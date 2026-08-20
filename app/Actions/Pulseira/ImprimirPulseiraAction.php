<?php

declare(strict_types=1);

namespace App\Actions\Pulseira;

use App\Events\PulseiraImpressa;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\Atendimento;
use App\Models\ClassificacaoRisco;
use App\Models\Paciente;
use App\Models\PulseiraImpressao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Pulseira\GerarPulseiraService;
use Illuminate\Support\Facades\DB;

/**
 * RF-15: toda impressão é rastreada — quem, quando, qual cor, por quê.
 *
 * RF-16 / RN-03: a reimpressão usa **o mesmo token**. O token é permanente; esta Action
 * registra eventos de impressão, nunca gera token novo. Uma pulseira reimpressa que
 * deixasse de resolver seria risco assistencial, e é exatamente o que aconteceria se
 * cada impressão gerasse um identificador diferente.
 */
final class ImprimirPulseiraAction
{
    public function __construct(
        private readonly GerarPulseiraService $pulseiras,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @return array{impressao: PulseiraImpressao, pdf: string}
     *
     * @throws OperadorSemRegistroProfissionalException
     */
    public function execute(
        Paciente $paciente,
        User $operador,
        ?Atendimento $atendimento = null,
        string $motivo = 'PRIMEIRA',
        ?string $observacao = null,
    ): array {
        /*
         * `impressa_por` é NOT NULL com FK para `profissional`: quem imprime precisa ser
         * um profissional identificável, porque "quem imprimiu" é parte da
         * rastreabilidade da pulseira (RF-15).
         *
         * O caso real que cai aqui é o administrador do sistema: ele tem a permission
         * `pulseira.imprimir` pela matriz da doc §2.3, mas pode ser uma conta de TI sem
         * registro profissional. Sem esta verificação, a tentativa morria numa violação
         * de constraint do MySQL -- mensagem que não diz nada a quem está na recepção.
         */
        $profissional = $operador->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('imprimir pulseira');
        }

        // A cor impressa é a classificação VIGENTE do atendimento (RN-09): quando o
        // paciente é reclassificado, a pulseira antiga passa a mentir sobre a
        // prioridade dele -- por isso a reclassificação dispara reimpressão.
        $classificacao = $atendimento?->classificacaoRisco;

        return DB::transaction(function () use (
            $paciente, $profissional, $operador, $atendimento, $classificacao, $motivo, $observacao
        ) {
            $impressao = PulseiraImpressao::create([
                'paciente_id' => $paciente->user_id,
                'atendimento_id' => $atendimento?->id,
                'classificacao_risco_id' => $classificacao?->id,
                'motivo' => $motivo,
                'observacao' => $observacao,
                'impressa_por' => $profissional->user_id,
                // RN-29: hora do servidor.
                'criado_em' => now(),
            ]);

            $pdf = $this->pulseiras->pdf(
                paciente: $paciente,
                atendimento: $atendimento,
                classificacao: $classificacao instanceof ClassificacaoRisco ? $classificacao : null,
                impressaEm: $impressao->criado_em,
            );

            $this->auditoria->registrar(
                acao: 'pulseira.imprimir',
                paciente: $paciente,
                atendimento: $atendimento,
                entidade: 'PulseiraImpressao',
                entidadeId: $impressao->id,
                justificativa: $motivo,
                usuario: $operador,
            );

            PulseiraImpressa::dispatch($impressao);

            return ['impressao' => $impressao, 'pdf' => $pdf];
        });
    }
}

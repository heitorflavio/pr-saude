<?php

declare(strict_types=1);

namespace App\Actions\Atendimento;

use App\Enums\StatusAtendimento;
use App\Events\StatusAtendimentoAlterado;
use App\Exceptions\DesfechoObrigatorioException;
use App\Exceptions\TransicaoInvalidaException;
use App\Models\Atendimento;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * A **única** porta de entrada para mudança de status (doc §6.3).
 *
 * Nada mais no sistema escreve `atendimento.status`. É o que garante que RN-13 (só
 * transições legais), RN-15 (histórico acrescentado, nunca sobrescrito) e RF-39
 * (permanência por status) valham sempre — e não apenas nos caminhos que alguém lembrou
 * de cobrir.
 */
final class AlterarStatusAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * @throws TransicaoInvalidaException RN-13.
     * @throws DesfechoObrigatorioException RN-14.
     */
    public function execute(
        Atendimento $atendimento,
        StatusAtendimento $novoStatus,
        User $autor,
        ?string $observacao = null,
        ?string $desfecho = null,
        ?string $desfechoObservacao = null,
    ): Atendimento {
        $atual = $atendimento->status;

        // RN-13
        if (! $atual->podeTransitarPara($novoStatus)) {
            throw new TransicaoInvalidaException($atual, $novoStatus);
        }

        // RN-14: o CHECK ck_atend_desfecho recusaria no banco; recusar aqui dá mensagem.
        if ($novoStatus === StatusAtendimento::Finalizado && blank($desfecho)) {
            throw DesfechoObrigatorioException::paraFinalizar();
        }

        return DB::transaction(function () use (
            $atendimento, $atual, $novoStatus, $autor, $observacao, $desfecho, $desfechoObservacao
        ) {
            $antes = $atendimento->getAttributes();

            /*
             * RF-39: quanto tempo o atendimento passou no status que está deixando.
             *
             * A referência é a última transição registrada; quando não há nenhuma (caso
             * que não deveria ocorrer, já que a abertura grava a primeira), cai para
             * `admitido_em`.
             */
            $ultima = $atendimento->statusHistorico()->latest('criado_em')->first();
            $referencia = $ultima?->criado_em ?? $atendimento->admitido_em;

            /*
             * `permanencia_segundos` é `INT UNSIGNED`: um valor negativo derruba o
             * INSERT com "Out of range value". Isso acontece de verdade sempre que a
             * referência é posterior ao agora -- desvio de relógio entre servidores,
             * registro importado com data retroativa, ou correção manual de horário.
             *
             * Perder a transição de status por causa de um relógio seria pior que
             * gravar zero: o paciente ficaria preso no estado anterior.
             */
            $permanencia = $referencia !== null
                ? max(0, (int) $referencia->diffInSeconds(now()))
                : null;

            // RN-15: acrescenta, nunca sobrescreve.
            $atendimento->statusHistorico()->create([
                'status_anterior' => $atual->value,
                'status_novo' => $novoStatus->value,
                'alterado_por' => $autor->id,
                'observacao' => $observacao,
                'permanencia_segundos' => $permanencia,
                // RN-29: hora do servidor.
                'criado_em' => now(),
            ]);

            $atendimento->status = $novoStatus;

            if ($novoStatus === StatusAtendimento::EmAtendimento && $atendimento->primeiro_atendimento_em === null) {
                $atendimento->primeiro_atendimento_em = now();
            }

            if ($novoStatus->ehTerminal()) {
                $atendimento->finalizado_em = now();

                if ($desfecho !== null) {
                    $atendimento->desfecho = $desfecho;
                    $atendimento->desfecho_observacao = $desfechoObservacao;
                }

                /*
                 * Encerra a presença na fila. Sem isto o paciente continuaria aparecendo
                 * no painel de quem o atendeu depois de ter ido embora -- e a carga
                 * ponderada do profissional (doc §7.4) ficaria permanentemente inflada.
                 */
                $atendimento->filaItens()
                    ->whereIn('situacao', ['AGUARDANDO', 'CHAMADO', 'EM_ATENDIMENTO'])
                    ->update([
                        'situacao' => $novoStatus === StatusAtendimento::Cancelado ? 'DESISTENCIA' : 'CONCLUIDO',
                        'saiu_em' => now(),
                    ]);
            }

            $atendimento->save();

            $this->auditoria->registrar(
                acao: 'atendimento.alterar_status',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'Atendimento',
                entidadeId: $atendimento->id,
                antes: $antes,
                depois: $atendimento->getAttributes(),
                justificativa: $observacao,
                usuario: $autor,
            );

            // RF-38: atualiza painéis e o portal do paciente.
            StatusAtendimentoAlterado::dispatch($atendimento, $atual, $novoStatus);

            return $atendimento;
        });
    }
}

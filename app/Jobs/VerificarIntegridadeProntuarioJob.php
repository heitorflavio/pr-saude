<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\IntegridadeProntuarioViolada;
use App\Models\Atendimento;
use App\Models\RegistroClinico;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Prontuario\HashEncadeadoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * doc §9.4 — verificação periódica da cadeia de hash.
 *
 * A cadeia não impede a adulteração; ela a torna **detectável**. Detecção que ninguém
 * executa é indistinguível de nenhuma detecção — por isso a verificação é rotina
 * agendada, e não um botão que alguém aperta quando desconfia. Adulteração descoberta
 * seis meses depois, por acaso, já não serve como evidência de nada.
 */
class VerificarIntegridadeProntuarioJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int|null  $atendimentoId  Restringe a um atendimento (verificação sob demanda,
     *                                   em auditoria). Nulo varre a janela recente.
     * @param  int  $janelaDias  Só os atendimentos com registro criado nos últimos N dias.
     *                           Varrer o histórico inteiro a cada hora custaria caro e não
     *                           acrescentaria nada: o passado distante não muda sozinho.
     */
    public function __construct(
        public readonly ?int $atendimentoId = null,
        public readonly int $janelaDias = 7,
    ) {}

    public function handle(HashEncadeadoService $hashes, AuditoriaService $auditoria): void
    {
        foreach ($this->atendimentosParaVerificar() as $atendimento) {
            $resultado = $hashes->verificarCadeia($atendimento->id);

            if ($resultado['integra']) {
                continue;
            }

            /*
             * O log da quebra é auditoria, não `registro_clinico`: escrever o alarme na
             * mesma cadeia que ele acusa daria a quem adulterou a chance de adulterar
             * também o aviso.
             */
            $auditoria->registrar(
                acao: 'prontuario.integridade_violada',
                atendimento: $atendimento,
                entidade: 'Atendimento',
                entidadeId: $atendimento->id,
                depois: ['quebras' => $resultado['quebras']],
                justificativa: 'Verificação automática da cadeia de hash (doc §9.4).',
            );

            Log::critical('Integridade do prontuário violada.', [
                'atendimento_id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'quebras' => $resultado['quebras'],
            ]);

            IntegridadeProntuarioViolada::dispatch($atendimento, $resultado['quebras']);
        }
    }

    /**
     * @return Collection<int, Atendimento>
     */
    private function atendimentosParaVerificar(): Collection
    {
        if ($this->atendimentoId !== null) {
            return Atendimento::whereKey($this->atendimentoId)->get();
        }

        $ids = RegistroClinico::query()
            ->where('criado_em', '>=', now()->subDays($this->janelaDias))
            ->distinct()
            ->pluck('atendimento_id');

        return Atendimento::whereIn('id', $ids)->get();
    }
}

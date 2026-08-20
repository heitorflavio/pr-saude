<?php

declare(strict_types=1);

namespace App\Actions\Triagem;

use App\Actions\Atendimento\AlterarStatusAction;
use App\Enums\StatusAtendimento;
use App\Events\EmergenciaDetectada;
use App\Events\ReimpressaoPulseiraNecessaria;
use App\Events\TriagemRealizada;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Exceptions\TriagemInvalidaException;
use App\Models\Atendimento;
use App\Models\ClassificacaoRisco;
use App\Models\SinalVital;
use App\Models\Triagem;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * Triagem inicial: classificação de risco pelo Protocolo de Manchester.
 *
 * Duas saídas possíveis, e a diferença entre elas é a razão de o módulo existir:
 *
 * - **Vermelho** (RN-11): o paciente **não entra em fila**. Vai direto para
 *   `EM_ATENDIMENTO` e o plantão é notificado. Colocar uma parada cardiorrespiratória
 *   na posição 1 de uma fila ainda seria colocá-la numa fila.
 * - **Demais cores**: `AGUARDANDO_ATENDIMENTO` e criação do `fila_item`, que a
 *   `vw_fila_ordenada` ordena por prioridade e, dentro dela, por ordem de chegada
 *   (RN-10).
 */
final class RealizarTriagemAction
{
    public function __construct(
        private readonly AlterarStatusAction $alterarStatus,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>|null  $sinaisVitais
     *
     * @throws TriagemInvalidaException
     */
    public function execute(
        Atendimento $atendimento,
        int $classificacaoRiscoId,
        User $autor,
        string $queixaPrincipal,
        ?string $justificativa = null,
        ?array $sinaisVitais = null,
    ): Triagem {
        if ($atendimento->status->ehTerminal()) {
            throw TriagemInvalidaException::atendimentoEncerrado();
        }

        if ($atendimento->triagens()->exists()) {
            throw TriagemInvalidaException::atendimentoJaTriado();
        }

        $profissionalId = $this->profissionalDe($autor);
        $classificacao = ClassificacaoRisco::findOrFail($classificacaoRiscoId);

        return DB::transaction(function () use (
            $atendimento, $classificacao, $autor, $profissionalId, $queixaPrincipal, $justificativa, $sinaisVitais
        ) {
            $sinalVital = $this->registrarSinaisVitais($atendimento, $sinaisVitais, $profissionalId);

            $triagem = $atendimento->triagens()->create([
                'classificacao_risco_id' => $classificacao->id,
                'sinal_vital_id' => $sinalVital?->id,
                'realizada_por' => $profissionalId,
                'queixa_principal' => $queixaPrincipal,
                'justificativa_classificacao' => $justificativa,
                'reclassificacao' => false,
                'triagem_anterior_id' => null,
                // RN-29: hora do servidor.
                'criado_em' => now(),
            ]);

            // RN-09: a classificação vigente do atendimento passa a ser esta.
            $atendimento->classificacao_risco_id = $classificacao->id;
            $atendimento->save();

            if ($classificacao->exige_atendimento_imediato) {
                // RN-11: vermelho não espera. Nada de `fila_item`.
                $this->alterarStatus->execute(
                    atendimento: $atendimento,
                    novoStatus: StatusAtendimento::EmAtendimento,
                    autor: $autor,
                    observacao: 'Classificação vermelha: atendimento imediato (RN-11).',
                );

                EmergenciaDetectada::dispatch($atendimento->fresh());
            } else {
                $this->alterarStatus->execute(
                    atendimento: $atendimento,
                    novoStatus: StatusAtendimento::AguardandoAtendimento,
                    autor: $autor,
                    observacao: 'Triagem concluída: '.$classificacao->nome.'.',
                );

                /*
                 * `profissional_id` nulo = fila geral. A atribuição a um profissional é
                 * a Fase 7 (UC-05); até lá o paciente fica visível na fila da unidade,
                 * que é melhor que ficar invisível esperando alguém assumi-lo.
                 */
                $atendimento->filaItens()->create([
                    'profissional_id' => null,
                    'classificacao_risco_id' => $classificacao->id,
                    'situacao' => 'AGUARDANDO',
                    // RN-10: o desempate entre cores iguais é por ordem de chegada.
                    'entrou_em' => now(),
                    'criado_por' => $autor->id,
                ]);
            }

            // RF-13: a pulseira sai com a cor da classificação.
            ReimpressaoPulseiraNecessaria::dispatch($atendimento->fresh(), 'PRIMEIRA');

            $this->auditoria->registrar(
                acao: 'triagem.classificar',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'Triagem',
                entidadeId: $triagem->id,
                depois: $triagem->getAttributes(),
                usuario: $autor,
            );

            TriagemRealizada::dispatch($triagem, false);

            return $triagem;
        });
    }

    /**
     * D-06: sinais vitais em tabela própria, série temporal. Os `CHECK` de faixa
     * (dor 0-10, SpO2 0-100, temperatura 25-45) são do banco -- um erro de digitação que
     * vira decisão clínica precisa falhar na escrita.
     *
     * @param  array<string, mixed>|null  $sinaisVitais
     */
    private function registrarSinaisVitais(
        Atendimento $atendimento,
        ?array $sinaisVitais,
        int $profissionalId,
    ): ?SinalVital {
        if ($sinaisVitais === null || $sinaisVitais === []) {
            return null;
        }

        return $atendimento->sinaisVitais()->create([
            ...$sinaisVitais,
            'aferido_por' => $profissionalId,
            'aferido_em' => now(),
        ]);
    }

    private function profissionalDe(User $autor): int
    {
        $profissional = $autor->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('realizar triagem');
        }

        return $profissional->user_id;
    }
}

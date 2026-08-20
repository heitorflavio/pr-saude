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
 * Reclassificação de risco (doc §7.5) — o mecanismo de segurança do módulo de fila.
 *
 * Três pontos, e nenhum deles é detalhe de implementação:
 *
 * 1. **A triagem anterior não é sobrescrita.** Cria-se uma nova, encadeada por
 *    `triagem_anterior_id`. Reconstruir o raciocínio clínico — "entrou verde e virou
 *    laranja às 14h20, com queda de saturação" — é o que sustenta a auditoria de evento
 *    adverso. Um `UPDATE` apagaria exatamente a informação que a investigação procura.
 *
 * 2. **`entrou_em` é preservado.** Reclassificar não é penalidade nem prêmio de posição:
 *    é correção de gravidade. Alterando apenas `classificacao_risco_id` no `fila_item`
 *    já existente, a reordenação acontece sozinha na próxima leitura da
 *    `vw_fila_ordenada` — não há recálculo de posições, porque posição não é persistida
 *    (RN-10).
 *
 * 3. **Dispara a reimpressão da pulseira** (RN-09). Uma pulseira verde num paciente
 *    laranja é pior que nenhuma pulseira: comunica ativamente a informação errada.
 */
final class ReclassificarRiscoAction
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
        int $novaClassificacaoId,
        User $autor,
        string $justificativa,
        ?array $sinaisVitais = null,
    ): Triagem {
        if ($atendimento->status->ehTerminal()) {
            throw TriagemInvalidaException::atendimentoEncerrado();
        }

        $anterior = $atendimento->triagemVigente();

        if ($anterior === null) {
            throw TriagemInvalidaException::semTriagemAnterior();
        }

        if (trim($justificativa) === '') {
            throw TriagemInvalidaException::justificativaObrigatoria();
        }

        $profissional = $autor->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('reclassificar risco');
        }

        $nova = ClassificacaoRisco::findOrFail($novaClassificacaoId);

        return DB::transaction(function () use (
            $atendimento, $anterior, $nova, $autor, $profissional, $justificativa, $sinaisVitais
        ) {
            $sinalVital = $this->registrarSinaisVitais($atendimento, $sinaisVitais, $profissional->user_id);

            // 1. Nova triagem encadeada; a anterior permanece intacta.
            $triagem = $atendimento->triagens()->create([
                'classificacao_risco_id' => $nova->id,
                'sinal_vital_id' => $sinalVital?->id,
                'realizada_por' => $profissional->user_id,
                // A queixa principal acompanha: o motivo da vinda não muda porque a
                // gravidade mudou.
                'queixa_principal' => $anterior->queixa_principal,
                'justificativa_classificacao' => $justificativa,
                'reclassificacao' => true,
                'triagem_anterior_id' => $anterior->id,
                'criado_em' => now(),
            ]);

            // RN-09: a classificação vigente do atendimento passa a ser a nova.
            $atendimento->classificacao_risco_id = $nova->id;
            $atendimento->save();

            // 2. `entrou_em` intacto: só a prioridade do item muda, e a view reordena.
            $atendimento->filaItemAtivo()?->update([
                'classificacao_risco_id' => $nova->id,
            ]);

            // 3. RN-09
            ReimpressaoPulseiraNecessaria::dispatch($atendimento->fresh(), 'RECLASSIFICACAO');

            // RN-11: vermelho não espera, mesmo que tenha chegado verde.
            if ($nova->exige_atendimento_imediato) {
                $this->promoverParaAtendimentoImediato($atendimento, $autor);
                EmergenciaDetectada::dispatch($atendimento->fresh());
            }

            $this->auditoria->registrar(
                acao: 'triagem.reclassificar',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'Triagem',
                entidadeId: $triagem->id,
                antes: ['classificacao_risco_id' => $anterior->classificacao_risco_id],
                depois: ['classificacao_risco_id' => $nova->id],
                justificativa: $justificativa,
                usuario: $autor,
            );

            TriagemRealizada::dispatch($triagem, true);

            return $triagem;
        });
    }

    /**
     * O paciente reclassificado como vermelho sai da fila e vai para atendimento.
     *
     * A transição só é tentada quando é legal a partir do estado atual: se ele já está
     * `EM_ATENDIMENTO` ou `EM_OBSERVACAO`, a piora não deve derrubar o fluxo com uma
     * `TransicaoInvalidaException` — a reclassificação em si é o que importa registrar.
     */
    private function promoverParaAtendimentoImediato(Atendimento $atendimento, User $autor): void
    {
        $atendimento->refresh();

        if (! $atendimento->status->podeTransitarPara(StatusAtendimento::EmAtendimento)) {
            return;
        }

        $this->alterarStatus->execute(
            atendimento: $atendimento,
            novoStatus: StatusAtendimento::EmAtendimento,
            autor: $autor,
            observacao: 'Reclassificado como vermelho: atendimento imediato (RN-11).',
        );

        // Sai da fila: quem está em atendimento não ocupa posição.
        $atendimento->filaItemAtivo()?->update([
            'situacao' => 'EM_ATENDIMENTO',
            'chamado_em' => now(),
        ]);
    }

    /**
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

        // D-06: série temporal. A aferição da reclassificação é um novo ponto, não uma
        // correção da anterior -- é a comparação entre as duas que mostra a piora.
        return $atendimento->sinaisVitais()->create([
            ...$sinaisVitais,
            'aferido_por' => $profissionalId,
            'aferido_em' => now(),
        ]);
    }
}

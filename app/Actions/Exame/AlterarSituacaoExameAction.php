<?php

declare(strict_types=1);

namespace App\Actions\Exame;

use App\Enums\SituacaoExame;
use App\Events\SituacaoExameAlterada;
use App\Exceptions\ExameInvalidoException;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\ExameSolicitacao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/** Porta única para coleta, início de execução e cancelamento. */
final class AlterarSituacaoExameAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(ExameSolicitacao $solicitacao, SituacaoExame $destino, User $autor, ?string $motivo = null): ExameSolicitacao
    {
        $origem = $solicitacao->situacao;
        if (! $origem->podeTransitarPara($destino)
            || in_array($destino, [SituacaoExame::Concluido, SituacaoExame::Liberado], true)) {
            throw ExameInvalidoException::transicao($origem, $destino);
        }
        if ($destino === SituacaoExame::Cancelado && blank($motivo)) {
            throw new ExameInvalidoException('O cancelamento exige motivo.');
        }

        $profissional = $autor->profissional;
        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('executar exame');
        }

        return DB::transaction(function () use ($solicitacao, $origem, $destino, $autor, $profissional, $motivo) {
            $dados = ['situacao' => $destino];
            if ($destino === SituacaoExame::Coletado) {
                $dados += ['coletado_em' => now(), 'coletado_por' => $profissional->user_id];
            }
            if ($destino === SituacaoExame::Cancelado) {
                $dados += ['cancelado_em' => now(), 'cancelado_por' => $profissional->user_id, 'motivo_cancelamento' => trim((string) $motivo)];
            }
            $solicitacao->update($dados);

            $this->auditoria->registrar(
                acao: 'exame.alterar_situacao', atendimento: $solicitacao->atendimento,
                entidade: 'ExameSolicitacao', entidadeId: $solicitacao->id,
                antes: ['situacao' => $origem->value], depois: ['situacao' => $destino->value],
                justificativa: $motivo, usuario: $autor,
            );
            SituacaoExameAlterada::dispatch($solicitacao, $origem, $destino);

            return $solicitacao->fresh();
        });
    }
}

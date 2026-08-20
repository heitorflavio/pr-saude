<?php

declare(strict_types=1);

namespace App\Actions\Exame;

use App\Enums\SituacaoExame;
use App\Events\ResultadoExameLiberado;
use App\Exceptions\ExameInvalidoException;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\ExameResultado;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class LiberarResultadoAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(ExameResultado $resultado, User $autor): ExameResultado
    {
        $resultado->loadMissing('solicitacao.atendimento');
        if ($resultado->foiLiberado() || $resultado->solicitacao->situacao !== SituacaoExame::Concluido) {
            throw ExameInvalidoException::transicao($resultado->solicitacao->situacao, SituacaoExame::Liberado);
        }

        $profissional = $autor->profissional;
        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('liberar resultado de exame');
        }

        // RN-25: para valor crítico, a própria liberação por médico é o registro de
        // ciência. O schema não possui outro campo de ciência separado.
        if ($resultado->possui_valor_critico && $profissional->categoria !== 'MEDICO') {
            throw ExameInvalidoException::valorCriticoSemCiencia();
        }

        return DB::transaction(function () use ($resultado, $autor, $profissional) {
            $resultado->update([
                'liberado_por' => $profissional->user_id,
                'liberado_em' => now(),
                'visivel_ao_paciente' => true,
            ]);
            $resultado->solicitacao->update(['situacao' => SituacaoExame::Liberado]);

            $this->auditoria->registrar(
                acao: 'exame.liberar_resultado', atendimento: $resultado->solicitacao->atendimento,
                entidade: 'ExameResultado', entidadeId: $resultado->id,
                antes: ['visivel_ao_paciente' => false], depois: ['visivel_ao_paciente' => true],
                usuario: $autor,
            );
            ResultadoExameLiberado::dispatch($resultado);

            return $resultado->fresh();
        });
    }
}

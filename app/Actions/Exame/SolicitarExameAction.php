<?php

declare(strict_types=1);

namespace App\Actions\Exame;

use App\Enums\SituacaoExame;
use App\Events\ExameSolicitado;
use App\Exceptions\ExameInvalidoException;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\Atendimento;
use App\Models\Exame;
use App\Models\ExameSolicitacao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class SolicitarExameAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(Atendimento $atendimento, Exame $exame, User $autor, string $carater, ?string $indicacaoClinica = null): ExameSolicitacao
    {
        if ($atendimento->status->ehTerminal()) {
            throw ExameInvalidoException::atendimentoEncerrado();
        }

        $medico = $autor->profissional;
        if ($medico === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('solicitar exame');
        }
        if (! $medico->ativo || $medico->categoria !== 'MEDICO' || blank($medico->conselho_numero)) {
            throw new ExameInvalidoException('Solicitação de exame exige médico ativo com conselho válido.');
        }
        if (! $exame->ativo) {
            throw new ExameInvalidoException('O exame selecionado está inativo no catálogo.');
        }

        return DB::transaction(function () use ($atendimento, $exame, $autor, $medico, $carater, $indicacaoClinica) {
            $solicitacao = ExameSolicitacao::create([
                'atendimento_id' => $atendimento->id,
                'exame_id' => $exame->id,
                'solicitado_por' => $medico->user_id,
                'carater' => $carater,
                'indicacao_clinica' => filled($indicacaoClinica) ? trim((string) $indicacaoClinica) : null,
                'situacao' => SituacaoExame::Solicitado,
                // RN-29: data clínica do servidor.
                'solicitado_em' => now(),
            ]);

            $this->auditoria->registrar(
                acao: 'exame.solicitar', paciente: $atendimento->paciente, atendimento: $atendimento,
                entidade: 'ExameSolicitacao', entidadeId: $solicitacao->id,
                depois: ['exame_id' => $exame->id, 'carater' => $carater], usuario: $autor,
            );
            ExameSolicitado::dispatch($solicitacao);

            return $solicitacao;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Prontuario;

use App\Events\DiagnosticoRegistrado;
use App\Exceptions\DiagnosticoInvalidoException;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\Atendimento;
use App\Models\Cid10;
use App\Models\Diagnostico;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * RF-46 — hipótese diagnóstica e diagnóstico definitivo com código CID-10.
 *
 * `natureza` distingue os três momentos do raciocínio: `SUSPEITA` (hipótese em aberto),
 * `DIFERENCIAL` (o que ainda precisa ser descartado) e `DEFINITIVO` (o que se sustentou).
 * Guardar os três, e não só o último, é o que permite avaliar depois se a investigação
 * foi razoável — um prontuário que só registra o acerto final não mostra raciocínio.
 *
 * O diagnóstico **não é retificado por adendo**: ele não é `registro_clinico`. A revisão
 * de hipótese é um diagnóstico novo; o anterior permanece, porque explica as condutas
 * tomadas enquanto valia.
 */
final class RegistrarDiagnosticoAction
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @throws DiagnosticoInvalidoException
     */
    public function execute(
        Atendimento $atendimento,
        string $cid10Codigo,
        User $autor,
        string $natureza = 'SUSPEITA',
        bool $principal = false,
        ?string $observacao = null,
    ): Diagnostico {
        if ($atendimento->status->ehTerminal()) {
            throw DiagnosticoInvalidoException::atendimentoEncerrado();
        }

        $cid10Codigo = mb_strtoupper(trim($cid10Codigo));

        if (! Cid10::where('codigo', $cid10Codigo)->exists()) {
            throw DiagnosticoInvalidoException::cid10Inexistente($cid10Codigo);
        }

        // Marcar como principal uma hipótese ainda em aberto transformaria a dúvida em
        // afirmação na estatística e no faturamento.
        if ($principal && $natureza === 'SUSPEITA') {
            throw DiagnosticoInvalidoException::suspeitaNaoPodeSerPrincipal();
        }

        $profissional = $autor->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('registrar diagnóstico');
        }

        return DB::transaction(function () use (
            $atendimento, $cid10Codigo, $autor, $profissional, $natureza, $principal, $observacao
        ) {
            if ($atendimento->diagnosticos()->where('cid10_codigo', $cid10Codigo)->exists()) {
                throw DiagnosticoInvalidoException::duplicado($cid10Codigo);
            }

            // Um atendimento tem um diagnóstico principal: é ele que responde "por que
            // este paciente esteve aqui". Dois tornam a resposta ambígua.
            if ($principal) {
                $jaPrincipal = $atendimento->diagnosticos()->where('principal', true)->first();

                if ($jaPrincipal !== null) {
                    throw DiagnosticoInvalidoException::principalJaDefinido($jaPrincipal->cid10_codigo);
                }
            }

            $diagnostico = $atendimento->diagnosticos()->create([
                'cid10_codigo' => $cid10Codigo,
                'natureza' => $natureza,
                'principal' => $principal,
                'observacao' => $observacao,
                'registrado_por' => $profissional->user_id,
                // RN-29: hora do servidor.
                'criado_em' => now(),
            ]);

            $this->auditoria->registrar(
                acao: 'diagnostico.registrar',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'Diagnostico',
                entidadeId: $diagnostico->id,
                depois: [
                    'cid10_codigo' => $cid10Codigo,
                    'natureza' => $natureza,
                    'principal' => $principal,
                ],
                usuario: $autor,
            );

            DiagnosticoRegistrado::dispatch($diagnostico);

            return $diagnostico;
        });
    }
}

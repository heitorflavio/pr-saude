<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Atendimento;
use App\Models\Paciente;
use App\Services\Auditoria\AuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * doc §14.3: registra o ACESSO, não apenas a alteração.
 *
 * Aplicado às rotas que expõem dado clínico. Grava depois de a resposta ser produzida e
 * só quando ela foi bem-sucedida: registrar tentativa negada como se fosse acesso
 * poluiria a resposta de "quem acessou os dados deste paciente?" com ruído -- a negativa
 * é registrada pelo `ExigirVinculoAssistencial`, com a semântica correta.
 *
 * Uso: `->middleware('auditar:prontuario.ler')` na rota.
 */
final class RegistrarAuditoria
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function handle(Request $request, Closure $next, ?string $acao = null): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $usuario = $request->user() ?? $request->user('paciente');

        if ($usuario === null) {
            return $response;
        }

        $paciente = $this->pacienteDaRota($request);
        $atendimento = $this->atendimentoDaRota($request);

        $this->auditoria->registrarLeitura(
            acao: $acao ?? ($request->route()?->getName() ?? $request->path()),
            paciente: $paciente,
            atendimento: $atendimento,
            entidade: $paciente !== null ? 'Paciente' : ($atendimento !== null ? 'Atendimento' : null),
            entidadeId: $paciente?->user_id ?? $atendimento?->id,
            justificativa: $request->session()->pull('justificativa_quebra_sigilo'),
        );

        return $response;
    }

    private function pacienteDaRota(Request $request): ?Paciente
    {
        $parametro = $request->route('paciente');

        return $parametro instanceof Paciente ? $parametro : null;
    }

    private function atendimentoDaRota(Request $request): ?Atendimento
    {
        $parametro = $request->route('atendimento');

        return $parametro instanceof Atendimento ? $parametro : null;
    }
}

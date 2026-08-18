<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Paciente;
use App\Services\Auditoria\AuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN-28: acesso a paciente sem vínculo assistencial exige justificativa registrada --
 * o padrão "break the glass", exigível pela LGPD sob o princípio da necessidade
 * (art. 6º, III).
 *
 * O desenho é deliberadamente permissivo e integralmente auditado: quem justifica,
 * entra. Bloquear de fato seria pior -- o profissional que precisa do dado numa
 * emergência encontraria um caminho fora do sistema, e aí não haveria registro nenhum.
 * O controle aqui não é a porta, é a câmera.
 */
final class ExigirVinculoAssistencial
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function handle(Request $request, Closure $next): Response
    {
        $paciente = $request->route('paciente');
        $usuario = $request->user();

        if (! $paciente instanceof Paciente || $usuario === null) {
            return $next($request);
        }

        // Vínculo assistencial existente: caminho normal, sem fricção.
        if (Gate::forUser($usuario)->allows('verContexto', $paciente)) {
            return $next($request);
        }

        $justificativa = trim((string) $request->input('justificativa_quebra_sigilo', ''));

        if ($justificativa === '') {
            $this->auditoria->registrar(
                acao: 'prontuario.quebra_sigilo.negada',
                paciente: $paciente,
                entidade: 'Paciente',
                entidadeId: $paciente->user_id,
            );

            abort(403, 'Você não possui vínculo assistencial com este paciente. Informe uma justificativa para acessar (RN-28).');
        }

        if (Gate::forUser($usuario)->denies('quebrarSigilo', $paciente)) {
            $this->auditoria->registrar(
                acao: 'prontuario.quebra_sigilo.negada',
                paciente: $paciente,
                entidade: 'Paciente',
                entidadeId: $paciente->user_id,
                justificativa: $justificativa,
            );

            abort(403, 'Você não tem permissão para quebra de sigilo.');
        }

        $this->auditoria->registrar(
            acao: 'prontuario.quebra_sigilo',
            paciente: $paciente,
            entidade: 'Paciente',
            entidadeId: $paciente->user_id,
            justificativa: $justificativa,
        );

        // Deixa a justificativa disponível para o RegistrarAuditoria da mesma requisição.
        $request->session()->flash('justificativa_quebra_sigilo', $justificativa);

        return $next($request);
    }
}

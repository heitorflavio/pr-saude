<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AcessoPortalVigente
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * D-63: a janela da M-9 caiu — o portal não depende mais de atendimento aberto.
         * O que sobrou para este middleware é o único desligamento que continua existindo:
         * a conta desativada pela recepção. Sem esta verificação por requisição, quem já
         * estivesse logado seguiria navegando até a sessão expirar sozinha.
         */
        $usuario = $request->user('paciente');
        if ($usuario !== null && (! $usuario->ativo || $usuario->paciente === null)) {
            Auth::guard('paciente')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors([
                'cpf' => 'O seu acesso ao portal foi desativado. Procure a recepção.',
            ]);
        }

        return $next($request);
    }
}

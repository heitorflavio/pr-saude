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
        $usuario = $request->user('paciente');
        if ($usuario !== null && ! $usuario->paciente?->possuiAcessoVigente()) {
            Auth::guard('paciente')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors([
                'cpf' => 'O acesso ao portal não está mais vigente. Procure a recepção.',
            ]);
        }

        return $next($request);
    }
}

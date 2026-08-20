<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SenhaDefinitiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('paciente')?->senha_provisoria) {
            return redirect()->route('portal.senha');
        }

        return $next($request);
    }
}

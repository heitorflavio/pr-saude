<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RNF-09 e RNF-10: 30 minutos de inatividade para a equipe, 15 para o paciente.
 *
 * O Laravel tem um único `config('session.lifetime')` -- não há expiração por guard
 * nativa. Ler `config/session.php` e concluir que a exigência está atendida seria erro:
 * por isso a janela por guard é carimbada aqui, na sessão, e verificada a cada request.
 *
 * O motivo de a janela do paciente ser mais curta: ele acessa o portal do celular, com
 * frequência em sala de espera compartilhada, e não tem o hábito de encerrar sessão.
 */
final class ExpirarSessao
{
    private const MINUTOS_EQUIPE = 30;

    private const MINUTOS_PACIENTE = 15;

    private const CHAVE = 'ultima_atividade_em';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('paciente')->check() ? 'paciente' : 'web';

        if (! Auth::guard($guard)->check()) {
            return $next($request);
        }

        $limite = $guard === 'paciente' ? self::MINUTOS_PACIENTE : self::MINUTOS_EQUIPE;

        // Timestamp inteiro, não Carbon: o valor atravessa serialização de sessão sem
        // depender da classe, e a subtração é sempre positiva -- `diffInMinutes` no
        // Carbon 3 é sinalizado e devolveria negativo nesta ordem.
        $ultima = $request->session()->get(self::CHAVE);

        if (is_int($ultima) && (now()->getTimestamp() - $ultima) > $limite * 60) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $rota = $guard === 'paciente' ? 'portal.login' : 'login';

            return redirect()->route($rota)->with(
                'status',
                "Sua sessão expirou por inatividade de {$limite} minutos. Entre novamente."
            );
        }

        $request->session()->put(self::CHAVE, now()->getTimestamp());

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN-06: senha provisória força a troca no primeiro acesso.
 *
 * A senha inicial do paciente é a própria data de nascimento (RN-05) -- 15,3 bits de
 * entropia, um dado que está impresso na pulseira dele. Deixar essa credencial vigente
 * depois do primeiro acesso seria manter a conta praticamente aberta.
 */
final class SenhaProvisoria
{
    /**
     * Rotas que continuam alcançáveis com senha provisória: sem elas, o usuário ficaria
     * preso num laço de redirecionamento sem conseguir trocar a senha nem sair.
     *
     * @var array<int, string>
     */
    private const LIBERADAS = [
        'senha.provisoria',
        'senha.provisoria.atualizar',
        'password.confirm',
        'logout',
        'portal.senha',
        'portal.senha.atualizar',
        'portal.sair',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user() ?? $request->user('paciente');

        if ($usuario === null || ! $usuario->senha_provisoria) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::LIBERADAS, strict: true)) {
            return $next($request);
        }

        $destino = $usuario->ehPaciente() ? 'portal.senha' : 'senha.provisoria';

        return redirect()->route($destino)->with(
            'status',
            'Sua senha é provisória. Defina uma nova senha para continuar.'
        );
    }
}

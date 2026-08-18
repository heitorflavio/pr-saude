<?php

use App\Http\Middleware\ExigirVinculoAssistencial;
use App\Http\Middleware\ExpirarSessao;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RegistrarAuditoria;
use App\Http\Middleware\SenhaProvisoria;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // RNF-09 / RNF-10: 30 min equipe, 15 min paciente. Global porque uma sessão
            // esquecida aberta é risco em qualquer rota, não só nas clínicas.
            ExpirarSessao::class,
            // RN-06: senha provisória bloqueia o resto do sistema até ser trocada.
            SenhaProvisoria::class,
        ]);

        $middleware->alias([
            // Uso: ->middleware('auditar:prontuario.ler')
            'auditar' => RegistrarAuditoria::class,
            // RN-28: break the glass com justificativa registrada.
            'vinculo' => ExigirVinculoAssistencial::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

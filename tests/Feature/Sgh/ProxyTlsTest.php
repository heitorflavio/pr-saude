<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Quando o TLS termina fora do PHP (túnel ngrok na demonstração, balanceador em
 * produção), a requisição chega ao servidor em http. O gerador de URL então emite os
 * assets do Vite em http dentro de uma página https e o navegador bloqueia tudo como
 * mixed content — a aplicação abre sem estilo e sem JavaScript. Honrar o
 * X-Forwarded-Proto é o que evita isso, e só vale para proxy declarado: o mesmo pacote
 * de cabeçalhos carrega o X-Forwarded-For, que vira o IP da trilha de auditoria (RF-80).
 */
beforeEach(function () {
    Route::get('/_teste-proxy-tls', fn () => response()->json([
        'seguro' => request()->secure(),
        'asset' => asset('build/assets/app.css'),
    ]));
});

it('gera asset em https quando o proxy declarado informa X-Forwarded-Proto', function () {
    config(['trustedproxy.proxies' => '127.0.0.1,::1']);

    // URL absoluta em http: é assim que a requisição chega ao PHP depois que o túnel
    // termina o TLS. A base do teste é o APP_URL, que já é https e mascararia o defeito.
    $resposta = $this->get('http://pr-saude.test/_teste-proxy-tls', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'tunel.exemplo.test',
    ]);

    $resposta->assertOk();
    expect($resposta->json('seguro'))->toBeTrue();
    expect($resposta->json('asset'))->toBe('https://tunel.exemplo.test/build/assets/app.css');
});

it('ignora X-Forwarded-Proto enquanto nenhum proxy for declarado', function () {
    config(['trustedproxy.proxies' => null]);

    $resposta = $this->get('http://pr-saude.test/_teste-proxy-tls', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'atacante.exemplo.test',
    ]);

    $resposta->assertOk();
    expect($resposta->json('seguro'))->toBeFalse();
    expect($resposta->json('asset'))->toBe('http://pr-saude.test/build/assets/app.css');
});

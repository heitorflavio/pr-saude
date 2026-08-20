<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Proxies TLS confiáveis
    |---------------------------------------------------------------------------
    |
    | Lida por Illuminate\Http\Middleware\TrustProxies quando nenhum proxy é
    | declarado em bootstrap/app.php. Atrás de um túnel que termina o TLS (ngrok
    | na demonstração, balanceador em produção) o PHP recebe a requisição em
    | http: sem honrar o X-Forwarded-Proto o gerador de URL emite os assets do
    | Vite em http dentro de uma página https e o navegador bloqueia tudo como
    | mixed content.
    |
    | Vazio por padrão porque confiar em proxy é decisão de ambiente: com o
    | X-Forwarded-For aceito, o IP que a auditoria (RF-77) grava passa a vir de
    | um cabeçalho. Declare os IPs reais do proxy — '*' apenas quando o servidor
    | de aplicação for inalcançável fora dele.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),
];

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de hash padrao
    |--------------------------------------------------------------------------
    |
    | RNF-07 exige Argon2id. Nao e preferencia: Argon2id venceu a Password Hashing
    | Competition e e o unico dos tres candidatos resistente tanto a ataque por GPU
    | (custo de memoria) quanto a ataque por canal lateral (acesso a memoria
    | independente da senha). bcrypt nao tem custo de memoria configuravel.
    |
    | Se `password_algos()` do PHP nao listar `argon2id`, NAO trocar silenciosamente
    | para bcrypt -- a exigencia e do requisito, nao do ambiente.
    |
    */

    'driver' => 'argon2id',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parametros do Argon
    |--------------------------------------------------------------------------
    |
    | Padroes acima do minimo do PHP (65536 KiB / 4 iteracoes), seguindo a faixa
    | recomendada pelo OWASP Password Storage Cheat Sheet: 19 MiB de memoria com 2
    | iteracoes ja excede o piso, e 64 MiB com 4 iteracoes da folga confortavel para
    | um servidor de aplicacao dedicado.
    |
    | Os testes sobrescrevem estes valores para o minimo aceito pelo PHP (phpunit.xml):
    | com 64 MiB por hash, uma suite que cria dezenas de usuarios levaria minutos.
    |
    */

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],

];

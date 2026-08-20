<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato do gerador de token de pulseira (doc §8.2.1).
 *
 * Existe por uma razão concreta: E1 do UC-01 exige que a falha na geração do token
 * produza rollback integral do cadastro, e isso precisa ser **provado por teste**. Com
 * a implementação `final` (que ela deve ser -- ninguém deve poder sobrescrever a
 * geração de um identificador de segurança por herança), não haveria como injetar a
 * falha.
 *
 * O contrato resolve os dois requisitos ao mesmo tempo: a implementação continua
 * fechada, e a invariante fica testável.
 */
interface GeradorTokenPulseira
{
    /** Token novo: 22 caracteres base62 (≈131 bits) + 4 de checksum HMAC. */
    public function gerar(): string;

    /** Verifica o checksum em tempo constante, sem consultar o banco. */
    public function valido(string $token): bool;
}

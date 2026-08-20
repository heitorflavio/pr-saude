<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * E2 do UC-01: colisão de token de pulseira após as tentativas previstas.
 *
 * Probabilisticamente desprezível -- o espaço é 62²² ≈ 2,7 × 10³⁹ e a densidade de
 * tokens válidos em 20 anos de operação seria ≈ 3,7 × 10⁻³⁴ (doc §8.2.2). Se isto for
 * lançado alguma vez, a hipótese realista não é azar: é `random_int` mal semeado,
 * chave HMAC vazia ou banco replicando registros.
 *
 * Por isso a exceção existe em vez de um laço infinito: um cadastro que trava para
 * sempre é pior que um cadastro que falha alto e deixa rastro.
 */
final class TokenPulseiraIndisponivelException extends DominioException
{
    public static function aposTentativas(int $tentativas): self
    {
        return new self(
            "Não foi possível gerar um token de pulseira único após {$tentativas} tentativas. "
            .'Verifique PULSEIRA_KEY e a integridade de paciente.token_pulseira.'
        );
    }
}

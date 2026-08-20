<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * RN-30: falhas na regularização de identificação de paciente provisório.
 */
final class RegularizacaoInvalidaException extends DominioException
{
    public static function pacienteJaIdentificado(): self
    {
        return new self(
            'Este paciente já possui identificação definitiva. '
            .'A correção de CPF errado é atualização cadastral, não regularização.'
        );
    }

    public static function cpfInvalido(): self
    {
        return new self('CPF inválido: o dígito verificador não confere (RF-03).');
    }
}

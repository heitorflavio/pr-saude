<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AdministracaoInvalidaException extends DominioException
{
    public static function divergenciaSemObservacao(): self
    {
        return new self('A dose divergente é permitida, mas exige observação clínica.');
    }

    public static function motivoObrigatorio(): self
    {
        return new self('Informe o motivo da não administração.');
    }

    public static function prescritorInvalido(): self
    {
        return new self('A prescrição exige médico ativo com conselho profissional válido (RN-18).');
    }

    public static function atendimentoEncerrado(): self
    {
        return new self('Não é possível prescrever ou administrar em atendimento encerrado.');
    }
}

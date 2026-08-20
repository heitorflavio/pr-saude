<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PrescricaoNaoVigenteException extends DominioException
{
    public static function ordemInvalida(): self
    {
        return new self('Prescrição suspensa, concluída ou fora da vigência; administração não autorizada.');
    }
}

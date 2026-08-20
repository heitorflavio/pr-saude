<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplaChecagemObrigatoriaException extends DominioException
{
    public static function ausente(string $medicamento): self
    {
        return new self("{$medicamento} é de alta vigilância e exige conferência por um segundo profissional.");
    }

    public static function mesmoExecutor(): self
    {
        return new self('O executor não pode conferir a própria administração; identifique outro profissional.');
    }
}

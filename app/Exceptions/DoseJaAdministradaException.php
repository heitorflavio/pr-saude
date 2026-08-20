<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\AdministracaoMedicamento;

final class DoseJaAdministradaException extends DominioException
{
    public static function paraSituacao(string $situacao, ?AdministracaoMedicamento $registro = null): self
    {
        $detalhe = $registro?->executor?->nome_completo !== null
            ? " por {$registro->executor->nome_completo} em {$registro->administrado_em?->format('d/m/Y H:i')}"
            : '';

        return new self("Dose já registrada como {$situacao}{$detalhe}.");
    }
}

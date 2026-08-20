<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PacienteAlergia;

final class AlergiaBloqueanteException extends DominioException
{
    public function __construct(public readonly PacienteAlergia $alergia)
    {
        parent::__construct(
            "Paciente com alergia {$alergia->gravidade} a {$alergia->principioAtivo()}. "
            .'A administração está bloqueada; uma justificativa clínica é obrigatória para prosseguir.'
        );
    }
}

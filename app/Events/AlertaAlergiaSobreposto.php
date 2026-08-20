<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AdministracaoMedicamento;
use App\Models\PacienteAlergia;
use Illuminate\Foundation\Events\Dispatchable;

/** RN-21: ponto de integração para notificar o prescritor. */
final class AlertaAlergiaSobreposto
{
    use Dispatchable;

    public function __construct(
        public readonly AdministracaoMedicamento $administracao,
        public readonly PacienteAlergia $alergia,
    ) {}
}

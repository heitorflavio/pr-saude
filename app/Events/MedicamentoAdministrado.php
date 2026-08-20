<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AdministracaoMedicamento;
use Illuminate\Foundation\Events\Dispatchable;

final class MedicamentoAdministrado
{
    use Dispatchable;

    public function __construct(public readonly AdministracaoMedicamento $administracao) {}
}

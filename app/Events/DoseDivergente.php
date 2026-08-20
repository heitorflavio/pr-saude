<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AdministracaoMedicamento;
use App\Models\PrescricaoItem;
use Illuminate\Foundation\Events\Dispatchable;

final class DoseDivergente
{
    use Dispatchable;

    public function __construct(
        public readonly AdministracaoMedicamento $administracao,
        public readonly PrescricaoItem $item,
    ) {}
}

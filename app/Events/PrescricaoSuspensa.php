<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prescricao;
use Illuminate\Foundation\Events\Dispatchable;

final class PrescricaoSuspensa
{
    use Dispatchable;

    public function __construct(public readonly Prescricao $prescricao) {}
}

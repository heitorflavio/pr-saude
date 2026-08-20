<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Diagnostico;
use Illuminate\Foundation\Events\Dispatchable;

final class DiagnosticoRegistrado
{
    use Dispatchable;

    public function __construct(
        public readonly Diagnostico $diagnostico,
    ) {}
}

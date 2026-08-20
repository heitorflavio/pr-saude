<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExameResultado;
use Illuminate\Foundation\Events\Dispatchable;

final class ResultadoExameRegistrado
{
    use Dispatchable;

    public function __construct(public readonly ExameResultado $resultado) {}
}

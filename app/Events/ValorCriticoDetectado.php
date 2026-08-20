<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExameResultado;
use Illuminate\Foundation\Events\Dispatchable;

/** RN-25: notificação prioritária ao solicitante. */
final class ValorCriticoDetectado
{
    use Dispatchable;

    public function __construct(public readonly ExameResultado $resultado) {}
}

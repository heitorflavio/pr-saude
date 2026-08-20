<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\RegistroClinico;
use Illuminate\Foundation\Events\Dispatchable;

/** doc §7.7: emitido mesmo sem ouvinte — o ponto de extensão precede a extensão. */
final class RegistroClinicoCriado
{
    use Dispatchable;

    public function __construct(
        public readonly RegistroClinico $registro,
    ) {}
}

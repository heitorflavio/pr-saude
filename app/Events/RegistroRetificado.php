<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\RegistroClinico;
use Illuminate\Foundation\Events\Dispatchable;

final class RegistroRetificado
{
    use Dispatchable;

    public function __construct(
        public readonly RegistroClinico $original,
        public readonly RegistroClinico $adendo,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Triagem;
use Illuminate\Foundation\Events\Dispatchable;

final class TriagemRealizada
{
    use Dispatchable;

    public function __construct(
        public readonly Triagem $triagem,
        public readonly bool $reclassificacao = false,
    ) {}
}

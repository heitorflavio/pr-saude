<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Atendimento;
use Illuminate\Foundation\Events\Dispatchable;

final class AtendimentoAberto
{
    use Dispatchable;

    public function __construct(public readonly Atendimento $atendimento) {}
}

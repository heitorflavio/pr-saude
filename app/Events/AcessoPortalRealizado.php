<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Paciente;
use Illuminate\Foundation\Events\Dispatchable;

/** M-8: ponto de integração para notificar o telefone cadastrado. */
final class AcessoPortalRealizado
{
    use Dispatchable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly ?string $ip,
    ) {}
}

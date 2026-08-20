<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Paciente;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RN-30: o paciente não identificado ganhou CPF, preservando todo o histórico.
 */
final class IdentificacaoRegularizada
{
    use Dispatchable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly string $codigoProvisorioAnterior,
    ) {}
}

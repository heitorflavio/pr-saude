<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Atendimento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * doc §9.4: a cadeia de hash não impede a adulteração — ela a torna **detectável**.
 * Detectar sem avisar ninguém seria não detectar; por isso a quebra é um evento de
 * domínio, e não apenas uma linha de log.
 */
final class IntegridadeProntuarioViolada
{
    use Dispatchable;

    /**
     * @param  array<int, array{id: int, motivo: string}>  $quebras
     */
    public function __construct(
        public readonly Atendimento $atendimento,
        public readonly array $quebras,
    ) {}
}

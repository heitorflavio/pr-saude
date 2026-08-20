<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RF-38 / doc §7.7: é este evento que o painel da fila vai escutar quando a atualização
 * deixar de ser por polling e passar a WebSocket.
 *
 * Emitido desde já, mesmo sem ouvinte — é o que torna aquela migração barata: nenhuma
 * Action precisará ser reescrita, só um listener acrescentado.
 */
final class StatusAtendimentoAlterado
{
    use Dispatchable;

    public function __construct(
        public readonly Atendimento $atendimento,
        public readonly StatusAtendimento $anterior,
        public readonly StatusAtendimento $novo,
    ) {}
}

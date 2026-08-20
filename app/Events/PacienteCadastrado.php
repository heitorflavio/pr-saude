<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Paciente;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido mesmo sem ouvinte, de propósito (doc §7.7).
 *
 * É o que torna barata a migração futura para WebSocket e a integração com sistemas
 * externos: quando o painel precisar atualizar em tempo real, ou quando a impressora de
 * pulseiras virar um serviço, o ponto de extensão já existe e nenhuma Action precisa ser
 * reescrita.
 */
final class PacienteCadastrado
{
    use Dispatchable;

    public function __construct(public readonly Paciente $paciente) {}
}

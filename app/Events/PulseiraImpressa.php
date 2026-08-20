<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PulseiraImpressao;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitido mesmo sem ouvinte (doc §7.7). O ponto de extensão natural aqui é a impressora
 * de pulseiras como serviço externo — o ator secundário da doc §2.1.
 */
final class PulseiraImpressa
{
    use Dispatchable;

    public function __construct(public readonly PulseiraImpressao $impressao) {}
}

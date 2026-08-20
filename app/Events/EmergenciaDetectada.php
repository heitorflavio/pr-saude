<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Atendimento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RN-11: classificação VERMELHO exige atendimento imediato.
 *
 * O paciente não entra em fila — vai direto para `EM_ATENDIMENTO`. Este evento é o
 * gancho para a notificação do plantão: quando o painel deixar de ser polling, é ele que
 * o alerta sonoro vai escutar (doc §7.7).
 */
final class EmergenciaDetectada
{
    use Dispatchable;

    public function __construct(public readonly Atendimento $atendimento) {}
}

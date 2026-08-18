<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prescricao;
use App\Models\User;

final class PrescricaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prescricao.ler');
    }

    public function view(User $user, Prescricao $prescricao): bool
    {
        return $user->can('prescricao.ler');
    }

    /** Prescrever é ato privativo médico (doc §2.3, linha "Prescrição de medicamento"). */
    public function create(User $user): bool
    {
        return $user->can('prescricao.criar');
    }

    /**
     * Suspender prescrição alheia é permitido a médico -- em plantão, o prescritor
     * original pode não estar presente, e uma prescrição que precisa parar não espera.
     * Fica registrado quem suspendeu e por quê (`suspensa_por`, `motivo_suspensao`).
     */
    public function suspender(User $user, Prescricao $prescricao): bool
    {
        return $user->can('prescricao.atualizar');
    }

    public function update(User $user, Prescricao $prescricao): bool
    {
        return $user->can('prescricao.atualizar');
    }
}

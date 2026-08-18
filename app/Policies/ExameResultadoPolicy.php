<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExameResultado;
use App\Models\User;

final class ExameResultadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exame.ler_resultado');
    }

    public function view(User $user, ExameResultado $resultado): bool
    {
        return $user->can('exame.ler_resultado');
    }

    /** Registrar laudo é do laboratório (doc §2.3, "Execução / laudo de exame"). */
    public function create(User $user): bool
    {
        return $user->can('exame.executar');
    }

    public function update(User $user, ExameResultado $resultado): bool
    {
        return $user->can('exame.atualizar_resultado');
    }

    /**
     * RN-24: liberar o resultado ao paciente é ato explícito e identificado -- o CHECK
     * `ck_result_liberacao` recusa `visivel_ao_paciente = TRUE` sem `liberado_por`.
     *
     * RN-25: valor crítico bloqueia a liberação antes da ciência médica. Um potássio de
     * 7,2 chegando ao celular do paciente antes de o médico ver não é transparência, é
     * dano -- e a decisão do que fazer com ele é clínica.
     */
    public function liberar(User $user, ExameResultado $resultado): bool
    {
        if (! $user->can('exame.liberar_resultado')) {
            return false;
        }

        if ($resultado->possui_valor_critico) {
            // Só quem pode conduzir clinicamente o valor crítico libera.
            return $user->can('prontuario.criar_nota_medica');
        }

        return true;
    }
}

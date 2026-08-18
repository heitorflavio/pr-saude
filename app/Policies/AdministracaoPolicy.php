<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdministracaoMedicamento;
use App\Models\Profissional;
use App\Models\User;

final class AdministracaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('medicamento.ler_administracao');
    }

    public function view(User $user, AdministracaoMedicamento $administracao): bool
    {
        return $user->can('medicamento.ler_administracao');
    }

    /** Administrar é ato de enfermagem (doc §2.3): enfermeiro ou técnico. */
    public function create(User $user): bool
    {
        return $user->can('medicamento.administrar');
    }

    /**
     * RN-22: medicamento de alta vigilância exige um SEGUNDO profissional como
     * conferente, e ele precisa ser distinto do executor.
     *
     * A regra "distinto do executor" é o coração da dupla checagem: se a mesma pessoa
     * pudesse conferir a própria dose, o controle não existiria -- seria só mais um
     * clique. O banco garante que o conferente é um profissional válido; esta Policy
     * garante que ele não é quem está administrando.
     */
    public function conferir(User $user, Profissional $executor): bool
    {
        if (! $user->can('medicamento.administrar')) {
            return false;
        }

        return $user->profissional?->user_id !== null
            && $user->profissional->user_id !== $executor->user_id;
    }

    /**
     * RN-21: sobrepor um alerta de alergia é permitido, mas só com justificativa
     * registrada -- e o CHECK `ck_adm_justificativa` recusa a escrita sem ela.
     *
     * Note a assimetria com RN-23, que apenas SINALIZA divergência de dose. Bloquear
     * tudo produziria fadiga de alerta, e um profissional que clica "ok" em todo aviso
     * é um profissional sem nenhum aviso.
     */
    public function sobreporAlertaAlergia(User $user): bool
    {
        return $user->can('medicamento.administrar');
    }
}

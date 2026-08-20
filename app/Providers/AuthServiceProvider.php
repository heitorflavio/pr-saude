<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdministracaoMedicamento;
use App\Models\Atendimento;
use App\Models\ExameResultado;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\RegistroClinico;
use App\Models\User;
use App\Policies\AdministracaoPolicy;
use App\Policies\AtendimentoPolicy;
use App\Policies\ExameResultadoPolicy;
use App\Policies\PacientePolicy;
use App\Policies\PrescricaoPolicy;
use App\Policies\RegistroClinicoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Registradas explicitamente, não por descoberta automática: `AdministracaoPolicy`
     * governa `AdministracaoMedicamento`, e a convenção do Laravel procuraria por
     * `AdministracaoMedicamentoPolicy`. Listar as seis torna o mapa auditável.
     *
     * @var array<class-string, class-string>
     */
    private array $policies = [
        Paciente::class => PacientePolicy::class,
        Atendimento::class => AtendimentoPolicy::class,
        RegistroClinico::class => RegistroClinicoPolicy::class,
        Prescricao::class => PrescricaoPolicy::class,
        AdministracaoMedicamento::class => AdministracaoPolicy::class,
        ExameResultado::class => ExameResultadoPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        /*
         * O tipo ADMIN é o superadministrador do sistema. O atalho é baseado no tipo da
         * conta, e não na role, para que um erro de sincronização do RBAC não deixe uma
         * conta administrativa sem acesso à funcionalidade necessária para repará-lo.
         * As invariantes do domínio e do banco continuam valendo: autorização irrestrita
         * não torna válidas transições, dados ou operações inexistentes.
         */
        Gate::before(function (User $user, string $ability) {
            return $user->ehAdmin() ? true : null;
        });
    }
}

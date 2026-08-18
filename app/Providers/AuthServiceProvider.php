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

    /**
     * Abilities que o `admin` NÃO recebe pelo atalho do Gate::before.
     *
     * Cobre os dois nomes pelos quais a quebra de sigilo é consultada: a permission do
     * spatie (`prontuario.quebra_sigilo`) e o método da Policy (`quebrarSigilo`) --
     * porque `Gate::before` recebe o nome do método quando a checagem passa por Policy.
     *
     * @var array<int, string>
     */
    private const SEM_ATALHO_PARA_ADMIN = [
        'prontuario.quebra_sigilo',
        'quebrarSigilo',
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        /*
         * O admin passa por cima de tudo -- menos da quebra de sigilo.
         *
         * A exceção é o ponto inteiro do controle: RN-28 exige justificativa registrada
         * para acesso a paciente sem vínculo assistencial. Se o administrador tivesse
         * atalho aqui, bastaria uma conta de admin para tornar o controle decorativo,
         * e "quem acessou os dados deste paciente?" passaria a ter resposta incompleta
         * exatamente para o perfil com mais alcance.
         *
         * Retornar `null` (e não `false`) deixa a checagem seguir para a Policy: quem
         * não é admin, e o próprio admin no caso excluído, são avaliados normalmente.
         */
        Gate::before(function (User $user, string $ability) {
            if (in_array($ability, self::SEM_ATALHO_PARA_ADMIN, strict: true)) {
                return null;
            }

            return $user->ehAdmin() ? true : null;
        });
    }
}

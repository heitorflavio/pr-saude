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
     * Abilities ADMINISTRATIVAS: as únicas para as quais o `admin` tem atalho.
     *
     * O prompt §5 pedia um `Gate::before` que liberasse o admin para tudo, exceto quebra
     * de sigilo. Isso contrariava a matriz da doc §2.3, que dá ao administrador apenas
     * **R** nas linhas clínicas -- prontuário, prescrição, administração de medicamento.
     * A intenção do documento é clara: administrador configura o sistema, não pratica
     * medicina. Um admin de TI capaz de assinar evolução médica em nome próprio é um
     * risco de integridade do prontuário que nenhuma auditoria posterior desfaz.
     *
     * O atalho ficou restrito ao domínio administrativo (DECISOES.md D-20). Tudo o mais
     * cai na verificação normal -- e lá o admin tem exatamente o que a matriz semeou,
     * nem mais nem menos.
     *
     * @var array<int, string>
     */
    private const PREFIXOS_ADMINISTRATIVOS = [
        'usuario.',
        'catalogo_',
        'auditoria.',
        'paciente.',
    ];

    /**
     * Abilities de Policy liberadas ao admin.
     *
     * `verContexto` está aqui porque a doc §13.5 é explícita: o administrador não tem
     * vínculo assistencial com ninguém, e sem isto não conseguiria abrir a ficha
     * cadastral de nenhum paciente -- que é trabalho administrativo legítimo.
     *
     * @var array<int, string>
     */
    private const POLICIES_ADMINISTRATIVAS = [
        'verContexto',
    ];

    /**
     * Nunca liberadas pelo atalho, em nenhuma hipótese.
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
         * O atalho do admin, restrito ao domínio administrativo.
         *
         * `prontuario.quebra_sigilo` fica de fora mesmo dentro do prefixo administrativo:
         * é o ponto inteiro do controle da RN-28. Se o administrador tivesse atalho ali,
         * bastaria uma conta de admin para tornar o controle decorativo, e "quem acessou
         * os dados deste paciente?" teria resposta incompleta exatamente para o perfil
         * com mais alcance.
         *
         * Retornar `null` (e não `false`) deixa a checagem seguir seu curso normal: quem
         * não é admin, e o admin fora do domínio administrativo, são avaliados pela
         * permission semeada e pela Policy -- como qualquer outro.
         */
        Gate::before(function (User $user, string $ability) {
            if (! $user->ehAdmin()) {
                return null;
            }

            if (in_array($ability, self::SEM_ATALHO_PARA_ADMIN, strict: true)) {
                return null;
            }

            if (in_array($ability, self::POLICIES_ADMINISTRATIVAS, strict: true)) {
                return true;
            }

            foreach (self::PREFIXOS_ADMINISTRATIVOS as $prefixo) {
                if (str_starts_with($ability, $prefixo)) {
                    return true;
                }
            }

            return null;
        });
    }
}

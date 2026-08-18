<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A matriz RBAC da doc §2.3, percorrida célula por célula.
 *
 * Os testes NEGATIVOS são os que importam. Um teste que só confirma que o médico pode
 * prescrever não prova nada sobre segurança: o que prova é que a recepcionista não pode,
 * e que o técnico de enfermagem não pode. Por isso cada role é verificada contra a
 * matriz INTEIRA -- o que ela tem e, principalmente, tudo o que ela não tem.
 */
beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->matriz = (new RbacSeeder)->matriz();
    $this->todasPermissoes = array_keys($this->matriz);
});

/** Usuário da equipe com a role indicada. */
function usuarioCom(string $role): User
{
    $user = User::factory()->profissional()->create();
    $user->assignRole($role);

    return $user->fresh();
}

// =====================================================================
// A matriz inteira, role por role
// =====================================================================

it('concede a cada role exatamente as permissoes da matriz da doc 2.3, e nenhuma outra', function (string $role) {
    $user = usuarioCom($role);

    $esperadas = array_keys(array_filter(
        $this->matriz,
        fn (array $roles) => in_array($role, $roles, strict: true)
    ));

    $negadas = array_values(array_diff($this->todasPermissoes, $esperadas));

    expect($esperadas)->not->toBeEmpty("A role {$role} nao tem nenhuma permissao na matriz.");

    foreach ($esperadas as $permissao) {
        expect($user->hasPermissionTo($permissao, 'web'))
            ->toBeTrue("A role {$role} deveria ter {$permissao}.");
    }

    // O que importa: tudo o que a role NAO pode.
    foreach ($negadas as $permissao) {
        expect($user->hasPermissionTo($permissao, 'web'))
            ->toBeFalse("A role {$role} NAO deveria ter {$permissao}.");
    }
})->with([
    'recepcao',
    'enfermeiro_triagem',
    'enfermeiro_assistencial',
    'tecnico_enfermagem',
    'medico',
    'laboratorio',
    'admin',
    'auditor',
]);

// =====================================================================
// Negativas nomeadas -- as que doem se falharem
// =====================================================================

it('nega a recepcao qualquer escrita clinica', function () {
    $recepcao = usuarioCom('recepcao');

    expect($recepcao->hasPermissionTo('prescricao.criar', 'web'))->toBeFalse()
        ->and($recepcao->hasPermissionTo('medicamento.administrar', 'web'))->toBeFalse()
        ->and($recepcao->hasPermissionTo('triagem.classificar', 'web'))->toBeFalse()
        ->and($recepcao->hasPermissionTo('prontuario.criar_nota_medica', 'web'))->toBeFalse()
        ->and($recepcao->hasPermissionTo('exame.liberar_resultado', 'web'))->toBeFalse();
});

it('nega ao tecnico de enfermagem prescrever e escrever nota medica', function () {
    $tecnico = usuarioCom('tecnico_enfermagem');

    // Administrar ele pode; prescrever e ato privativo medico.
    expect($tecnico->hasPermissionTo('medicamento.administrar', 'web'))->toBeTrue()
        ->and($tecnico->hasPermissionTo('prescricao.criar', 'web'))->toBeFalse()
        ->and($tecnico->hasPermissionTo('prontuario.criar_nota_medica', 'web'))->toBeFalse()
        ->and($tecnico->hasPermissionTo('prontuario.retificar', 'web'))->toBeFalse();
});

it('nega ao laboratorio acessar prontuario e prescricao', function () {
    $lab = usuarioCom('laboratorio');

    expect($lab->hasPermissionTo('exame.executar', 'web'))->toBeTrue()
        ->and($lab->hasPermissionTo('prontuario.ler_nota_medica', 'web'))->toBeFalse()
        ->and($lab->hasPermissionTo('prescricao.ler', 'web'))->toBeFalse()
        ->and($lab->hasPermissionTo('fila.ler', 'web'))->toBeFalse();
});

it('da ao auditor a trilha de auditoria e mais nada', function () {
    $auditor = usuarioCom('auditor');

    expect($auditor->hasPermissionTo('auditoria.ler', 'web'))->toBeTrue();

    foreach (array_diff($this->todasPermissoes, ['auditoria.ler']) as $permissao) {
        expect($auditor->hasPermissionTo($permissao, 'web'))
            ->toBeFalse("O auditor NAO deveria ter {$permissao} -- ele consulta a trilha, nao edita dado clinico.");
    }
});

it('nega ao medico gerenciar usuarios e ler a auditoria', function () {
    $medico = usuarioCom('medico');

    expect($medico->hasPermissionTo('usuario.gerenciar', 'web'))->toBeFalse()
        ->and($medico->hasPermissionTo('auditoria.ler', 'web'))->toBeFalse()
        ->and($medico->hasPermissionTo('catalogo_medicamento.excluir', 'web'))->toBeFalse();
});

// =====================================================================
// RN-27 -- o paciente nao executa nenhuma escrita
// =====================================================================

it('nao cria nenhuma role chamada paciente', function () {
    // RN-27, primeira camada: o guard `paciente` fica sem role e sem permission, de
    // proposito. As celulas "R (so o seu)" da doc 2.3 sao garantidas por ausencia de
    // rota de escrita e por global scope, nao por permissao.
    expect(Role::where('name', 'paciente')->exists())->toBeFalse();
});

it('nega qualquer permissao a um usuario do tipo paciente', function () {
    $paciente = User::factory()->paciente()->create();

    expect($paciente->getAllPermissions())->toBeEmpty()
        ->and($paciente->getRoleNames())->toBeEmpty();

    foreach ($this->todasPermissoes as $permissao) {
        expect($paciente->can($permissao))
            ->toBeFalse("Paciente NAO pode {$permissao}.");
    }
});

it('devolve false em qualquer can() para usuario autenticado no guard paciente', function () {
    $paciente = Paciente::factory()->create();
    $usuario = $paciente->user;

    Auth::guard('paciente')->login($usuario);

    expect(Auth::guard('paciente')->check())->toBeTrue()
        // A sessao da equipe permanece vazia: guards separados dao isolamento de sessao.
        ->and(Auth::guard('web')->check())->toBeFalse();

    $autenticado = Auth::guard('paciente')->user();

    foreach ($this->todasPermissoes as $permissao) {
        expect($autenticado->can($permissao))
            ->toBeFalse("Guard paciente NAO pode {$permissao}.");
    }
});

// =====================================================================
// Gate::before -- o admin e a unica excecao dele
// =====================================================================

it('libera o admin para qualquer permissao pelo Gate::before', function () {
    $admin = User::factory()->admin()->create();

    foreach (array_diff($this->todasPermissoes, ['prontuario.quebra_sigilo']) as $permissao) {
        expect($admin->can($permissao))->toBeTrue("Admin deveria poder {$permissao}.");
    }
});

it('nao libera o admin para quebra de sigilo, nem pela permission nem pela policy', function () {
    // A excecao e o ponto inteiro do controle: se o administrador tivesse atalho aqui,
    // bastaria uma conta de admin para tornar a RN-28 decorativa.
    $admin = User::factory()->admin()->create();
    $paciente = Paciente::factory()->create();

    expect($admin->can('prontuario.quebra_sigilo'))->toBeFalse()
        ->and($admin->can('quebrarSigilo', $paciente))->toBeFalse();
});

it('nao concede quebra de sigilo ao admin na matriz semeada', function () {
    $admin = usuarioCom('admin');

    expect($admin->hasPermissionTo('prontuario.quebra_sigilo', 'web'))->toBeFalse();
});

it('nao aplica o atalho do admin a um usuario que nao e admin', function () {
    $recepcao = usuarioCom('recepcao');

    expect($recepcao->can('usuario.gerenciar'))->toBeFalse()
        ->and($recepcao->can('prescricao.criar'))->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// =====================================================================
// RNF-07 -- Argon2id
// =====================================================================

it('usa Argon2id como driver de hash', function () {
    expect(config('hashing.driver'))->toBe('argon2id');
});

it('gera hash de senha no formato Argon2id', function () {
    $hash = Hash::make('senha-de-teste');

    // O prefixo do algoritmo esta no proprio hash: se algum dia alguem trocar o driver
    // silenciosamente para bcrypt, este teste falha em vez de o sistema degradar em
    // silencio.
    expect($hash)->toStartWith('$argon2id$')
        ->and(Hash::check('senha-de-teste', $hash))->toBeTrue()
        ->and(Hash::check('outra-senha', $hash))->toBeFalse();
});

it('grava a senha do usuario com Argon2id', function () {
    $user = User::factory()->create(['password' => 'senha-de-teste']);

    expect($user->fresh()->password)->toStartWith('$argon2id$');
});

// =====================================================================
// Dois guards, sessoes isoladas (doc 13.4)
// =====================================================================

it('define os guards web e paciente sobre o mesmo model', function () {
    expect(config('auth.guards.web.provider'))->toBe('profissionais')
        ->and(config('auth.guards.paciente.provider'))->toBe('pacientes')
        ->and(config('auth.providers.profissionais.model'))->toBe(User::class)
        ->and(config('auth.providers.pacientes.model'))->toBe(User::class);
});

// =====================================================================
// RN-06 -- senha provisoria
// =====================================================================

it('redireciona quem tem senha provisoria para a troca de senha', function () {
    $user = User::factory()->profissional()->comSenhaProvisoria()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('senha.provisoria'));
});

it('permite alcancar a propria tela de troca de senha e o logout', function () {
    $user = User::factory()->profissional()->comSenhaProvisoria()->create();

    $this->actingAs($user)->get(route('senha.provisoria'))->assertOk();
});

it('libera o sistema depois da troca da senha provisoria', function () {
    $user = User::factory()->profissional()->comSenhaProvisoria()->create();

    $this->actingAs($user)
        ->put(route('senha.provisoria.atualizar'), [
            'senha' => 'uma-senha-nova-forte',
            'senha_confirmation' => 'uma-senha-nova-forte',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->senha_provisoria)->toBeFalse()
        ->and($user->senha_alterada_em)->not->toBeNull()
        ->and(Hash::check('uma-senha-nova-forte', $user->password))->toBeTrue();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('recusa trocar a senha provisoria pela mesma senha', function () {
    // Permitir "trocar" para a mesma senha tornaria a RN-06 decorativa: a credencial
    // fraca continuaria vigente com a flag limpa.
    $user = User::factory()->profissional()->comSenhaProvisoria()->create([
        'password' => 'senha-provisoria',
    ]);

    $this->actingAs($user)
        ->put(route('senha.provisoria.atualizar'), [
            'senha' => 'senha-provisoria',
            'senha_confirmation' => 'senha-provisoria',
        ])
        ->assertSessionHasErrors('senha');

    expect($user->fresh()->senha_provisoria)->toBeTrue();
});

it('recusa senha com menos de 8 caracteres', function () {
    $user = User::factory()->profissional()->comSenhaProvisoria()->create();

    $this->actingAs($user)
        ->put(route('senha.provisoria.atualizar'), [
            'senha' => 'curta',
            'senha_confirmation' => 'curta',
        ])
        ->assertSessionHasErrors('senha');

    expect($user->fresh()->senha_provisoria)->toBeTrue();
});

// =====================================================================
// RNF-09 / RNF-10 -- expiracao de sessao por guard
// =====================================================================

it('expira a sessao da equipe depois de 30 minutos de inatividade', function () {
    $user = User::factory()->profissional()->create();

    // Primeira requisicao carimba a ultima atividade.
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $this->travel(31)->minutes();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('mantem a sessao da equipe viva dentro dos 30 minutos', function () {
    $user = User::factory()->profissional()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $this->travel(20)->minutes();

    $this->get('/dashboard')->assertOk();
    $this->assertAuthenticated();
});

// =====================================================================
// D-18 -- o starter kit nao cria nem apaga conta por autoatendimento
// =====================================================================

it('nao registra a rota publica de cadastro', function () {
    // Num SGH nao existe autocadastro: usuarios sao criados pela recepcao (pacientes,
    // RN-04) ou pelo administrador (equipe, permissao usuario.gerenciar).
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Invasor',
        'email' => 'invasor@exemplo.test',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ])->assertNotFound();

    expect(User::where('email', 'invasor@exemplo.test')->exists())->toBeFalse();
});

// =====================================================================
// D-14 fechada -- login e tipo passaram a ser obrigatorios
// =====================================================================

it('recusa criar usuario sem login ou sem tipo', function () {
    expect(fn () => User::factory()->create(['login' => null]))
        ->toThrow(QueryException::class);

    expect(fn () => User::factory()->create(['tipo' => null]))
        ->toThrow(QueryException::class);
});

it('mantem o login unico entre usuarios', function () {
    User::factory()->create(['login' => '12345678901']);

    expect(fn () => User::factory()->create(['login' => '12345678901']))
        ->toThrow(QueryException::class);
});

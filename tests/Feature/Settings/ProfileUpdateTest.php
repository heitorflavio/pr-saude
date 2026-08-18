<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('nao existe rota de auto-exclusao de conta', function () {
    // D-18: num SGH, conta de paciente ou de profissional nao se apaga por
    // autoatendimento. Um usuario removido deixaria registro clinico orfao de autor, e
    // a trilha de auditoria precisa continuar podendo dizer quem agiu (D-08).
    // Desativacao e ato administrativo, sob a permissao usuario.gerenciar.
    $user = User::factory()->create();

    // 405, nao 404: a URI continua existindo para GET e PATCH -- o que deixou de
    // existir e o verbo DELETE. E a prova mais precisa de que a rota foi removida.
    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
    $this->assertNotSoftDeleted('users', ['id' => $user->id]);
});

test('a rota profile.destroy nao esta registrada', function () {
    expect(Route::has('profile.destroy'))->toBeFalse();
});

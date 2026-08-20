<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;
use App\Events\AcessoPortalRealizado;
use App\Models\Atendimento;
use App\Models\AuditoriaLog;
use App\Models\ExameResultado;
use App\Models\ExameSolicitacao;
use App\Models\Paciente;
use App\Models\RegistroClinico;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->cpf = '52998224725';
    $this->nascimento = '1985-03-14';
    $this->senhaInicial = '14031985';
    $this->usuarioPaciente = User::factory()->paciente()->create([
        'login' => $this->cpf,
        'password' => $this->senhaInicial,
        'senha_provisoria' => true,
        'created_at' => now(),
    ]);
    $this->pacientePortal = Paciente::factory()->create([
        'user_id' => $this->usuarioPaciente->id,
        'cpf' => $this->cpf,
        'data_nascimento' => $this->nascimento,
        'nome_completo' => 'Maria do Portal',
    ]);
    $this->atendimentoPortal = Atendimento::factory()->create([
        'paciente_id' => $this->pacientePortal->user_id,
        'unidade_id' => Unidade::factory(),
        'status' => StatusAtendimento::AguardandoAtendimento,
    ]);
});

function lerPulseiraEEntrar(object $teste, ?string $senha = null)
{
    $teste->get(route('pulseira.resolver', $teste->pacientePortal->token_pulseira))
        ->assertRedirect(route('portal.login'));

    return $teste->post(route('portal.autenticar'), [
        'cpf' => $teste->cpf,
        'senha' => $senha ?? $teste->senhaInicial,
    ]);
}

it('primeiro acesso exige token válido da pulseira na sessão', function () {
    $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => $this->senhaInicial])
        ->assertSessionHasErrors(['cpf' => 'No primeiro acesso, escaneie o QR Code da sua pulseira.']);

    lerPulseiraEEntrar($this)->assertRedirect(route('portal.senha'));
    $this->assertAuthenticatedAs($this->usuarioPaciente, 'paciente');
});

it('o fator de posse sobrevive ao GET do formulário até o POST de login', function () {
    $this->get(route('pulseira.resolver', $this->pacientePortal->token_pulseira));
    $this->get(route('portal.login'))->assertOk();

    $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => $this->senhaInicial])
        ->assertRedirect(route('portal.senha'));
});

it('CPF inexistente e senha errada devolvem mensagem idêntica', function () {
    $inexistente = $this->post(route('portal.autenticar'), ['cpf' => '11144477735', 'senha' => 'qualquer']);
    $errada = $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'senha-errada']);

    $inexistente->assertSessionHasErrors(['cpf' => 'Credenciais inválidas.']);
    $errada->assertSessionHasErrors(['cpf' => 'Credenciais inválidas.']);
});

it('bloqueia progressivamente a conta após três falhas', function () {
    foreach (range(1, 3) as $_) {
        $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'errada']);
    }

    expect($this->usuarioPaciente->fresh()->tentativas_falhas)->toBe(3)
        ->and($this->usuarioPaciente->fresh()->bloqueado_ate)->not->toBeNull()
        ->and($this->usuarioPaciente->fresh()->bloqueado_ate->isFuture())->toBeTrue();
});

it('audita toda tentativa e mascara o CPF', function () {
    $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'errada']);
    $log = AuditoriaLog::where('acao', 'portal.login_falha')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->dados_depois['cpf'])->toBe('[REMOVIDO]');
});

it('senha inicial expira após 72 horas', function () {
    $this->usuarioPaciente->forceFill(['created_at' => now()->subHours(73)])->save();

    lerPulseiraEEntrar($this)
        ->assertSessionHasErrors(['cpf' => 'Senha inicial expirada. Solicite uma nova na recepção.']);
    $this->assertGuest('paciente');
});

it('troca obrigatória recusa CPF, nascimento, senha comum e menos de oito caracteres', function (string $senha) {
    $this->actingAs($this->usuarioPaciente, 'paciente')
        ->post(route('portal.senha.atualizar'), ['password' => $senha, 'password_confirmation' => $senha])
        ->assertSessionHasErrors('password');
})->with(['52998224725', '14031985', '12345678', 'curta']);

it('troca a senha provisória e remove o fator de posse da sessão', function () {
    $response = $this->withSession(['portal.pulseira_token' => $this->pacientePortal->token_pulseira])
        ->actingAs($this->usuarioPaciente, 'paciente')
        ->post(route('portal.senha.atualizar'), [
            'password' => 'NovaSenha#2026', 'password_confirmation' => 'NovaSenha#2026',
        ]);

    $response->assertRedirect(route('portal.acompanhamento'))
        ->assertSessionMissing('portal.pulseira_token');

    $usuario = $this->usuarioPaciente->fresh();
    expect($usuario->senha_provisoria)->toBeFalse()
        ->and(Hash::check('NovaSenha#2026', $usuario->password))->toBeTrue();
});

it('emite evento para notificação de acesso bem-sucedido', function () {
    Event::fake([AcessoPortalRealizado::class]);
    lerPulseiraEEntrar($this);
    Event::assertDispatched(AcessoPortalRealizado::class);
});

it('acesso definitivo vale durante atendimento e por trinta dias após alta', function () {
    $this->usuarioPaciente->update(['senha_provisoria' => false, 'password' => 'NovaSenha#2026']);
    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')->get(route('portal.acompanhamento'))->assertOk();

    $this->atendimentoPortal->update([
        'status' => StatusAtendimento::Finalizado, 'desfecho' => 'ALTA', 'finalizado_em' => now()->subDays(29),
    ]);
    expect($this->pacientePortal->possuiAcessoVigente())->toBeTrue();

    $this->atendimentoPortal->update(['finalizado_em' => now()->subDays(31)]);
    expect($this->pacientePortal->possuiAcessoVigente())->toBeFalse();
});

it('paciente A recebe 404 ao tentar UUID do atendimento do paciente B', function () {
    $outro = Paciente::factory()->comAtendimentoAtivo()->create();
    $atendimentoOutro = $outro->atendimentos()->first();
    $this->usuarioPaciente->update(['senha_provisoria' => false]);

    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')
        ->get(route('portal.atendimento', $atendimentoOutro->uuid))
        ->assertNotFound();
});

it('global scope omite registros clínicos de outro paciente mesmo sem where no controller', function () {
    RegistroClinico::factory()->create(['atendimento_id' => $this->atendimentoPortal->id]);
    $outro = Atendimento::factory()->create(['paciente_id' => Paciente::factory()->create()->user_id]);
    RegistroClinico::factory()->create(['atendimento_id' => $outro->id]);

    $this->actingAs($this->usuarioPaciente, 'paciente');
    expect(RegistroClinico::query()->count())->toBe(1);
});

it('registro sigiloso é omitido sem indicar que existe', function () {
    RegistroClinico::factory()->create(['atendimento_id' => $this->atendimentoPortal->id, 'sigiloso' => true]);
    $this->usuarioPaciente->update(['senha_provisoria' => false]);

    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')
        ->get(route('portal.atendimento', $this->atendimentoPortal->uuid))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Portal/Atendimento')->has('evolucao', 0));
});

it('resultado concluído não liberado aparece em análise sem conteúdo', function () {
    $solicitacao = ExameSolicitacao::factory()->create([
        'atendimento_id' => $this->atendimentoPortal->id, 'situacao' => 'CONCLUIDO',
    ]);
    ExameResultado::factory()->create([
        'exame_solicitacao_id' => $solicitacao->id, 'visivel_ao_paciente' => false,
    ]);
    $this->usuarioPaciente->update(['senha_provisoria' => false]);

    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')
        ->get(route('portal.exames'))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('exames.0.situacao', 'Exame realizado — resultado em análise médica')
        ->where('exames.0.resultado', null));
});

it('as únicas escritas sob auth paciente são senha e logout', function () {
    $rotas = collect(Route::getRoutes())->filter(fn ($rota) => in_array('auth:paciente', $rota->gatherMiddleware(), true));
    expect($rotas)->not->toBeEmpty();

    $escritas = $rotas->filter(fn ($rota) => array_intersect($rota->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
        ->map(fn ($rota) => [$rota->uri(), array_values(array_intersect($rota->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))])
        ->values()->all();

    expect($escritas)->toBe([
        ['portal/senha', ['POST']],
        ['portal/sair', ['POST']],
    ]);
});

it('o guard paciente continua sem qualquer permission', function () {
    $this->actingAs($this->usuarioPaciente, 'paciente');
    expect($this->usuarioPaciente->can('prescricao.criar'))->toBeFalse()
        ->and($this->usuarioPaciente->can('exame.solicitar'))->toBeFalse();
});

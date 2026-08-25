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

function entrarNoPortal(object $teste, ?string $senha = null)
{
    return $teste->post(route('portal.autenticar'), [
        'cpf' => $teste->cpf,
        'senha' => $senha ?? $teste->senhaInicial,
    ]);
}

// D-61: o primeiro acesso não exige mais a posse da pulseira (M-3 removida).
it('primeiro acesso entra só com CPF e senha inicial e cai na troca de senha', function () {
    entrarNoPortal($this)->assertRedirect(route('portal.senha'));
    $this->assertAuthenticatedAs($this->usuarioPaciente, 'paciente');
});

it('a leitura da pulseira sem sessão redireciona ao login sem guardar fator de posse', function () {
    $this->get(route('pulseira.resolver', $this->pacientePortal->token_pulseira))
        ->assertRedirect(route('portal.login'))
        ->assertSessionMissing('portal.pulseira_token');

    $this->get(route('portal.login'))->assertOk();
});

it('CPF inexistente e senha errada devolvem mensagem idêntica', function () {
    $inexistente = $this->post(route('portal.autenticar'), ['cpf' => '11144477735', 'senha' => 'qualquer']);
    $errada = $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'senha-errada']);

    $inexistente->assertSessionHasErrors(['cpf' => 'Credenciais inválidas.']);
    $errada->assertSessionHasErrors(['cpf' => 'Credenciais inválidas.']);
});

// D-62: a conta não é mais trancada por tentativas falhas (M-4 removida).
it('falhas sucessivas não bloqueiam a conta e a senha correta continua entrando', function () {
    foreach (range(1, 5) as $_) {
        $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'errada']);
    }

    $usuario = $this->usuarioPaciente->fresh();
    expect($usuario->tentativas_falhas)->toBe(0)
        ->and($usuario->bloqueado_ate)->toBeNull();

    entrarNoPortal($this)->assertRedirect(route('portal.senha'));
    $this->assertAuthenticatedAs($this->usuarioPaciente, 'paciente');
});

// M-5 é o que restou contendo força bruta: o limite continua por origem, não por conta.
it('o limite por IP ainda barra varredura depois de trinta tentativas', function () {
    foreach (range(1, 30) as $_) {
        $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'errada']);
    }

    $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => $this->senhaInicial])
        ->assertSessionHasErrors(['cpf' => 'Muitas tentativas. Aguarde e tente novamente ou procure a recepção.']);
    $this->assertGuest('paciente');
});

it('audita toda tentativa e mascara o CPF', function () {
    $this->post(route('portal.autenticar'), ['cpf' => $this->cpf, 'senha' => 'errada']);
    $log = AuditoriaLog::where('acao', 'portal.login_falha')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->dados_depois['cpf'])->toBe('[REMOVIDO]');
});

it('senha inicial expira após 72 horas', function () {
    $this->usuarioPaciente->forceFill(['created_at' => now()->subHours(73)])->save();

    entrarNoPortal($this)
        ->assertSessionHasErrors(['cpf' => 'Senha inicial expirada. Solicite uma nova na recepção.']);
    $this->assertGuest('paciente');
});

it('troca obrigatória recusa CPF, nascimento, senha comum e menos de oito caracteres', function (string $senha) {
    $this->actingAs($this->usuarioPaciente, 'paciente')
        ->post(route('portal.senha.atualizar'), ['password' => $senha, 'password_confirmation' => $senha])
        ->assertSessionHasErrors('password');
})->with(['52998224725', '14031985', '12345678', 'curta']);

it('troca a senha provisória e renova a sessão', function () {
    $response = $this->actingAs($this->usuarioPaciente, 'paciente')
        ->post(route('portal.senha.atualizar'), [
            'password' => 'NovaSenha#2026', 'password_confirmation' => 'NovaSenha#2026',
        ]);

    $response->assertRedirect(route('portal.acompanhamento'));

    $usuario = $this->usuarioPaciente->fresh();
    expect($usuario->senha_provisoria)->toBeFalse()
        ->and(Hash::check('NovaSenha#2026', $usuario->password))->toBeTrue();
});

it('emite evento para notificação de acesso bem-sucedido', function () {
    Event::fake([AcessoPortalRealizado::class]);
    entrarNoPortal($this);
    Event::assertDispatched(AcessoPortalRealizado::class);
});

// D-63: o portal deixou de exigir atendimento. Estes três casos eram bloqueio antes.
it('paciente sem nenhum atendimento entra no portal e vê a tela vazia', function () {
    $semAtendimento = User::factory()->paciente()->create([
        'login' => '11144477735', 'password' => '01011990', 'senha_provisoria' => true,
    ]);
    Paciente::factory()->create([
        'user_id' => $semAtendimento->id, 'cpf' => '11144477735', 'data_nascimento' => '1990-01-01',
    ]);

    $this->post(route('portal.autenticar'), ['cpf' => '11144477735', 'senha' => '01011990'])
        ->assertRedirect(route('portal.senha'));
    $this->assertAuthenticatedAs($semAtendimento, 'paciente');

    $semAtendimento->update(['senha_provisoria' => false]);
    $this->actingAs($semAtendimento->fresh(), 'paciente')
        ->get(route('portal.acompanhamento'))
        ->assertOk()->assertInertia(fn ($page) => $page->where('atendimento', null));
});

it('acesso continua valendo muito depois da alta', function () {
    $this->usuarioPaciente->update(['senha_provisoria' => false, 'password' => 'NovaSenha#2026']);
    $this->atendimentoPortal->update([
        'status' => StatusAtendimento::Finalizado, 'desfecho' => 'ALTA', 'finalizado_em' => now()->subDays(400),
    ]);

    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')
        ->get(route('portal.acompanhamento'))->assertOk();
});

it('conta desativada é expulsa do portal na requisição seguinte', function () {
    $this->usuarioPaciente->update(['senha_provisoria' => false, 'password' => 'NovaSenha#2026']);
    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')->get(route('portal.acompanhamento'))->assertOk();

    $this->usuarioPaciente->update(['ativo' => false]);

    // `actingAs` fixa a instância em memória; a requisição real recarrega do provider,
    // então o teste precisa reautenticar com o registro atualizado para valer alguma coisa.
    $this->actingAs($this->usuarioPaciente->fresh(), 'paciente')
        ->get(route('portal.acompanhamento'))
        ->assertRedirect(route('portal.login'))
        ->assertSessionHasErrors(['cpf' => 'O seu acesso ao portal foi desativado. Procure a recepção.']);
    $this->assertGuest('paciente');
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

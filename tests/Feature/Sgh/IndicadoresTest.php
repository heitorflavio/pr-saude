<?php

declare(strict_types=1);

use App\Models\Atendimento;
use App\Models\AtendimentoStatusHistorico;
use App\Models\AuditoriaLog;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\Triagem;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Indicadores\IndicadoresService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('calcula os nove indicadores operacionais da doc 7.6', function () {
    $agora = CarbonImmutable::parse('2026-08-20 12:00:00');
    $this->travelTo($agora);
    $unidade = Unidade::factory()->create();
    $profissional = Profissional::factory()->medico()->create(['unidade_id' => $unidade->id]);
    $atendimento = Atendimento::factory()->finalizado('EVASAO')->create([
        'unidade_id' => $unidade->id,
        'profissional_responsavel_id' => $profissional->user_id,
        'classificacao_risco_id' => 4,
        'admitido_em' => $agora->subHours(2),
        'primeiro_atendimento_em' => $agora->subMinutes(90),
        'finalizado_em' => $agora,
    ]);
    $triagem = Triagem::factory()->create([
        'atendimento_id' => $atendimento->id,
        'classificacao_risco_id' => 4,
        'criado_em' => $agora->subMinutes(110),
    ]);
    Triagem::factory()->reclassificacaoDe($triagem, 3)->create(['criado_em' => $agora->subMinutes(100)]);
    FilaItem::factory()->create([
        'atendimento_id' => $atendimento->id,
        'profissional_id' => $profissional->user_id,
        'classificacao_risco_id' => 4,
        'situacao' => 'CONCLUIDO',
        'entrou_em' => $agora->subMinutes(110),
        'chamado_em' => $agora->subMinutes(90),
        'saiu_em' => $agora,
    ]);
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $profissional->user_id,
        'inicio_em' => $agora->subHours(4),
        'fim_em' => $agora,
    ]);
    AtendimentoStatusHistorico::factory()->create([
        'atendimento_id' => $atendimento->id,
        'status_anterior' => 'AGUARDANDO_TRIAGEM',
        'permanencia_segundos' => 600,
    ]);

    $resultado = app(IndicadoresService::class)->calcular($agora->startOfDay(), $agora->endOfDay(), $unidade->id);

    expect(collect($resultado)->except('periodo'))->toHaveCount(9)
        ->and($resultado['tempo_porta_triagem'])->toMatchArray(['minutos' => 10.0, 'amostra' => 1])
        ->and($resultado['tempo_porta_atendimento']['minutos'])->toBe(30.0)
        ->and($resultado['aderencia_tempo_alvo']['percentual'])->toBe(100.0)
        ->and($resultado['tempo_permanencia']['minutos'])->toBe(120.0)
        ->and($resultado['distribuicao_cor']->firstWhere('cor', 'VERDE')['total'])->toBe(1)
        ->and($resultado['taxa_reclassificacao']['percentual'])->toBe(100.0)
        ->and($resultado['taxa_evasao']['percentual'])->toBe(100.0)
        ->and($resultado['produtividade_profissional']->first()['por_hora'])->toBe(0.25)
        ->and($resultado['tempo_medio_status']->first()['minutos'])->toBe(10.0);
});

it('a tela de auditoria limita a consulta aos últimos 90 dias e ao paciente pedido', function () {
    $auditor = User::factory()->profissional()->create();
    $auditor->assignRole('auditor');
    $paciente = Paciente::factory()->create();
    $outro = Paciente::factory()->create();
    Auth::login($auditor->fresh());

    AuditoriaLog::factory()->create(['paciente_id' => $paciente->user_id, 'acao' => 'prontuario.ler', 'criado_em' => now()->subDay()]);
    AuditoriaLog::factory()->create(['paciente_id' => $paciente->user_id, 'acao' => 'antigo', 'criado_em' => now()->subDays(91)]);
    AuditoriaLog::factory()->create(['paciente_id' => $outro->user_id, 'acao' => 'outro.ler', 'criado_em' => now()]);

    $this->get(route('auditoria.index', ['paciente_id' => $paciente->user_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auditoria/Index')
            ->where('logs.total', 1)
            ->where('logs.data.0.acao', 'prontuario.ler')
            ->where('pacienteSelecionado.id', $paciente->user_id));
});

it('nega as telas gerenciais a quem não pode ler auditoria', function () {
    $medico = Profissional::factory()->medico()->create();
    $medico->user->assignRole('medico');

    $this->actingAs($medico->user->fresh())->get(route('auditoria.index'))->assertForbidden();
    $this->actingAs($medico->user->fresh())->get(route('indicadores.index'))->assertForbidden();
});

it('oferece quebra de sigilo, exige justificativa e audita o acesso excepcional', function () {
    $paciente = Paciente::factory()->create();
    $atendimento = Atendimento::factory()->create(['paciente_id' => $paciente->user_id]);
    $medico = Profissional::factory()->medico()->create(['unidade_id' => $atendimento->unidade_id]);
    $medico->user->assignRole('medico');
    ProfissionalDisponibilidade::factory()->create(['profissional_id' => $medico->user_id, 'fim_em' => null]);

    $this->actingAs($medico->user->fresh())
        ->get(route('prontuario.show', $atendimento))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Prontuario/QuebraSigilo'));

    $this->post(route('quebra-sigilo.store'), ['justificativa' => 'Paciente inconsciente em urgência.'])
        ->assertRedirect(route('prontuario.show', $atendimento));

    $this->get(route('prontuario.show', $atendimento))->assertOk();

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'prontuario.quebra_sigilo',
        'paciente_id' => $paciente->user_id,
        'justificativa' => 'Paciente inconsciente em urgência.',
    ]);
});

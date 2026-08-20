<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\Unidade;
use App\Services\Atendimento\PainelAtendimentoService;

/**
 * O episódio atravessando os módulos.
 *
 * Mudar a situação registrava a transição e não acontecia mais nada visível: prescrever e
 * solicitar exame são atos à parte, em telas que nenhum link alcançava. Estes testes
 * fixam a ligação — os cards de módulo, o aviso de pendência e o "Chamar" da fila.
 */
beforeEach(function () {
    $this->unidade = Unidade::factory()->create();

    $this->medico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $this->medico->user->assignRole('medico');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $this->medico->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);
    $this->autor = $this->medico->user->fresh();

    $this->paciente = Paciente::factory()->create();

    $this->atendimento = Atendimento::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'profissional_responsavel_id' => $this->medico->user_id,
        'status' => StatusAtendimento::EmAtendimento,
    ]);
});

it('a tela do atendimento entrega os modulos com o ato que alimenta cada um', function () {
    $this->actingAs($this->autor)
        ->get(route('atendimentos.show', $this->atendimento))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Atendimentos/Show')
            ->where('modulos.triagem.href', route('triagem.edit', $this->atendimento->id))
            ->where('modulos.prontuario.href', route('prontuario.show', $this->atendimento->id))
            ->has('modulos.fila')
            // Os dois que o usuario nao alcancava: as telas existiam sem link nenhum.
            ->where('modulos.medicamentos.href', route('medicamentos.show', $this->atendimento->id))
            ->where('modulos.medicamentos.acao', 'Prescrever')
            ->where('modulos.exames.href', route('exames.create', $this->atendimento->id))
            ->where('modulos.exames.acao', 'Solicitar exame')
        );
});

it('avisa que aguardando medicacao nao criou prescricao, e oferece o ato que falta', function () {
    $this->atendimento->update(['status' => StatusAtendimento::AguardandoMedicacao]);

    $painel = app(PainelAtendimentoService::class);
    $atendimento = $this->atendimento->fresh();
    $pendencia = $painel->pendencia($atendimento, $painel->modulos($atendimento, $this->autor));

    expect($pendencia)->not->toBeNull()
        ->and($pendencia['texto'])->toContain('não cria prescrição')
        ->and($pendencia['acao'])->toBe('Prescrever')
        ->and($pendencia['href'])->toBe(route('medicamentos.show', $atendimento->id));
});

it('avisa que a situacao de exame nao criou solicitacao', function () {
    $this->atendimento->update(['status' => StatusAtendimento::AguardandoExame]);

    $painel = app(PainelAtendimentoService::class);
    $atendimento = $this->atendimento->fresh();
    $pendencia = $painel->pendencia($atendimento, $painel->modulos($atendimento, $this->autor));

    expect($pendencia['acao'])->toBe('Solicitar exame')
        ->and($pendencia['href'])->toBe(route('exames.create', $atendimento->id));
});

it('nao avisa nada quando o atendimento esta encerrado', function () {
    $this->atendimento->update([
        'status' => StatusAtendimento::Finalizado,
        'desfecho' => 'ALTA',
        'finalizado_em' => now(),
    ]);

    $painel = app(PainelAtendimentoService::class);
    $atendimento = $this->atendimento->fresh();

    expect($painel->pendencia($atendimento, $painel->modulos($atendimento, $this->autor)))->toBeNull();
});

it('esconde o modulo que o usuario nao pode abrir', function () {
    $tecnico = Profissional::factory()->create([
        'unidade_id' => $this->unidade->id,
        'categoria' => 'TECNICO_ENFERMAGEM',
    ]);
    $tecnico->user->assignRole('tecnico_enfermagem');

    $modulos = app(PainelAtendimentoService::class)
        ->modulos($this->atendimento, $tecnico->user->fresh());

    // doc §2.3: o técnico não lê solicitação de exame. Um card que leva a 403 gasta o
    // clique de quem está no meio de um plantão.
    expect($modulos['exames']['liberado'])->toBeFalse()
        ->and($modulos['medicamentos']['liberado'])->toBeTrue();
});

it('chamar pela fila poe o atendimento em curso e leva ao atendimento', function () {
    $this->atendimento->update(['status' => StatusAtendimento::AguardandoAtendimento]);

    $this->actingAs($this->autor)
        ->post(route('fila.chamar', $this->atendimento))
        // Quem chama vai atender agora: cair de volta na fila obrigaria a procurar o
        // paciente que acabou de sair dela.
        ->assertRedirect(route('atendimentos.show', $this->atendimento->id));

    expect($this->atendimento->fresh()->status)->toBe(StatusAtendimento::EmAtendimento);
});

it('chamar respeita RN-12: quem nao responde pelo atendimento nao chama', function () {
    $this->atendimento->update(['status' => StatusAtendimento::AguardandoAtendimento]);

    $tecnico = Profissional::factory()->create([
        'unidade_id' => $this->unidade->id,
        'categoria' => 'TECNICO_ENFERMAGEM',
    ]);
    $tecnico->user->assignRole('tecnico_enfermagem');

    $this->actingAs($tecnico->user->fresh())
        ->post(route('fila.chamar', $this->atendimento))
        ->assertForbidden();

    expect($this->atendimento->fresh()->status)->toBe(StatusAtendimento::AguardandoAtendimento);
});

/*
|--------------------------------------------------------------------------
| O card so vira link para quem pode executar o ato
|--------------------------------------------------------------------------
|
| Regressao: o card de exames era exibido a quem tem `exame.ler_solicitacao`
| -- enfermeiro assistencial e laboratorio -- mas apontava para o formulario de
| solicitacao, que a doc 2.3 reserva ao medico. O usuario via o botao, clicava,
| e batia num 403 que nao explicava nada.
*/

it('nao oferece Solicitar exame a quem nao pode solicitar', function () {
    $enfermeiro = Profissional::factory()->create([
        'unidade_id' => $this->unidade->id,
        'categoria' => 'ENFERMEIRO',
    ]);
    $enfermeiro->user->assignRole('enfermeiro_assistencial');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $enfermeiro->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);
    $usuario = $enfermeiro->user->fresh();

    $exames = app(PainelAtendimentoService::class)->modulos($this->atendimento, $usuario)['exames'];

    // Ele precisa SABER que ha exames -- e nao pode receber um link que devolve 403.
    expect($exames['liberado'])->toBeTrue()
        ->and($exames['acao_liberada'])->toBeFalse();

    // A prova de que o link estaria quebrado: a rota nega mesmo.
    $this->actingAs($usuario)
        ->get(route('exames.create', $this->atendimento))
        ->assertForbidden();
});

it('oferece Solicitar exame ao medico responsavel, e a rota aceita', function () {
    $exames = app(PainelAtendimentoService::class)->modulos($this->atendimento, $this->autor)['exames'];

    expect($exames['acao_liberada'])->toBeTrue()
        ->and($exames['acao'])->toBe('Solicitar exame');

    $this->actingAs($this->autor)
        ->get(route('exames.create', $this->atendimento))
        ->assertOk();
});

it('o rotulo do card acompanha o que a pessoa pode fazer, nao o que ela ve', function () {
    $tecnico = Profissional::factory()->create([
        'unidade_id' => $this->unidade->id,
        'categoria' => 'TECNICO_ENFERMAGEM',
    ]);
    $tecnico->user->assignRole('tecnico_enfermagem');

    $modulos = app(PainelAtendimentoService::class)->modulos($this->atendimento, $tecnico->user->fresh());

    // O tecnico le prescricao e administra, mas nao prescreve (doc 2.3).
    expect($modulos['medicamentos']['acao'])->toBe('Ver prescrições')
        ->and($modulos['medicamentos']['acao_liberada'])->toBeTrue();

    expect($modulos['triagem']['acao'])->toBe('Ver triagem');
});

it('a pendencia explica para todos, mas so oferece o botao a quem pode agir', function () {
    $this->atendimento->update(['status' => StatusAtendimento::AguardandoExame]);

    $enfermeiro = Profissional::factory()->create([
        'unidade_id' => $this->unidade->id,
        'categoria' => 'ENFERMEIRO',
    ]);
    $enfermeiro->user->assignRole('enfermeiro_assistencial');
    $usuario = $enfermeiro->user->fresh();

    $painel = app(PainelAtendimentoService::class);
    $atendimento = $this->atendimento->fresh();
    $pendencia = $painel->pendencia($atendimento, $painel->modulos($atendimento, $usuario));

    /*
     * O enfermeiro e quem vai ouvir a pergunta do paciente: ele precisa da explicacao.
     * Trocar a confusao "nada acontece" por um 403 nao seria progresso.
     */
    expect($pendencia)->not->toBeNull()
        ->and($pendencia['texto'])->toContain('nenhum exame foi solicitado')
        ->and($pendencia['acao_liberada'])->toBeFalse();

    $doMedico = $painel->pendencia($atendimento, $painel->modulos($atendimento, $this->autor));

    expect($doMedico['acao_liberada'])->toBeTrue();
});

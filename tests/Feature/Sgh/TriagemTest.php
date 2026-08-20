<?php

declare(strict_types=1);

use App\Actions\Atendimento\AbrirAtendimentoAction;
use App\Actions\Triagem\RealizarTriagemAction;
use App\Actions\Triagem\ReclassificarRiscoAction;
use App\Enums\SituacaoEspera;
use App\Enums\StatusAtendimento;
use App\Events\EmergenciaDetectada;
use App\Events\ReimpressaoPulseiraNecessaria;
use App\Events\TriagemRealizada;
use App\Exceptions\TriagemInvalidaException;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\SinalVital;
use App\Models\Unidade;
use App\Services\Fila\AvaliadorEsperaService;
use Database\Seeders\ClassificacaoRiscoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

/** Ids do ClassificacaoRiscoSeeder: 1 vermelho, 2 laranja, 3 amarelo, 4 verde, 5 azul. */
const VERMELHO = 1;
const LARANJA = 2;
const AMARELO = 3;
const VERDE = 4;
const AZUL = 5;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->unidade = Unidade::factory()->create();

    $this->enfermeiro = Profissional::factory()->enfermeiro()->create(['unidade_id' => $this->unidade->id]);
    $this->enfermeiro->user->assignRole('enfermeiro_triagem');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $this->enfermeiro->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);
    $this->triador = $this->enfermeiro->user->fresh();

    $this->recepcao = Profissional::factory()->recepcao()->create(['unidade_id' => $this->unidade->id]);
    $this->recepcao->user->assignRole('recepcao');

    $this->abrir = fn () => app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(),
        unidade: $this->unidade,
        autor: $this->recepcao->user->fresh(),
    );
});

// =====================================================================
// Triagem inicial
// =====================================================================

it('registra triagem, sinais vitais e coloca o paciente na fila', function () {
    Event::fake([TriagemRealizada::class, ReimpressaoPulseiraNecessaria::class]);

    $atendimento = ($this->abrir)();

    $triagem = app(RealizarTriagemAction::class)->execute(
        atendimento: $atendimento,
        classificacaoRiscoId: VERDE,
        autor: $this->triador,
        queixaPrincipal: 'Dor de garganta há dois dias.',
        sinaisVitais: ['temperatura' => 37.8, 'escala_dor' => 4, 'saturacao_o2' => 98],
    );

    $atendimento->refresh();

    expect($triagem->reclassificacao)->toBeFalse()
        ->and($triagem->triagem_anterior_id)->toBeNull()
        ->and($atendimento->classificacao_risco_id)->toBe(VERDE)
        ->and($atendimento->status)->toBe(StatusAtendimento::AguardandoAtendimento);

    // D-06: sinais vitais em tabela propria, ligados a triagem.
    expect($triagem->sinalVital)->not->toBeNull()
        ->and((float) $triagem->sinalVital->temperatura)->toBe(37.8)
        ->and($triagem->sinalVital->escala_dor)->toBe(4);

    // Entrou na fila geral: profissional_id nulo ate a atribuicao da Fase 7.
    $filaItem = $atendimento->filaItemAtivo();
    expect($filaItem)->not->toBeNull()
        ->and($filaItem->classificacao_risco_id)->toBe(VERDE)
        ->and($filaItem->profissional_id)->toBeNull()
        ->and($filaItem->situacao)->toBe('AGUARDANDO');

    Event::assertDispatched(TriagemRealizada::class);
    Event::assertDispatched(ReimpressaoPulseiraNecessaria::class);
});

it('leva o vermelho direto para EM_ATENDIMENTO, sem fila', function () {
    // RN-11. Colocar uma parada cardiorrespiratoria na posicao 1 de uma fila ainda seria
    // coloca-la numa fila.
    Event::fake([EmergenciaDetectada::class]);

    $atendimento = ($this->abrir)();

    app(RealizarTriagemAction::class)->execute(
        atendimento: $atendimento,
        classificacaoRiscoId: VERMELHO,
        autor: $this->triador,
        queixaPrincipal: 'Parada cardiorrespiratoria.',
    );

    $atendimento->refresh();

    expect($atendimento->status)->toBe(StatusAtendimento::EmAtendimento)
        ->and($atendimento->classificacao_risco_id)->toBe(VERMELHO)
        // Nenhum item de fila foi criado.
        ->and($atendimento->filaItens()->count())->toBe(0)
        ->and($atendimento->primeiro_atendimento_em)->not->toBeNull();

    Event::assertDispatched(EmergenciaDetectada::class);
});

it('recusa triar duas vezes o mesmo atendimento', function () {
    $atendimento = ($this->abrir)();
    $acao = app(RealizarTriagemAction::class);

    $acao->execute($atendimento, VERDE, $this->triador, 'Queixa inicial.');

    expect(fn () => $acao->execute($atendimento->fresh(), AMARELO, $this->triador, 'Outra.'))
        ->toThrow(TriagemInvalidaException::class);

    expect($atendimento->fresh()->triagens()->count())->toBe(1);
});

it('recusa triar atendimento encerrado', function () {
    $atendimento = ($this->abrir)();
    DB::table('atendimento')->where('id', $atendimento->id)->update([
        'status' => 'FINALIZADO', 'desfecho' => 'ALTA', 'finalizado_em' => now(),
    ]);

    expect(fn () => app(RealizarTriagemAction::class)
        ->execute($atendimento->fresh(), VERDE, $this->triador, 'Queixa.'))
        ->toThrow(TriagemInvalidaException::class);
});

// =====================================================================
// Reclassificacao -- doc 7.5
// =====================================================================

it('preserva entrou_em ao reclassificar de verde para laranja e reordena a fila', function () {
    // O teste central da doc 7.5: reclassificar nao e penalidade nem premio de posicao.
    Carbon::setTestNow(Carbon::parse('2026-08-18 14:00:00'));

    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Dor abdominal leve.');

    $filaItem = $atendimento->fresh()->filaItemAtivo();
    $entrouEmOriginal = $filaItem->entrou_em;
    $filaItemId = $filaItem->id;

    // Uma hora depois, o quadro piora.
    Carbon::setTestNow(Carbon::parse('2026-08-18 15:00:00'));

    app(ReclassificarRiscoAction::class)->execute(
        atendimento: $atendimento->fresh(),
        novaClassificacaoId: LARANJA,
        autor: $this->triador,
        justificativa: 'Queda de saturacao e piora da dor.',
        sinaisVitais: ['saturacao_o2' => 89, 'escala_dor' => 9],
    );

    $filaItem = $filaItem->fresh();

    expect($filaItem->id)->toBe($filaItemId, 'Deveria reaproveitar o MESMO item de fila.')
        // O ponto: uma hora de espera nao e perdida.
        ->and($filaItem->entrou_em->equalTo($entrouEmOriginal))->toBeTrue()
        // A reordenacao acontece sozinha na leitura da view: so a prioridade mudou.
        ->and($filaItem->classificacao_risco_id)->toBe(LARANJA)
        ->and($atendimento->fresh()->classificacao_risco_id)->toBe(LARANJA);

    Carbon::setTestNow();
});

it('mantem a triagem anterior legivel e encadeada apos a reclassificacao', function () {
    // Reconstruir "entrou verde e virou laranja as 14h20, com queda de saturacao" e o
    // que sustenta a auditoria de evento adverso. Um UPDATE apagaria exatamente isso.
    $atendimento = ($this->abrir)();

    $original = app(RealizarTriagemAction::class)->execute(
        $atendimento, VERDE, $this->triador, 'Dor abdominal leve.', 'Sem sinais de alarme.'
    );

    $nova = app(ReclassificarRiscoAction::class)->execute(
        atendimento: $atendimento->fresh(),
        novaClassificacaoId: LARANJA,
        autor: $this->triador,
        justificativa: 'Queda de saturacao.',
    );

    $original->refresh();

    expect($atendimento->fresh()->triagens()->count())->toBe(2)
        // A anterior permanece exatamente como estava.
        ->and($original->classificacao_risco_id)->toBe(VERDE)
        ->and($original->justificativa_classificacao)->toBe('Sem sinais de alarme.')
        ->and($original->reclassificacao)->toBeFalse()
        // E a nova aponta para ela.
        ->and($nova->triagem_anterior_id)->toBe($original->id)
        ->and($nova->reclassificacao)->toBeTrue()
        ->and($nova->classificacao_risco_id)->toBe(LARANJA)
        // A queixa principal acompanha: o motivo da vinda nao muda com a gravidade.
        ->and($nova->queixa_principal)->toBe($original->queixa_principal);
});

it('dispara reimpressao de pulseira na reclassificacao', function () {
    // RN-09: uma pulseira verde num paciente laranja e pior que nenhuma pulseira.
    Event::fake([ReimpressaoPulseiraNecessaria::class]);

    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Queixa.');

    app(ReclassificarRiscoAction::class)->execute(
        $atendimento->fresh(), LARANJA, $this->triador, 'Piora do quadro.'
    );

    Event::assertDispatched(
        ReimpressaoPulseiraNecessaria::class,
        fn (ReimpressaoPulseiraNecessaria $e) => $e->motivo === 'RECLASSIFICACAO'
    );
});

it('leva a atendimento imediato quando a reclassificacao e vermelha', function () {
    // RN-11 tambem vale para quem chegou verde e piorou.
    Event::fake([EmergenciaDetectada::class]);

    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Queixa.');

    app(ReclassificarRiscoAction::class)->execute(
        $atendimento->fresh(), VERMELHO, $this->triador, 'Rebaixamento do nivel de consciencia.'
    );

    $atendimento->refresh();

    expect($atendimento->status)->toBe(StatusAtendimento::EmAtendimento)
        ->and($atendimento->classificacao_risco_id)->toBe(VERMELHO)
        // Sai da fila: quem esta em atendimento nao ocupa posicao.
        ->and($atendimento->filaItemAtivo())->toBeNull();

    Event::assertDispatched(EmergenciaDetectada::class);
});

it('recusa reclassificar sem justificativa', function () {
    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Queixa.');

    expect(fn () => app(ReclassificarRiscoAction::class)
        ->execute($atendimento->fresh(), LARANJA, $this->triador, '   '))
        ->toThrow(TriagemInvalidaException::class);

    expect($atendimento->fresh()->triagens()->count())->toBe(1);
});

it('recusa reclassificar sem triagem anterior', function () {
    $atendimento = ($this->abrir)();

    expect(fn () => app(ReclassificarRiscoAction::class)
        ->execute($atendimento, LARANJA, $this->triador, 'Justificativa qualquer.'))
        ->toThrow(TriagemInvalidaException::class);
});

it('registra a serie temporal de sinais vitais, sem sobrescrever', function () {
    // D-06: a comparacao entre as afericoes e o que mostra a piora.
    $atendimento = ($this->abrir)();

    app(RealizarTriagemAction::class)->execute(
        $atendimento, VERDE, $this->triador, 'Queixa.', sinaisVitais: ['saturacao_o2' => 98]
    );

    app(ReclassificarRiscoAction::class)->execute(
        $atendimento->fresh(), LARANJA, $this->triador, 'Queda de saturacao.',
        sinaisVitais: ['saturacao_o2' => 89]
    );

    $afericoes = $atendimento->fresh()->sinaisVitais()->orderBy('aferido_em')->get();

    expect($afericoes)->toHaveCount(2)
        ->and((float) $afericoes[0]->saturacao_o2)->toBe(98.0)
        ->and((float) $afericoes[1]->saturacao_o2)->toBe(89.0);
});

it('audita a reclassificacao com a justificativa', function () {
    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Queixa.');

    app(ReclassificarRiscoAction::class)->execute(
        $atendimento->fresh(), LARANJA, $this->triador, 'Queda de saturacao para 89%.'
    );

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'triagem.reclassificar',
        'atendimento_id' => $atendimento->id,
        'justificativa' => 'Queda de saturacao para 89%.',
    ]);
});

// =====================================================================
// Os CHECK de faixa continuam sendo do banco
// =====================================================================

it('o banco recusa sinal vital fora da faixa mesmo pela Action', function () {
    // A validacao do FormRequest da mensagem; o CHECK e quem garante.
    $atendimento = ($this->abrir)();

    expect(fn () => app(RealizarTriagemAction::class)->execute(
        $atendimento, VERDE, $this->triador, 'Queixa.', sinaisVitais: ['temperatura' => 99.9]
    ))->toThrow(QueryException::class);

    // A transacao inteira foi revertida: nem triagem, nem sinal vital.
    expect($atendimento->fresh()->triagens()->count())->toBe(0)
        ->and(SinalVital::count())->toBe(0);
});

// =====================================================================
// AvaliadorEsperaService -- doc 7.3.1
// =====================================================================

it('classifica a criticidade da espera sem reordenar nada', function () {
    $avaliador = app(AvaliadorEsperaService::class);

    // Vermelho: tempo-alvo zero, nunca espera.
    expect($avaliador->avaliar(0, 0))->toBe(SituacaoEspera::AtendimentoImediato)
        ->and($avaliador->avaliar(30, 0))->toBe(SituacaoEspera::AtendimentoImediato);

    // Verde: alvo de 120 min.
    expect($avaliador->avaliar(60, 120))->toBe(SituacaoEspera::DentroDoAlvo)     // 0,50
        ->and($avaliador->avaliar(89, 120))->toBe(SituacaoEspera::DentroDoAlvo)  // 0,74
        ->and($avaliador->avaliar(90, 120))->toBe(SituacaoEspera::ProximoDoAlvo) // 0,75
        ->and($avaliador->avaliar(119, 120))->toBe(SituacaoEspera::ProximoDoAlvo)
        ->and($avaliador->avaliar(120, 120))->toBe(SituacaoEspera::AlvoExcedido) // 1,00
        ->and($avaliador->avaliar(239, 120))->toBe(SituacaoEspera::AlvoExcedido)
        ->and($avaliador->avaliar(240, 120))->toBe(SituacaoEspera::EsperaCritica); // 2,00
});

it('sugere reavaliacao a partir do alvo excedido, sem promover a prioridade', function () {
    // doc 7.3.1: envelhecimento automatico e PROIBIDO. Um azul que espera tres horas nao
    // se torna mais grave que um laranja que acabou de chegar; promove-lo inverteria a
    // logica de seguranca do paciente.
    expect(SituacaoEspera::DentroDoAlvo->sugereReavaliacao())->toBeFalse()
        ->and(SituacaoEspera::ProximoDoAlvo->sugereReavaliacao())->toBeFalse()
        ->and(SituacaoEspera::AlvoExcedido->sugereReavaliacao())->toBeTrue()
        ->and(SituacaoEspera::EsperaCritica->sugereReavaliacao())->toBeTrue();
});

it('nao altera a prioridade do paciente que espera demais', function () {
    // A prova de que nao ha envelhecimento: mesmo muito alem do alvo, a classificacao
    // continua a mesma. Quem muda e o profissional, reclassificando.
    Carbon::setTestNow(Carbon::parse('2026-08-18 08:00:00'));

    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, AZUL, $this->triador, 'Queixa leve.');

    $filaItem = $atendimento->fresh()->filaItemAtivo();

    // Dez horas depois -- 600 min contra um alvo de 240.
    Carbon::setTestNow(Carbon::parse('2026-08-18 18:00:00'));

    expect($filaItem->fresh()->classificacao_risco_id)->toBe(AZUL)
        ->and($atendimento->fresh()->classificacao_risco_id)->toBe(AZUL);

    $situacao = app(AvaliadorEsperaService::class)->avaliar(600, 240);
    expect($situacao)->toBe(SituacaoEspera::EsperaCritica)
        ->and($situacao->sugereReavaliacao())->toBeTrue();

    Carbon::setTestNow();
});

// =====================================================================
// Autorizacao
// =====================================================================

it('nega triagem a quem nao tem triagem.classificar', function () {
    $atendimento = ($this->abrir)();

    $this->actingAs($this->recepcao->user->fresh())
        ->post(route('triagem.store', $atendimento->id), [
            'classificacao_risco_id' => VERDE,
            'queixa_principal' => 'Tentativa indevida.',
        ])
        ->assertForbidden();

    expect($atendimento->fresh()->triagens()->count())->toBe(0);
});

it('exibe a cadeia de triagens na tela', function () {
    $atendimento = ($this->abrir)();
    app(RealizarTriagemAction::class)->execute($atendimento, VERDE, $this->triador, 'Queixa.');
    app(ReclassificarRiscoAction::class)->execute(
        $atendimento->fresh(), LARANJA, $this->triador, 'Piora.'
    );

    $this->actingAs($this->triador)
        ->get(route('triagem.edit', $atendimento->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Triagem/Edit')
            ->has('triagens', 2)
            ->where('jaTriado', true)
            // A mais recente primeiro.
            ->where('triagens.0.reclassificacao', true)
            ->where('triagens.1.reclassificacao', false)
        );
});

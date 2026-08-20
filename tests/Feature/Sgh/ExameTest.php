<?php

declare(strict_types=1);

use App\Actions\Exame\AlterarSituacaoExameAction;
use App\Actions\Exame\LiberarResultadoAction;
use App\Actions\Exame\RegistrarResultadoAction;
use App\Actions\Exame\SolicitarExameAction;
use App\Enums\SituacaoExame;
use App\Enums\StatusAtendimento;
use App\Events\ValorCriticoDetectado;
use App\Exceptions\ExameInvalidoException;
use App\Models\Atendimento;
use App\Models\Exame;
use App\Models\ExameFaixaCritica;
use App\Models\ExameSolicitacao;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Exame\AvaliadorResultadoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->unidade = Unidade::factory()->create();
    $this->medico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $this->medico->user->assignRole('medico');
    $this->laboratorio = Profissional::factory()->laboratorio()->create(['unidade_id' => $this->unidade->id]);
    $this->laboratorio->user->assignRole('laboratorio');
    $this->paciente = Paciente::factory()->create();
    $this->atendimento = Atendimento::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'profissional_responsavel_id' => $this->medico->user_id,
        'status' => StatusAtendimento::EmAtendimento,
    ]);
    $this->exame = Exame::factory()->create(['nome' => 'Eletrólitos']);
});

function solicitacaoEmExecucao(object $teste): ExameSolicitacao
{
    $solicitacao = app(SolicitarExameAction::class)->execute(
        $teste->atendimento, $teste->exame, $teste->medico->user->fresh(), 'ROTINA', 'Avaliar distúrbio eletrolítico.'
    );
    $acao = app(AlterarSituacaoExameAction::class);
    $solicitacao = $acao->execute($solicitacao, SituacaoExame::Coletado, $teste->laboratorio->user->fresh());

    return $acao->execute($solicitacao, SituacaoExame::EmExecucao, $teste->laboratorio->user->fresh());
}

it('usa faixa crítica parametrizada em tabela, não constante de código', function () {
    $avaliador = app(AvaliadorResultadoService::class);
    expect($avaliador->sinalizar('Potassio', 7.2, 3.5, 5.5, 'mEq/L'))->toBe('CRITICO');

    ExameFaixaCritica::where('analito', 'Potássio')->update(['critico_max' => 8.0]);
    expect($avaliador->sinalizar('Potássio', 7.2, 3.5, 5.5, 'mEq/L'))->toBe('ALTO');
});

it('cumpre o ciclo SOLICITADO até LIBERADO sem pular estados', function () {
    $solicitacao = app(SolicitarExameAction::class)->execute(
        $this->atendimento, $this->exame, $this->medico->user->fresh(), 'URGENTE', 'Hipercalemia suspeita.'
    );
    expect($solicitacao->situacao)->toBe(SituacaoExame::Solicitado);

    $ciclo = app(AlterarSituacaoExameAction::class);
    $solicitacao = $ciclo->execute($solicitacao, SituacaoExame::Coletado, $this->laboratorio->user->fresh());
    $solicitacao = $ciclo->execute($solicitacao, SituacaoExame::EmExecucao, $this->laboratorio->user->fresh());
    $resultado = app(RegistrarResultadoAction::class)->execute(
        $solicitacao, $this->laboratorio->user->fresh(),
        [['analito' => 'Sódio', 'valor' => '140', 'unidade' => 'mEq/L', 'referencia_min' => 135, 'referencia_max' => 145]],
    );
    expect($solicitacao->fresh()->situacao)->toBe(SituacaoExame::Concluido)
        ->and($resultado->visivel_ao_paciente)->toBeFalse();

    app(LiberarResultadoAction::class)->execute($resultado, $this->laboratorio->user->fresh());
    expect($solicitacao->fresh()->situacao)->toBe(SituacaoExame::Liberado)
        ->and($resultado->fresh()->visivel_ao_paciente)->toBeTrue();
});

it('permite que o superadmin solicite exame sem habilitação médica', function () {
    $admin = User::factory()->admin()->create();
    Profissional::factory()->admin()->create([
        'user_id' => $admin->id,
        'unidade_id' => $this->unidade->id,
        'ativo' => false,
    ]);

    $solicitacao = app(SolicitarExameAction::class)->execute(
        $this->atendimento,
        $this->exame,
        $admin->fresh(),
        'ROTINA',
        'Solicitação administrativa.',
    );

    expect($solicitacao->solicitado_por)->toBe($admin->id)
        ->and($solicitacao->solicitante->categoria)->toBe('ADMIN');
});

it('continua exigindo habilitação médica de quem não é superadmin', function () {
    $profissional = Profissional::factory()->admin()->create([
        'unidade_id' => $this->unidade->id,
    ]);

    app(SolicitarExameAction::class)->execute(
        $this->atendimento,
        $this->exame,
        $profissional->user->fresh(),
        'ROTINA',
    );
})->throws(ExameInvalidoException::class, 'Solicitação de exame exige médico ativo com conselho válido.');

it('recusa pular da solicitação diretamente para execução', function () {
    $solicitacao = app(SolicitarExameAction::class)->execute(
        $this->atendimento, $this->exame, $this->medico->user->fresh(), 'ROTINA'
    );
    app(AlterarSituacaoExameAction::class)->execute(
        $solicitacao, SituacaoExame::EmExecucao, $this->laboratorio->user->fresh()
    );
})->throws(ExameInvalidoException::class);

it('grava cada analito com snapshot da faixa de referência e sinalização calculada', function () {
    $solicitacao = solicitacaoEmExecucao($this);
    $resultado = app(RegistrarResultadoAction::class)->execute(
        $solicitacao, $this->laboratorio->user->fresh(),
        [['analito' => 'Creatinina', 'valor' => '2.1', 'unidade' => 'mg/dL', 'referencia_min' => 0.6, 'referencia_max' => 1.3]],
    );
    $item = $resultado->itens->first();

    expect((float) $item->referencia_min)->toBe(0.6)
        ->and((float) $item->referencia_max)->toBe(1.3)
        ->and($item->sinalizacao)->toBe('ALTO');
});

it('detecta valor crítico, agrega no resultado e notifica prioritariamente', function () {
    Event::fake([ValorCriticoDetectado::class]);
    $resultado = app(RegistrarResultadoAction::class)->execute(
        solicitacaoEmExecucao($this), $this->laboratorio->user->fresh(),
        [['analito' => 'Potássio', 'valor' => '7.2', 'unidade' => 'mEq/L', 'referencia_min' => 3.5, 'referencia_max' => 5.5]],
    );

    expect($resultado->possui_valor_critico)->toBeTrue()
        ->and($resultado->itens->first()->sinalizacao)->toBe('CRITICO');
    Event::assertDispatched(ValorCriticoDetectado::class);
});

it('valor crítico não é liberado pelo laboratório antes da ciência médica', function () {
    $resultado = app(RegistrarResultadoAction::class)->execute(
        solicitacaoEmExecucao($this), $this->laboratorio->user->fresh(),
        [['analito' => 'Potássio', 'valor' => '7.2', 'unidade' => 'mEq/L']],
    );

    expect(fn () => app(LiberarResultadoAction::class)->execute($resultado, $this->laboratorio->user->fresh()))
        ->toThrow(ExameInvalidoException::class);

    app(LiberarResultadoAction::class)->execute($resultado, $this->medico->user->fresh());
    expect($resultado->fresh()->visivel_ao_paciente)->toBeTrue()
        ->and($resultado->liberador->categoria)->toBe('MEDICO');
});

it('o banco recusa resultado visível sem autor e data de liberação', function () {
    $solicitacao = ExameSolicitacao::factory()->create([
        'atendimento_id' => $this->atendimento->id,
        'exame_id' => $this->exame->id,
        'solicitado_por' => $this->medico->user_id,
    ]);

    expect(fn () => DB::table('exame_resultado')->insert([
        'exame_solicitacao_id' => $solicitacao->id,
        'executado_por' => $this->laboratorio->user_id,
        'executado_em' => now(), 'visivel_ao_paciente' => true, 'criado_em' => now(),
    ]))->toThrow(QueryException::class);
});

it('armazena anexo fora do public com SHA-256 e só entrega pela rota autenticada', function () {
    Storage::fake('local');
    $arquivo = UploadedFile::fake()->createWithContent('laudo.pdf', '%PDF-1.4 conteúdo clínico');
    $resultado = app(RegistrarResultadoAction::class)->execute(
        solicitacaoEmExecucao($this), $this->laboratorio->user->fresh(),
        laudo: 'Sem alterações.', anexos: [$arquivo],
    );
    $anexo = $resultado->anexos->first();

    Storage::disk('local')->assertExists($anexo->caminho);
    expect($anexo->caminho)->toStartWith('exames/')
        ->and($anexo->hash_sha256)->toBe(hash('sha256', '%PDF-1.4 conteúdo clínico'))
        ->and(str_starts_with(Storage::disk('local')->path($anexo->caminho), public_path()))->toBeFalse();

    $this->get(route('exames.anexo', $anexo))->assertRedirect();
    $this->actingAs($this->laboratorio->user->fresh())->get(route('exames.anexo', $anexo))->assertDownload('laudo.pdf');
});

it('fila do laboratório ordena urgente antes de rotina e preserva ordem de chegada', function () {
    ExameSolicitacao::factory()->create([
        'atendimento_id' => $this->atendimento->id, 'exame_id' => $this->exame->id,
        'solicitado_por' => $this->medico->user_id, 'carater' => 'ROTINA', 'solicitado_em' => now()->subHour(),
    ]);
    ExameSolicitacao::factory()->create([
        'atendimento_id' => $this->atendimento->id, 'exame_id' => $this->exame->id,
        'solicitado_por' => $this->medico->user_id, 'carater' => 'URGENTE', 'solicitado_em' => now(),
    ]);

    $fila = ExameSolicitacao::query()->filaLaboratorio()->get();
    expect($fila)->toHaveCount(2)->and($fila[0]->carater)->toBe('URGENTE');
});

it('exibe a fila e a tela de resultado ao laboratório vinculado pela solicitação', function () {
    $solicitacao = app(SolicitarExameAction::class)->execute(
        $this->atendimento, $this->exame, $this->medico->user->fresh(), 'URGENTE'
    );

    $this->actingAs($this->laboratorio->user->fresh())->get(route('exames.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->component('Exames/Index')->has('fila', 1));
    $this->actingAs($this->laboratorio->user->fresh())->get(route('exames.show', $solicitacao))
        ->assertOk()->assertInertia(fn ($p) => $p->component('Exames/Show')->where('solicitacao.carater', 'URGENTE'));
});

it('cancela apenas com motivo e não permite retomar estado terminal', function () {
    $solicitacao = app(SolicitarExameAction::class)->execute(
        $this->atendimento, $this->exame, $this->medico->user->fresh(), 'ROTINA'
    );
    $acao = app(AlterarSituacaoExameAction::class);
    expect(fn () => $acao->execute($solicitacao, SituacaoExame::Cancelado, $this->laboratorio->user->fresh()))
        ->toThrow(ExameInvalidoException::class);

    $cancelada = $acao->execute($solicitacao, SituacaoExame::Cancelado, $this->laboratorio->user->fresh(), 'Amostra inviável.');
    expect($cancelada->situacao)->toBe(SituacaoExame::Cancelado)
        ->and($cancelada->motivo_cancelamento)->toBe('Amostra inviável.');
    expect(fn () => $acao->execute($cancelada, SituacaoExame::Coletado, $this->laboratorio->user->fresh()))
        ->toThrow(ExameInvalidoException::class);
});

it('audita solicitação, resultado e liberação', function () {
    $solicitacao = solicitacaoEmExecucao($this);
    $resultado = app(RegistrarResultadoAction::class)->execute($solicitacao, $this->laboratorio->user->fresh(), laudo: 'Normal.');
    app(LiberarResultadoAction::class)->execute($resultado, $this->laboratorio->user->fresh());

    expect(DB::table('auditoria_log')->where('acao', 'exame.solicitar')->exists())->toBeTrue()
        ->and(DB::table('auditoria_log')->where('acao', 'exame.registrar_resultado')->exists())->toBeTrue()
        ->and(DB::table('auditoria_log')->where('acao', 'exame.liberar_resultado')->exists())->toBeTrue();
});

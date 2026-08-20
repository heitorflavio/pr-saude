<?php

declare(strict_types=1);

use App\Actions\Medicamento\PrescreverAction;
use App\Actions\Medicamento\RegistrarAdministracaoAction;
use App\Actions\Medicamento\SuspenderPrescricaoAction;
use App\Enums\StatusAtendimento;
use App\Enums\ViaAdministracao;
use App\Events\AlertaAlergiaSobreposto;
use App\Events\DoseDivergente;
use App\Exceptions\AdministracaoInvalidaException;
use App\Exceptions\AlergiaBloqueanteException;
use App\Exceptions\DoseJaAdministradaException;
use App\Exceptions\DuplaChecagemObrigatoriaException;
use App\Models\AdministracaoMedicamento;
use App\Models\Atendimento;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\PacienteAlergia;
use App\Models\PrescricaoItem;
use App\Models\Profissional;
use App\Models\Unidade;
use App\Services\Medicamento\AprazamentoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->unidade = Unidade::factory()->create();
    $this->medico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $this->medico->user->assignRole('medico');
    $this->enfermeiro = Profissional::factory()->enfermeiro()->create(['unidade_id' => $this->unidade->id]);
    $this->enfermeiro->user->assignRole('enfermeiro_assistencial');
    $this->conferente = Profissional::factory()->enfermeiro()->create(['unidade_id' => $this->unidade->id]);
    $this->conferente->user->assignRole('enfermeiro_assistencial');
    $this->paciente = Paciente::factory()->create();
    $this->atendimento = Atendimento::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'profissional_responsavel_id' => $this->enfermeiro->user_id,
        'status' => StatusAtendimento::EmAtendimento,
    ]);
    $this->medicamento = Medicamento::factory()->create([
        'nome_comercial' => 'Novalgina',
        'principio_ativo' => 'Dipirona sódica',
        'alta_vigilancia' => false,
    ]);
});

function prescreverDose(object $teste, ?Medicamento $medicamento = null, array $sobrescreve = [])
{
    $prescricao = app(PrescreverAction::class)->execute(
        $teste->atendimento,
        $teste->medico->user->fresh(),
        [[
            'medicamento_id' => ($medicamento ?? $teste->medicamento)->id,
            'dose' => 1000,
            'unidade_dose' => 'mg',
            'via' => 'IV',
            'frequencia_horas' => 6,
            'duracao_horas' => 24,
            'se_necessario' => false,
            ...$sobrescreve,
        ]],
    );

    return $prescricao->itens->first()->aprazamentos->first();
}

it('ancora 6 em 6 horas na grade 18, 00, 06 e 12', function () {
    $item = PrescricaoItem::factory()->create([
        'frequencia_horas' => 6, 'duracao_horas' => 24, 'se_necessario' => false,
    ]);

    $quantidade = app(AprazamentoService::class)->gerar($item, CarbonImmutable::parse('2026-08-20 14:37:45'));
    $horarios = $item->aprazamentos()->orderBy('sequencia')->pluck('horario_previsto')
        ->map(fn ($h) => CarbonImmutable::parse($h)->format('d H:i'))->all();

    expect($quantidade)->toBe(4)
        ->and($horarios)->toBe(['20 18:00', '21 00:00', '21 06:00', '21 12:00']);
});

it('não apraza medicação se necessário', function () {
    $item = PrescricaoItem::factory()->seNecessario()->create();
    expect(app(AprazamentoService::class)->gerar($item))->toBe(0)
        ->and($item->aprazamentos()->count())->toBe(0);
});

it('cria prescrição e seus aprazamentos na mesma transação', function () {
    $dose = prescreverDose($this);

    expect($dose->prescricaoItem->prescricao->status)->toBe('VIGENTE')
        ->and($dose->prescricaoItem->aprazamentos()->count())->toBe(4)
        ->and(DB::table('auditoria_log')->where('acao', 'prescricao.criar')->exists())->toBeTrue();
});

it('recusa prescrição por quem não é médico ativo com conselho', function () {
    app(PrescreverAction::class)->execute($this->atendimento, $this->enfermeiro->user->fresh(), [[
        'medicamento_id' => $this->medicamento->id, 'dose' => 1, 'unidade_dose' => 'mg',
        'via' => 'ORAL', 'frequencia_horas' => 8, 'duracao_horas' => 24,
    ]]);
})->throws(AdministracaoInvalidaException::class);

it('alergia ao princípio ativo bloqueia mesmo com nome comercial diferente', function () {
    PacienteAlergia::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'substancia' => 'DIPIRONA SODICA',
        'medicamento_id' => null,
    ]);
    $dose = prescreverDose($this);

    app(RegistrarAdministracaoAction::class)->execute(
        $dose, $this->enfermeiro, 1000, ViaAdministracao::Intravenosa
    );
})->throws(AlergiaBloqueanteException::class);

it('alergia com justificativa registra sobreposição e emite notificação ao prescritor', function () {
    Event::fake([AlertaAlergiaSobreposto::class]);
    PacienteAlergia::factory()->doMedicamento($this->medicamento)->create(['paciente_id' => $this->paciente->user_id]);
    $dose = prescreverDose($this);

    $registro = app(RegistrarAdministracaoAction::class)->execute(
        $dose, $this->enfermeiro, 1000, 'IV', justificativaAlergia: 'Benefício supera risco; médico presente no leito.'
    );

    expect($registro->alerta_alergia_sobreposto)->toBeTrue()
        ->and($registro->justificativa)->toContain('Benefício')
        ->and($dose->fresh()->situacao)->toBe('ADMINISTRADA');
    Event::assertDispatched(AlertaAlergiaSobreposto::class);
});

it('recusa a mesma dose aprazada duas vezes e informa o primeiro registro', function () {
    $dose = prescreverDose($this);
    $acao = app(RegistrarAdministracaoAction::class);
    $acao->execute($dose, $this->enfermeiro, 1000, 'IV');

    expect(fn () => $acao->execute($dose->fresh(), $this->enfermeiro, 1000, 'IV'))
        ->toThrow(DoseJaAdministradaException::class);
    expect(AdministracaoMedicamento::where('aprazamento_id', $dose->id)->count())->toBe(1);
});

it('alta vigilância exige conferente distinto do executor', function () {
    $alta = Medicamento::factory()->altaVigilancia()->create();
    $dose = prescreverDose($this, $alta);
    $acao = app(RegistrarAdministracaoAction::class);

    expect(fn () => $acao->execute($dose, $this->enfermeiro, 1000, 'IV'))
        ->toThrow(DuplaChecagemObrigatoriaException::class);
    expect(fn () => $acao->execute($dose, $this->enfermeiro, 1000, 'IV', conferente: $this->enfermeiro))
        ->toThrow(DuplaChecagemObrigatoriaException::class);

    $registro = $acao->execute($dose, $this->enfermeiro, 1000, 'IV', conferente: $this->conferente);
    expect($registro->checado_por)->toBe($this->conferente->user_id);
});

it('dose divergente é permitida, sinalizada e exige observação', function () {
    Event::fake([DoseDivergente::class]);
    $dose = prescreverDose($this);

    expect(fn () => app(RegistrarAdministracaoAction::class)->execute($dose, $this->enfermeiro, 500, 'IV'))
        ->toThrow(AdministracaoInvalidaException::class);

    $registro = app(RegistrarAdministracaoAction::class)->execute($dose, $this->enfermeiro, 500, 'IV', observacao: 'Dose reduzida por hipotensão.');
    expect((float) $registro->dose_administrada)->toBe(500.0);
    Event::assertDispatched(DoseDivergente::class);
});

it('registra não administração com motivo e baixa o aprazamento atomicamente', function () {
    $dose = prescreverDose($this);
    $registro = app(RegistrarAdministracaoAction::class)->execute(
        $dose, $this->enfermeiro, null, null,
        resultado: 'NAO_ADMINISTRADA', motivoNaoAdministracao: 'RECUSA_PACIENTE'
    );

    expect($registro->resultado)->toBe('NAO_ADMINISTRADA')
        ->and($registro->motivo_nao_administracao)->toBe('RECUSA_PACIENTE')
        ->and($dose->fresh()->situacao)->toBe('NAO_ADMINISTRADA');
});

it('suspende a ordem, os itens e todas as doses ainda pendentes', function () {
    $dose = prescreverDose($this);
    $prescricao = $dose->prescricaoItem->prescricao;
    app(SuspenderPrescricaoAction::class)->execute($prescricao, $this->medico->user->fresh(), 'Reação adversa em investigação.');

    expect($prescricao->fresh()->status)->toBe('SUSPENSA')
        ->and($prescricao->itens()->where('status', 'VIGENTE')->count())->toBe(0)
        ->and($prescricao->itens->first()->aprazamentos()->where('situacao', 'PENDENTE')->count())->toBe(0);

    expect(fn () => app(RegistrarAdministracaoAction::class)->execute($dose->fresh(), $this->enfermeiro, 1000, 'IV'))
        ->toThrow(DoseJaAdministradaException::class);
});

it('o checklist lê a view de doses pendentes e abre a conferência dos nove certos', function () {
    $dose = prescreverDose($this);

    $this->actingAs($this->enfermeiro->user->fresh())
        ->get(route('medicamentos.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Medicamentos/Index')->has('doses', 4));

    $this->actingAs($this->enfermeiro->user->fresh())
        ->get(route('medicamentos.conferir', $dose))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('Medicamentos/Administrar')
        ->where('medicamento.principio_ativo', 'Dipirona sódica')
        ->where('paciente.nome', $this->paciente->nomeExibicao()));
});

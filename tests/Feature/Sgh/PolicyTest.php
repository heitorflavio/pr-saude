<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;
use App\Enums\TipoRegistroClinico;
use App\Models\AdministracaoMedicamento;
use App\Models\Atendimento;
use App\Models\ExameResultado;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\RegistroClinico;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

/**
 * A camada CONTEXTUAL. Estes testes são a razão de as Policies existirem: a permissão
 * do spatie responde "este papel pode, em princípio?"; a Policy responde "este usuário
 * pode, NESTE registro?". Permission sozinha nunca basta para dado clínico.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Profissional da categoria pedida, com role e plantão aberto. */
function profissionalEmPlantao(string $categoria = 'medico', string $role = 'medico'): Profissional
{
    $profissional = Profissional::factory()->{$categoria}()->create();
    $profissional->user->assignRole($role);

    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $profissional->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);

    return $profissional->fresh();
}

function foraDePlantao(Profissional $profissional): Profissional
{
    $profissional->disponibilidades()->update(['fim_em' => now()]);

    return $profissional->fresh();
}

// =====================================================================
// PacientePolicy -- vinculo assistencial (RN-28) e minimo vital (doc 13.5)
// =====================================================================

it('reconhece vinculo assistencial pelo profissional responsavel', function () {
    $medico = profissionalEmPlantao();
    $paciente = Paciente::factory()->create();

    Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'profissional_responsavel_id' => $medico->user_id,
    ]);

    expect(Gate::forUser($medico->user)->allows('verContexto', $paciente))->toBeTrue();
});

it('reconhece vinculo assistencial pela fila, pelo prontuario e pela prescricao', function () {
    $paciente = Paciente::factory()->create();
    $atendimento = Atendimento::factory()->create(['paciente_id' => $paciente->user_id]);

    $daFila = profissionalEmPlantao();
    FilaItem::factory()->create([
        'atendimento_id' => $atendimento->id,
        'profissional_id' => $daFila->user_id,
    ]);

    $doProntuario = profissionalEmPlantao();
    RegistroClinico::factory()->create([
        'atendimento_id' => $atendimento->id,
        'autor_id' => $doProntuario->user_id,
    ]);

    $doPrescricao = profissionalEmPlantao();
    Prescricao::factory()->create([
        'atendimento_id' => $atendimento->id,
        'prescrito_por' => $doPrescricao->user_id,
    ]);

    expect(Gate::forUser($daFila->user)->allows('verContexto', $paciente))->toBeTrue()
        ->and(Gate::forUser($doProntuario->user)->allows('verContexto', $paciente))->toBeTrue()
        ->and(Gate::forUser($doPrescricao->user)->allows('verContexto', $paciente))->toBeTrue();
});

it('nega contexto clinico a profissional sem nenhum vinculo com o paciente', function () {
    // A negativa que importa: um medico do hospital nao ve o prontuario de quem nao
    // atendeu, so por ser medico.
    $estranho = profissionalEmPlantao();
    $paciente = Paciente::factory()->create();

    Atendimento::factory()->create(['paciente_id' => $paciente->user_id]);

    expect(Gate::forUser($estranho->user)->allows('verContexto', $paciente))->toBeFalse();
});

it('libera o minimo vital a qualquer profissional em plantao, mesmo sem vinculo', function () {
    // doc 13.5: negar a lista de alergias a quem atende uma parada no corredor seria
    // decisao de projeto com potencial letal. O acesso e amplo; a auditoria e integral.
    $estranho = profissionalEmPlantao();
    $paciente = Paciente::factory()->create();

    expect(Gate::forUser($estranho->user)->allows('verContexto', $paciente))->toBeFalse()
        ->and(Gate::forUser($estranho->user)->allows('verMinimoVital', $paciente))->toBeTrue();
});

it('nega o minimo vital a profissional fora de plantao', function () {
    $profissional = foraDePlantao(profissionalEmPlantao());
    $paciente = Paciente::factory()->create();

    expect(Gate::forUser($profissional->user)->allows('verMinimoVital', $paciente))->toBeFalse();
});

it('permite quebra de sigilo a profissional em plantao com a permissao', function () {
    $medico = profissionalEmPlantao();
    $paciente = Paciente::factory()->create();

    expect(Gate::forUser($medico->user)->allows('quebrarSigilo', $paciente))->toBeTrue();
});

it('nega quebra de sigilo a quem nao tem a permissao', function () {
    $recepcao = profissionalEmPlantao('recepcao', 'recepcao');
    $paciente = Paciente::factory()->create();

    expect(Gate::forUser($recepcao->user)->allows('quebrarSigilo', $paciente))->toBeFalse();
});

it('nega quebra de sigilo a medico fora de plantao', function () {
    $medico = foraDePlantao(profissionalEmPlantao());
    $paciente = Paciente::factory()->create();

    expect(Gate::forUser($medico->user)->allows('quebrarSigilo', $paciente))->toBeFalse();
});

// =====================================================================
// AtendimentoPolicy -- RN-12 e a nota 1 da doc 2.3
// =====================================================================

it('permite ao profissional responsavel alterar o status do seu atendimento', function () {
    // RN-12
    $medico = profissionalEmPlantao();
    $atendimento = Atendimento::factory()->create([
        'profissional_responsavel_id' => $medico->user_id,
    ]);

    expect(Gate::forUser($medico->user)->allows('alterarStatus', [$atendimento, StatusAtendimento::EmAtendimento]))
        ->toBeTrue();
});

it('nega ao tecnico de enfermagem alterar status de atendimento que nao e dele', function () {
    // O tecnico tem a permissao estatica `atendimento.alterar_status`, mas nao e
    // responsavel nem supervisor -- e aqui que a camada contextual faz diferenca.
    $tecnico = profissionalEmPlantao('tecnicoEnfermagem', 'tecnico_enfermagem');
    $atendimento = Atendimento::factory()->create();

    expect($tecnico->user->can('atendimento.alterar_status'))->toBeTrue()
        ->and(Gate::forUser($tecnico->user)->allows('alterarStatus', [$atendimento, StatusAtendimento::EmAtendimento]))
        ->toBeFalse();
});

it('permite ao laboratorio apenas as transicoes de exame', function () {
    // Nota 1 da doc 2.3: restricao que depende do par (origem, destino) e por isso nao
    // e expressavel como permissao estatica.
    $lab = profissionalEmPlantao('laboratorio', 'laboratorio');

    $aguardandoExame = Atendimento::factory()->create([
        'status' => StatusAtendimento::AguardandoExame,
    ]);
    $emAtendimento = Atendimento::factory()->create([
        'status' => StatusAtendimento::EmAtendimento,
    ]);

    expect(Gate::forUser($lab->user)->allows('alterarStatus', [$aguardandoExame, StatusAtendimento::EmExame]))
        ->toBeTrue()
        ->and(Gate::forUser($lab->user)->allows('alterarStatus', [$emAtendimento, StatusAtendimento::Finalizado]))
        ->toBeFalse();
});

it('nega ao laboratorio finalizar ou cancelar atendimento', function () {
    $lab = profissionalEmPlantao('laboratorio', 'laboratorio');
    $atendimento = Atendimento::factory()->create(['status' => StatusAtendimento::EmAtendimento]);

    expect(Gate::forUser($lab->user)->allows('finalizar', $atendimento))->toBeFalse()
        ->and(Gate::forUser($lab->user)->allows('cancelar', $atendimento))->toBeFalse();
});

// =====================================================================
// RegistroClinicoPolicy -- a doc 2.3 separa nota medica de evolucao
// =====================================================================

it('permite ao tecnico criar evolucao de enfermagem mas nao nota medica', function () {
    $tecnico = profissionalEmPlantao('tecnicoEnfermagem', 'tecnico_enfermagem');

    expect(Gate::forUser($tecnico->user)->allows('create', [RegistroClinico::class, TipoRegistroClinico::EvolucaoEnfermagem]))
        ->toBeTrue()
        ->and(Gate::forUser($tecnico->user)->allows('create', [RegistroClinico::class, TipoRegistroClinico::EvolucaoMedica]))
        ->toBeFalse();
});

it('nega a exclusao de registro clinico a todo mundo', function () {
    // RN-17 / D-05: nenhum registro clinico e excluido, por ninguem, nunca.
    $medico = profissionalEmPlantao();
    $registro = RegistroClinico::factory()->create();

    expect(Gate::forUser($medico->user)->allows('delete', $registro))->toBeFalse()
        ->and(Gate::forUser($medico->user)->allows('forceDelete', $registro))->toBeFalse();
});

it('nega ao tecnico retificar nota medica', function () {
    $tecnico = profissionalEmPlantao('tecnicoEnfermagem', 'tecnico_enfermagem');
    $notaMedica = RegistroClinico::factory()->create(['tipo' => TipoRegistroClinico::EvolucaoMedica]);

    expect(Gate::forUser($tecnico->user)->allows('retificar', $notaMedica))->toBeFalse();
});

// =====================================================================
// AdministracaoPolicy -- RN-22, a dupla checagem
// =====================================================================

it('recusa o proprio executor como conferente da dupla checagem', function () {
    // RN-22: se a mesma pessoa pudesse conferir a propria dose, o controle nao
    // existiria -- seria so mais um clique.
    $executor = profissionalEmPlantao('tecnicoEnfermagem', 'tecnico_enfermagem');
    $outro = profissionalEmPlantao('enfermeiro', 'enfermeiro_assistencial');

    expect(Gate::forUser($executor->user)->allows('conferir', [AdministracaoMedicamento::class, $executor]))
        ->toBeFalse()
        ->and(Gate::forUser($outro->user)->allows('conferir', [AdministracaoMedicamento::class, $executor]))
        ->toBeTrue();
});

// =====================================================================
// ExameResultadoPolicy -- RN-24 e RN-25
// =====================================================================

it('permite ao laboratorio liberar resultado sem valor critico', function () {
    $lab = profissionalEmPlantao('laboratorio', 'laboratorio');
    $resultado = ExameResultado::factory()->create(['possui_valor_critico' => false]);

    expect(Gate::forUser($lab->user)->allows('liberar', $resultado))->toBeTrue();
});

it('nega ao laboratorio liberar resultado com valor critico antes da ciencia medica', function () {
    // RN-25: um potassio de 7,2 chegando ao celular do paciente antes de o medico ver
    // nao e transparencia, e dano -- e a decisao do que fazer com ele e clinica.
    $lab = profissionalEmPlantao('laboratorio', 'laboratorio');
    $medico = profissionalEmPlantao();
    $resultado = ExameResultado::factory()->create(['possui_valor_critico' => true]);

    expect(Gate::forUser($lab->user)->allows('liberar', $resultado))->toBeFalse()
        ->and(Gate::forUser($medico->user)->allows('liberar', $resultado))->toBeTrue();
});

// =====================================================================
// O paciente nao alcanca nenhuma Policy de escrita (RN-26, RN-27)
// =====================================================================

it('nega ao paciente todas as policies de dado clinico', function () {
    $paciente = Paciente::factory()->create();
    $usuario = $paciente->user;
    $outroPaciente = Paciente::factory()->create();
    $atendimento = Atendimento::factory()->create();
    $registro = RegistroClinico::factory()->create();

    expect(Gate::forUser($usuario)->allows('verContexto', $outroPaciente))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows('verMinimoVital', $outroPaciente))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows('quebrarSigilo', $outroPaciente))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows('alterarStatus', [$atendimento, StatusAtendimento::Finalizado]))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows('view', $registro))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows('create', [Prescricao::class]))->toBeFalse();
});

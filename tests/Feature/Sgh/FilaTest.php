<?php

declare(strict_types=1);

use App\Actions\Atendimento\AlterarStatusAction;
use App\Actions\Fila\AtribuirProfissionalAction;
use App\Actions\Fila\TransferirFilaAction;
use App\Enums\StatusAtendimento;
use App\Exceptions\FilaInvalidaException;
use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fila\EstimadorEsperaService;
use App\Services\Fila\PainelFilaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ids do ClassificacaoRiscoSeeder. */
const V_VERMELHO = 1;
const V_LARANJA = 2;
const V_AMARELO = 3;
const V_VERDE = 4;
const V_AZUL = 5;

beforeEach(function () {
    $this->unidade = Unidade::factory()->create();

    $this->medico = criarProfissionalDisponivel($this->unidade, 'medico', 'medico');
    $this->autor = $this->medico->user->fresh();
    $this->autor->givePermissionTo('fila.atribuir');
});

function criarProfissionalDisponivel(Unidade $unidade, string $categoria, string $role, string $situacao = 'DISPONIVEL'): Profissional
{
    $profissional = Profissional::factory()->{$categoria}()->create(['unidade_id' => $unidade->id]);
    $profissional->user->assignRole($role);

    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $profissional->user_id,
        'situacao' => $situacao,
        'fim_em' => null,
    ]);

    return $profissional->fresh();
}

/** Põe um paciente na fila de um profissional, com cor e tempo de espera controlados. */
function enfileirar(
    Unidade $unidade,
    ?Profissional $profissional,
    int $cor,
    int $esperandoHaMinutos,
    string $nome,
    User $autor,
): FilaItem {
    $paciente = Paciente::factory()->create(['nome_completo' => $nome]);

    $atendimento = Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $unidade->id,
        'classificacao_risco_id' => $cor,
        'status' => 'AGUARDANDO_ATENDIMENTO',
        'admitido_em' => now()->subMinutes($esperandoHaMinutos),
    ]);

    return FilaItem::create([
        'atendimento_id' => $atendimento->id,
        'profissional_id' => $profissional?->user_id,
        'classificacao_risco_id' => $cor,
        'situacao' => 'AGUARDANDO',
        'entrou_em' => now()->subMinutes($esperandoHaMinutos),
        'criado_por' => $autor->id,
    ]);
}

// =====================================================================
// RN-10 -- o cenario da doc 5.4.1
// =====================================================================

it('ordena a fila por prioridade e depois por chegada, como na doc 5.4.1', function () {
    /*
     * Cinco pacientes inseridos em ordem de chegada propositalmente INVERSA a
     * prioridade. O resultado esperado esta na tabela da doc 5.4.1: Ana Paula, que
     * chegou por ultimo, e a primeira por ser laranja; e entre os dois verdes o
     * desempate e por ordem de chegada -- 149 min antes de 39 min.
     */
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_AMARELO, 69, 'Carlos Dias', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 39, 'Rita Nunes', $this->autor);
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);

    $fila = app(PainelFilaService::class)->fila($this->medico->user_id);

    expect($fila->pluck('paciente_nome')->all())->toBe([
        'Ana Paula',   // laranja, chegou por ultimo
        'Carlos Dias', // amarelo
        'Jose Lima',   // verde, esperando ha 149 min
        'Rita Nunes',  // verde, esperando ha 39 min
    ]);

    expect($fila->pluck('posicao')->all())->toBe([1, 2, 3, 4]);
});

it('sinaliza o tempo-alvo excedido de cada item', function () {
    // RF-33. Alvos: laranja 10 min, amarelo 60, verde 120.
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);
    enfileirar($this->unidade, $this->medico, V_AMARELO, 69, 'Carlos Dias', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 39, 'Rita Nunes', $this->autor);

    $fila = app(PainelFilaService::class)->fila($this->medico->user_id)->keyBy('paciente_nome');

    expect($fila['Ana Paula']['tempo_alvo_excedido'])->toBeFalse()
        ->and($fila['Carlos Dias']['tempo_alvo_excedido'])->toBeTrue()
        ->and($fila['Jose Lima']['tempo_alvo_excedido'])->toBeTrue()
        ->and($fila['Rita Nunes']['tempo_alvo_excedido'])->toBeFalse();

    // E sugere reavaliacao para quem passou do alvo -- sem mexer na prioridade.
    expect($fila['Jose Lima']['sugere_reavaliacao'])->toBeTrue()
        ->and($fila['Jose Lima']['prioridade_cor'])->toBe('VERDE');
});

// =====================================================================
// Carga ponderada -- doc 7.4
// =====================================================================

it('calcula carga ponderada de 1 laranja + 1 amarelo + 2 verdes igual a 11', function () {
    // A verificacao explicita da doc 7.4: 4 + 3 + 2 + 2 = 11.
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);
    enfileirar($this->unidade, $this->medico, V_AMARELO, 69, 'Carlos Dias', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 39, 'Rita Nunes', $this->autor);

    $carga = app(PainelFilaService::class)
        ->cargas(app(EstimadorEsperaService::class), $this->unidade->id)
        ->firstWhere('profissional_id', $this->medico->user_id);

    expect($carga['carga_ponderada'])->toBe(11)
        ->and($carga['pacientes_aguardando'])->toBe(4)
        // RF-27: contar cabecas e metrica ruim; a composicao por cor vem junto.
        ->and($carga['composicao'])->toBe([
            'VERMELHO' => 0, 'LARANJA' => 1, 'AMARELO' => 1, 'VERDE' => 2, 'AZUL' => 0,
        ]);
});

it('sugere o profissional de menor carga ponderada entre os disponiveis', function () {
    // RF-28. O enfermeiro Joao, com 1 amarelo, tem carga 3 contra os 11 do medico.
    $enfermeiro = criarProfissionalDisponivel($this->unidade, 'enfermeiro', 'enfermeiro_assistencial');

    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);
    enfileirar($this->unidade, $this->medico, V_AMARELO, 69, 'Carlos Dias', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 39, 'Rita Nunes', $this->autor);
    enfileirar($this->unidade, $enfermeiro, V_AMARELO, 19, 'Bebe Oliveira', $this->autor);

    $painel = app(PainelFilaService::class);
    $cargas = $painel->cargas(app(EstimadorEsperaService::class), $this->unidade->id);

    expect($cargas->firstWhere('profissional_id', $enfermeiro->user_id)['carga_ponderada'])->toBe(3)
        ->and($painel->sugerido($cargas))->toBe($enfermeiro->user_id);
});

it('nao sugere profissional fora de plantao', function () {
    $ausente = criarProfissionalDisponivel($this->unidade, 'enfermeiro', 'enfermeiro_assistencial', 'AUSENTE');
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);

    $painel = app(PainelFilaService::class);
    $cargas = $painel->cargas(app(EstimadorEsperaService::class), $this->unidade->id);

    expect($cargas->firstWhere('profissional_id', $ausente->user_id)['disponivel'])->toBeFalse()
        ->and($painel->sugerido($cargas))->toBe($this->medico->user_id);
});

it('conta quantos pacientes serao preteridos por um de maior prioridade', function () {
    // UC-05, passo 6. Um laranja (peso 2) ultrapassa os dois verdes e o amarelo.
    enfileirar($this->unidade, $this->medico, V_AMARELO, 69, 'Carlos Dias', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 39, 'Rita Nunes', $this->autor);

    $painel = app(PainelFilaService::class);

    expect($painel->preteridos($this->medico->user_id, 2))->toBe(3)  // laranja passa todos
        ->and($painel->preteridos($this->medico->user_id, 3))->toBe(2)  // amarelo passa os verdes
        ->and($painel->preteridos($this->medico->user_id, 4))->toBe(0); // verde nao passa ninguem
});

// =====================================================================
// Atribuicao
// =====================================================================

it('atribui reaproveitando o item da fila geral, sem recarimbar entrou_em', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18 14:00:00'));

    $item = enfileirar($this->unidade, null, V_VERDE, 45, 'Sem Dono', $this->autor);
    $entrouEmOriginal = $item->entrou_em;
    $atendimento = $item->atendimento;

    Carbon::setTestNow(Carbon::parse('2026-08-18 15:00:00'));

    $atribuido = app(AtribuirProfissionalAction::class)
        ->execute($atendimento, $this->medico, $this->autor);

    expect($atribuido->id)->toBe($item->id, 'Deveria reaproveitar o mesmo item.')
        ->and($atribuido->profissional_id)->toBe($this->medico->user_id)
        // Os 45 min ja esperados nao sao perdidos.
        ->and($atribuido->entrou_em->equalTo($entrouEmOriginal))->toBeTrue()
        // O responsavel acompanha: e ele que RN-12 reconhece daqui em diante.
        ->and($atendimento->fresh()->profissional_responsavel_id)->toBe($this->medico->user_id);

    Carbon::setTestNow();
});

it('recusa atribuir atendimento sem classificacao de risco', function () {
    $atendimento = Atendimento::factory()->create([
        'unidade_id' => $this->unidade->id,
        'classificacao_risco_id' => null,
    ]);

    expect(fn () => app(AtribuirProfissionalAction::class)
        ->execute($atendimento, $this->medico, $this->autor))
        ->toThrow(FilaInvalidaException::class);
});

it('audita a atribuicao', function () {
    $item = enfileirar($this->unidade, null, V_VERDE, 10, 'Paciente', $this->autor);

    app(AtribuirProfissionalAction::class)->execute($item->atendimento, $this->medico, $this->autor);

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'fila.atribuir',
        'atendimento_id' => $item->atendimento_id,
    ]);
});

// =====================================================================
// Transferencia -- nao penaliza a posicao
// =====================================================================

it('transfere entre filas preservando entrou_em e a posicao', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18 14:00:00'));

    $destino = criarProfissionalDisponivel($this->unidade, 'enfermeiro', 'enfermeiro_assistencial');

    // O transferido espera ha 90 min; na fila de destino ja ha um verde de 30 min.
    $item = enfileirar($this->unidade, $this->medico, V_VERDE, 90, 'Transferido', $this->autor);
    enfileirar($this->unidade, $destino, V_VERDE, 30, 'Ja Estava La', $this->autor);

    $entrouEmOriginal = $item->entrou_em;

    Carbon::setTestNow(Carbon::parse('2026-08-18 15:30:00'));

    $novo = app(TransferirFilaAction::class)->execute(
        atendimento: $item->atendimento,
        destino: $destino,
        autor: $this->autor,
        justificativa: 'Redistribuicao de carga entre profissionais.',
    );

    // Item novo, encadeado ao anterior -- o historico mostra que houve remanejamento.
    expect($novo->id)->not->toBe($item->id)
        ->and($novo->transferido_de_id)->toBe($item->id)
        ->and($novo->justificativa_transferencia)->toContain('Redistribuicao')
        ->and($item->fresh()->situacao)->toBe('TRANSFERIDO')
        // O ponto: `entrou_em` COPIADO, nao recarimbado.
        ->and($novo->entrou_em->equalTo($entrouEmOriginal))->toBeTrue();

    // E a posicao reflete isso: quem esperava ha mais tempo vem primeiro.
    $fila = app(PainelFilaService::class)->fila($destino->user_id);

    expect($fila->pluck('paciente_nome')->all())->toBe(['Transferido', 'Ja Estava La']);

    Carbon::setTestNow();
});

it('recusa transferir sem justificativa', function () {
    $destino = criarProfissionalDisponivel($this->unidade, 'enfermeiro', 'enfermeiro_assistencial');
    $item = enfileirar($this->unidade, $this->medico, V_VERDE, 10, 'Paciente', $this->autor);

    expect(fn () => app(TransferirFilaAction::class)
        ->execute($item->atendimento, $destino, $this->autor, '  '))
        ->toThrow(FilaInvalidaException::class);

    expect($item->fresh()->situacao)->toBe('AGUARDANDO');
});

it('recusa transferir para o mesmo profissional', function () {
    $item = enfileirar($this->unidade, $this->medico, V_VERDE, 10, 'Paciente', $this->autor);

    expect(fn () => app(TransferirFilaAction::class)
        ->execute($item->atendimento, $this->medico, $this->autor, 'Motivo qualquer.'))
        ->toThrow(FilaInvalidaException::class);
});

// =====================================================================
// Estimativa de espera -- media movel de 30 dias
// =====================================================================

it('estima pela media movel do proprio profissional, nao por constante', function () {
    // doc 7.4: "profissionais tem ritmos diferentes, e o paciente merece uma estimativa
    // honesta". Historico do medico: dois verdes de 40 e 60 min -> media 50.
    foreach ([[40, 5], [60, 3]] as [$duracao, $diasAtras]) {
        $item = enfileirar($this->unidade, $this->medico, V_VERDE, 0, "Historico {$duracao}", $this->autor);
        DB::table('fila_item')->where('id', $item->id)->update([
            'situacao' => 'CONCLUIDO',
            'chamado_em' => now()->subDays($diasAtras),
            'saiu_em' => now()->subDays($diasAtras)->addMinutes($duracao),
        ]);
    }

    $estimador = app(EstimadorEsperaService::class);

    expect($estimador->duracaoMedia($this->medico->user_id, V_VERDE))->toBe(50);
});

it('ignora historico fora da janela de 30 dias', function () {
    $item = enfileirar($this->unidade, $this->medico, V_VERDE, 0, 'Antigo', $this->autor);
    DB::table('fila_item')->where('id', $item->id)->update([
        'situacao' => 'CONCLUIDO',
        'chamado_em' => now()->subDays(40),
        'saiu_em' => now()->subDays(40)->addMinutes(200),
    ]);

    // Sem historico na janela, cai no padrao -- nao nos 200 min de 40 dias atras.
    expect(app(EstimadorEsperaService::class)->duracaoMedia($this->medico->user_id, V_VERDE))->toBe(20);
});

it('soma as duracoes da fila para estimar a espera de quem chegar agora', function () {
    // Somar so o numero de pessoas daria a mesma estimativa para uma fila de quatro
    // azuis e uma de quatro laranjas.
    enfileirar($this->unidade, $this->medico, V_VERDE, 10, 'A', $this->autor);
    enfileirar($this->unidade, $this->medico, V_VERDE, 20, 'B', $this->autor);

    // Sem historico: 20 min de padrao por paciente.
    expect(app(EstimadorEsperaService::class)->esperaEstimada($this->medico->user_id))->toBe(40);
});

// =====================================================================
// Telas
// =====================================================================

it('exibe o painel do profissional com a fila ordenada', function () {
    // RF-29
    enfileirar($this->unidade, $this->medico, V_VERDE, 149, 'Jose Lima', $this->autor);
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ana Paula', $this->autor);

    $this->actingAs($this->autor)
        ->get(route('fila.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fila/Index')
            ->has('fila', 2)
            ->where('fila.0.paciente_nome', 'Ana Paula')
            ->where('fila.0.posicao', 1)
        );
});

it('exibe a tela de atribuicao com carga, composicao e sugestao', function () {
    // UC-05, mockup da doc 7.4
    $enfermeiro = criarProfissionalDisponivel($this->unidade, 'enfermeiro', 'enfermeiro_assistencial');
    enfileirar($this->unidade, $this->medico, V_LARANJA, 4, 'Ja Atribuido', $this->autor);

    $novo = enfileirar($this->unidade, null, V_AMARELO, 5, 'Novo Paciente', $this->autor);

    $this->actingAs($this->autor)
        ->get(route('fila.atribuir', $novo->atendimento_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fila/Atribuir')
            ->has('cargas', 2)
            // O enfermeiro, sem fila, e o sugerido.
            ->where('sugerido', $enfermeiro->user_id)
        );
});

it('nega atribuicao a quem nao tem fila.atribuir', function () {
    $tecnico = criarProfissionalDisponivel($this->unidade, 'tecnicoEnfermagem', 'tecnico_enfermagem');
    $item = enfileirar($this->unidade, null, V_VERDE, 10, 'Paciente', $this->autor);

    $this->actingAs($tecnico->user->fresh())
        ->post(route('fila.store', $item->atendimento_id), ['profissional_id' => $this->medico->user_id])
        ->assertForbidden();

    expect($item->fresh()->profissional_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A fila acompanha o ciclo de vida do atendimento
|--------------------------------------------------------------------------
*/

it('tira o paciente da espera quando o atendimento comeca, marcando chamado_em', function () {
    $item = enfileirar($this->unidade, $this->medico, V_VERDE, 20, 'Paciente Chamado', $this->autor);
    $atendimento = Atendimento::find($item->atendimento_id);

    expect($item->situacao)->toBe('AGUARDANDO')
        ->and($item->chamado_em)->toBeNull();

    app(AlterarStatusAction::class)->execute(
        atendimento: $atendimento,
        novoStatus: StatusAtendimento::EmAtendimento,
        autor: $this->autor,
    );

    /*
     * Sem esta sincronizacao o paciente seguia listado na fila enquanto ja estava sendo
     * atendido, e `chamado_em` ficava nulo -- cegando a duracao real do atendimento e a
     * aderencia ao tempo-alvo do Manchester.
     */
    $item->refresh();

    expect($item->situacao)->toBe('EM_ATENDIMENTO')
        ->and($item->chamado_em)->not->toBeNull();

    $naFila = app(PainelFilaService::class)->fila($this->medico->user_id);

    expect(collect($naFila)->pluck('fila_item_id'))->not->toContain($item->id);
});

it('nao reescreve chamado_em nas idas e vindas entre exame e atendimento', function () {
    $item = enfileirar($this->unidade, $this->medico, V_AMARELO, 15, 'Paciente Exame', $this->autor);
    $atendimento = Atendimento::find($item->atendimento_id);
    $acao = app(AlterarStatusAction::class);

    $acao->execute($atendimento, StatusAtendimento::EmAtendimento, $this->autor);
    $chamadoOriginal = $item->fresh()->chamado_em;

    $acao->execute($atendimento->fresh(), StatusAtendimento::AguardandoExame, $this->autor);
    $acao->execute($atendimento->fresh(), StatusAtendimento::EmExame, $this->autor);
    $acao->execute($atendimento->fresh(), StatusAtendimento::EmAtendimento, $this->autor);

    // O paciente foi chamado uma vez. O exame nao o devolve para a fila de espera.
    expect($item->fresh()->chamado_em?->format('Y-m-d H:i:s'))
        ->toBe($chamadoOriginal?->format('Y-m-d H:i:s'));
});

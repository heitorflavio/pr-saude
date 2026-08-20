<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;
use App\Models\Aprazamento;
use App\Models\Atendimento;
use App\Models\ExameSolicitacao;
use App\Models\FilaItem;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoItem;
use App\Models\Profissional;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Ids do ClassificacaoRiscoSeeder. */
const P_VERMELHO = 1;

const P_LARANJA = 2;

const P_AZUL = 5;

beforeEach(function () {
    $this->unidade = Unidade::factory()->create();
    $this->medico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $this->medico->user->assignRole('medico');
});

/** Põe um paciente na fila, com cor e tempo de espera controlados. */
function enfileirarNoPainel(Unidade $unidade, ?Profissional $profissional, int $cor, int $esperaMinutos, string $nome): FilaItem
{
    $paciente = Paciente::factory()->create(['nome_completo' => $nome]);

    $atendimento = Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $unidade->id,
        'classificacao_risco_id' => $cor,
        'status' => StatusAtendimento::AguardandoAtendimento,
        'admitido_em' => now()->subMinutes($esperaMinutos),
    ]);

    return FilaItem::create([
        'atendimento_id' => $atendimento->id,
        'profissional_id' => $profissional?->user_id,
        'classificacao_risco_id' => $cor,
        'situacao' => 'AGUARDANDO',
        'entrou_em' => now()->subMinutes($esperaMinutos),
        'criado_por' => $atendimento->aberto_por,
    ]);
}

function usuarioComRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

// =====================================================================
// Cada bloco depende de permissão -- o servidor não monta o que o usuário
// não pode ver, e o payload é tão visível quanto a tela (doc §14.2).
// =====================================================================

it('a recepção vê a fila e os atendimentos, e não recebe doses nem exames', function () {
    $this->actingAs(usuarioComRole('recepcao'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('painel.fila')
            ->has('painel.atendimentos')
            ->missing('painel.doses')
            ->missing('painel.exames')
            // Sem fila própria: a recepção não recebe pacientes.
            ->missing('painel.minha_fila'));
});

it('o técnico de enfermagem recebe o bloco de doses', function () {
    $this->actingAs(usuarioComRole('tecnico_enfermagem'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('painel.doses')->missing('painel.exames'));
});

it('o laboratório recebe o bloco de exames', function () {
    $this->actingAs(usuarioComRole('laboratorio'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('painel.exames')->missing('painel.fila'));
});

it('o auditor não recebe nenhum bloco operacional', function () {
    // A única permissão do auditor é `auditoria.ler` (doc §2.1): ele consulta a trilha,
    // não conduz o plantão.
    $this->actingAs(usuarioComRole('auditor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('painel.fila')
            ->missing('painel.doses')
            ->missing('painel.exames')
            ->where('contexto.pode.indicadores', true));
});

// =====================================================================
// Os números vêm das views, não de contagem reimplementada em PHP
// =====================================================================

it('conta a fila inteira, os não atribuídos e quem passou do tempo-alvo', function () {
    // Laranja tem alvo de 10 min: 30 minutos de espera excedem. Azul tem 240.
    enfileirarNoPainel($this->unidade, null, P_LARANJA, 30, 'Ana Excedida');
    enfileirarNoPainel($this->unidade, null, P_AZUL, 15, 'Bruno No Alvo');
    enfileirarNoPainel($this->unidade, $this->medico, P_AZUL, 5, 'Carla Atribuída');

    $this->actingAs($this->medico->user->fresh())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('painel.fila.total', 3)
            ->where('painel.fila.sem_profissional', 2)
            // RF-33
            ->where('painel.fila.alem_do_alvo', 1)
            // As cinco cores sempre presentes: a ausência de vermelho é informação.
            ->has('painel.fila.distribuicao', 5)
            ->where('painel.fila.distribuicao.0.cor', 'VERMELHO')
            ->where('painel.fila.distribuicao.0.total', 0)
            ->where('painel.fila.distribuicao.1.total', 1)
            ->where('painel.fila.distribuicao.4.total', 2));
});

it('a lista de aguardando atribuição respeita a ordenação da RN-10', function () {
    // O azul chegou antes, mas o laranja é mais prioritário: prioridade primeiro,
    // ordem de chegada só como desempate.
    enfileirarNoPainel($this->unidade, null, P_AZUL, 120, 'Chegou Primeiro');
    enfileirarNoPainel($this->unidade, null, P_LARANJA, 5, 'Mais Grave');
    enfileirarNoPainel($this->unidade, null, P_VERMELHO, 1, 'Emergência');

    $this->actingAs($this->medico->user->fresh())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('painel.fila.aguardando_atribuicao.0.paciente_nome', 'Emergência')
            ->where('painel.fila.aguardando_atribuicao.1.paciente_nome', 'Mais Grave')
            ->where('painel.fila.aguardando_atribuicao.2.paciente_nome', 'Chegou Primeiro')
            // A espera do azul excedeu o alvo de 240? Não -- 120 min ainda está dentro,
            // e o painel não deve inventar alerta.
            ->where('painel.fila.aguardando_atribuicao.2.tempo_alvo_excedido', false));
});

it('minha fila conta só os pacientes do próprio profissional', function () {
    enfileirarNoPainel($this->unidade, $this->medico, P_LARANJA, 30, 'Meu Paciente');
    enfileirarNoPainel($this->unidade, null, P_AZUL, 5, 'Da Fila Geral');

    $outro = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $outro->user->assignRole('medico');
    enfileirarNoPainel($this->unidade, $outro, P_AZUL, 5, 'De Outro Profissional');

    $this->actingAs($this->medico->user->fresh())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('painel.minha_fila.total', 1)
            ->where('painel.minha_fila.alem_do_alvo', 1)
            ->where('painel.minha_fila.proximos.0.paciente_nome', 'Meu Paciente'));
});

it('conta os atendimentos abertos por status e o movimento do dia', function () {
    // "Hoje" e o dia do servidor (RN-29). Sem congelar o relogio, um `subHours(3)`
    // executado a meia-noite e meia cairia em ontem e o teste falharia por horario.
    $this->travelTo(now()->startOfDay()->addHours(10));

    $paciente = Paciente::factory()->create();
    Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'status' => StatusAtendimento::EmAtendimento,
        'admitido_em' => now()->subHour(),
    ]);
    Atendimento::factory()->finalizado()->create([
        'paciente_id' => Paciente::factory()->create()->user_id,
        'unidade_id' => $this->unidade->id,
        'admitido_em' => now()->subHours(3),
        'finalizado_em' => now()->subMinutes(10),
    ]);

    $this->actingAs($this->medico->user->fresh())->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // O finalizado não conta como aberto, e os terminais não aparecem na lista.
            ->where('painel.atendimentos.ativos', 1)
            ->where('painel.atendimentos.em_atendimento', 1)
            ->where('painel.atendimentos.admitidos_hoje', 2)
            ->where('painel.atendimentos.finalizados_hoje', 1)
            ->has('painel.atendimentos.por_status', 7));
});

it('mostra as doses pendentes com o atraso e a marca de alta vigilância', function () {
    $atendimento = Atendimento::factory()->create([
        'paciente_id' => Paciente::factory()->create(['nome_completo' => 'Dora Medicada'])->user_id,
        'unidade_id' => $this->unidade->id,
        'status' => StatusAtendimento::AguardandoMedicacao,
    ]);
    $item = PrescricaoItem::factory()->create([
        'prescricao_id' => Prescricao::factory()->create(['atendimento_id' => $atendimento->id])->id,
        'medicamento_id' => Medicamento::factory()->create(['alta_vigilancia' => true])->id,
    ]);
    Aprazamento::factory()->atrasada(45)->create(['prescricao_item_id' => $item->id, 'sequencia' => 1]);
    Aprazamento::factory()->create([
        'prescricao_item_id' => $item->id, 'sequencia' => 2,
        'horario_previsto' => now()->addHours(6),
    ]);

    $this->actingAs(usuarioComRole('tecnico_enfermagem'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('painel.doses.pendentes', 2)
            ->where('painel.doses.atrasadas', 1)
            // A atrasada vem primeiro, como no checklist (RF-60).
            ->where('painel.doses.proximas.0.atrasada', true)
            ->where('painel.doses.proximas.0.paciente', 'Dora Medicada')
            // RN-22
            ->where('painel.doses.proximas.0.alta_vigilancia', true));
});

it('separa os exames concluídos que ainda aguardam liberação', function () {
    $atendimento = Atendimento::factory()->create([
        'paciente_id' => Paciente::factory()->create()->user_id,
        'unidade_id' => $this->unidade->id,
    ]);
    ExameSolicitacao::factory()->create(['atendimento_id' => $atendimento->id, 'situacao' => 'SOLICITADO']);
    ExameSolicitacao::factory()->create(['atendimento_id' => $atendimento->id, 'situacao' => 'EM_EXECUCAO']);
    ExameSolicitacao::factory()->create(['atendimento_id' => $atendimento->id, 'situacao' => 'CONCLUIDO']);
    ExameSolicitacao::factory()->create(['atendimento_id' => $atendimento->id, 'situacao' => 'LIBERADO']);

    $this->actingAs(usuarioComRole('laboratorio'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('painel.exames.a_coletar', 1)
            ->where('painel.exames.em_execucao', 1)
            // RN-24: liberado não é pendência; concluído é.
            ->where('painel.exames.aguardando_liberacao', 1));
});

// =====================================================================
// O painel é da equipe
// =====================================================================

it('o paciente autenticado no portal não alcança o painel da equipe', function () {
    $paciente = Paciente::factory()->create();

    $this->actingAs($paciente->user, 'paciente');

    /*
     * `actingAs` com guard troca o guard padrão da aplicação, e o middleware `auth`
     * sem argumento resolve justamente o padrão -- sem esta linha o teste passaria a
     * medir o próprio harness, e não o sistema. Numa requisição real o padrão é `web`,
     * e a sessão do portal vive no guard `paciente`.
     */
    auth()->shouldUse('web');

    // RN-27: o painel é da equipe. O paciente não tem sessão no guard `web`.
    $this->get('/dashboard')->assertRedirect('/login');
});

it('o painel não escreve nada', function () {
    enfileirarNoPainel($this->unidade, $this->medico, P_LARANJA, 30, 'Paciente Em Espera');
    $antes = DB::table('fila_item')->orderBy('id')->get()->toJson();

    $this->actingAs($this->medico->user->fresh())->get('/dashboard')->assertOk();

    // RN-10: a posição é calculada na leitura. Uma leitura do painel não pode ter
    // persistido posição, nem alterado a fila de qualquer outra forma.
    expect(DB::table('fila_item')->orderBy('id')->get()->toJson())->toBe($antes)
        ->and(DB::getSchemaBuilder()->hasColumn('fila_item', 'posicao'))->toBeFalse();
});

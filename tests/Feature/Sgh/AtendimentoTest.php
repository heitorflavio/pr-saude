<?php

declare(strict_types=1);

use App\Actions\Atendimento\AbrirAtendimentoAction;
use App\Actions\Atendimento\AlterarStatusAction;
use App\Actions\Atendimento\FinalizarAtendimentoAction;
use App\Enums\StatusAtendimento;
use App\Events\AtendimentoAberto;
use App\Events\StatusAtendimentoAlterado;
use App\Exceptions\AtendimentoAtivoExistenteException;
use App\Exceptions\DesfechoObrigatorioException;
use App\Exceptions\TransicaoInvalidaException;
use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\Unidade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->unidade = Unidade::factory()->create();

    $this->recepcao = Profissional::factory()->recepcao()->create(['unidade_id' => $this->unidade->id]);
    $this->recepcao->user->assignRole('recepcao');

    $this->medico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $this->medico->user->assignRole('medico');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $this->medico->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);

    $this->autor = $this->recepcao->user->fresh();
});

/** Coloca o atendimento no status pedido sem passar pela Action, para montar cenário. */
function forcarStatus(Atendimento $atendimento, StatusAtendimento $status): Atendimento
{
    $dados = ['status' => $status->value];

    // Os CHECK ck_atend_desfecho e ck_atend_finalizado exigem os dois campos.
    if ($status === StatusAtendimento::Finalizado) {
        $dados['desfecho'] = 'ALTA';
        $dados['finalizado_em'] = now();
    }

    if ($status === StatusAtendimento::Cancelado) {
        $dados['finalizado_em'] = now();
    }

    DB::table('atendimento')->where('id', $atendimento->id)->update($dados);

    return $atendimento->fresh();
}

// =====================================================================
// Abertura -- RF-21 e RN-07
// =====================================================================

it('abre atendimento com numero sequencial por ano e unidade', function () {
    Event::fake([AtendimentoAberto::class]);

    $ano = now()->year;

    $primeiro = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(),
        unidade: $this->unidade,
        autor: $this->autor,
    );

    $segundo = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(),
        unidade: $this->unidade,
        autor: $this->autor,
    );

    expect($primeiro->numero)->toBe("{$ano}-000001")
        ->and($segundo->numero)->toBe("{$ano}-000002")
        ->and($primeiro->status)->toBe(StatusAtendimento::AguardandoTriagem);

    Event::assertDispatched(AtendimentoAberto::class, 2);
});

it('numera globalmente por ano, sem colidir entre unidades', function () {
    // D-34: o schema declara UNIQUE(numero) global e o formato `2026-000148` nao tem
    // componente de unidade -- contar por unidade faria a segunda UPA colidir. O numero
    // e o identificador de recuperacao quando o QR falha; repeti-lo entre unidades seria
    // pior que um contador por unidade.
    $outra = Unidade::factory()->create();
    $ano = now()->year;

    $a = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );
    $b = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $outra, autor: $this->autor,
    );

    expect($a->numero)->toBe("{$ano}-000001")
        ->and($b->numero)->toBe("{$ano}-000002")
        ->and($a->numero)->not->toBe($b->numero);
});

it('grava a primeira linha do historico na abertura', function () {
    // RN-15 / RF-22: sem esta linha a linha do tempo nao teria inicio.
    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );

    $historico = $atendimento->statusHistorico()->first();

    expect($atendimento->statusHistorico()->count())->toBe(1)
        ->and($historico->status_anterior)->toBeNull()
        ->and($historico->status_novo)->toBe('AGUARDANDO_TRIAGEM')
        ->and($historico->alterado_por)->toBe($this->autor->id);
});

it('recusa segundo atendimento ativo do mesmo paciente na mesma unidade', function () {
    // RN-07
    $paciente = Paciente::factory()->create();
    $acao = app(AbrirAtendimentoAction::class);

    $primeiro = $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);

    try {
        $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);
        $this->fail('Deveria ter recusado o segundo atendimento ativo.');
    } catch (AtendimentoAtivoExistenteException $e) {
        // A excecao carrega o atendimento existente: em vez de duplicar, o sistema leva
        // a quem ja esta aberto.
        expect($e->atendimento?->id)->toBe($primeiro->id);
    }

    expect(Atendimento::where('paciente_id', $paciente->user_id)->count())->toBe(1);
});

it('permite o mesmo paciente ativo em unidades diferentes', function () {
    // RN-07 e por paciente E unidade: quem foi transferido de UPA pode ter episodio
    // aberto nas duas durante a transicao.
    $paciente = Paciente::factory()->create();
    $outra = Unidade::factory()->create();
    $acao = app(AbrirAtendimentoAction::class);

    $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);
    $acao->execute(paciente: $paciente, unidade: $outra, autor: $this->autor);

    expect(Atendimento::where('paciente_id', $paciente->user_id)->count())->toBe(2);
});

it('permite novo atendimento apos finalizar o anterior', function () {
    $paciente = Paciente::factory()->create();
    $acao = app(AbrirAtendimentoAction::class);

    $primeiro = $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);
    forcarStatus($primeiro, StatusAtendimento::Finalizado);

    $segundo = $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);

    expect($segundo->id)->not->toBe($primeiro->id)
        ->and($paciente->fresh()->atendimentoAtivo($this->unidade->id)?->id)->toBe($segundo->id);
});

it('traduz a violacao do indice unico quando a corrida escapa da verificacao', function () {
    /*
     * A verificacao em PHP nao e o controle -- o controle e o indice unico
     * `uk_atendimento_ativo` sobre a coluna gerada. Este teste simula a corrida: um
     * concorrente insere o atendimento ativo DEPOIS da nossa verificacao e ANTES da
     * nossa escrita, e o banco recusa a nossa.
     */
    $paciente = Paciente::factory()->create();
    $unidadeId = $this->unidade->id;
    $autorId = $this->autor->id;
    $jaCorreu = false;

    Atendimento::creating(function () use ($paciente, $unidadeId, $autorId, &$jaCorreu) {
        if ($jaCorreu) {
            return;
        }
        $jaCorreu = true;

        // O concorrente, gravado por fora da Action.
        DB::table('atendimento')->insert([
            'uuid' => (string) Str::uuid(),
            'numero' => now()->year.'-999999',
            'paciente_id' => $paciente->user_id,
            'unidade_id' => $unidadeId,
            'status' => 'AGUARDANDO_TRIAGEM',
            'origem' => 'ESPONTANEA',
            'admitido_em' => now(),
            'aberto_por' => $autorId,
        ]);
    });

    expect(fn () => app(AbrirAtendimentoAction::class)->execute(
        paciente: $paciente, unidade: $this->unidade, autor: $this->autor,
    ))->toThrow(AtendimentoAtivoExistenteException::class);

    /*
     * O que este teste prova: a Action TRADUZ a violacao do indice unico para a excecao
     * de dominio, em vez de deixar o erro do MySQL vazar.
     *
     * Nao prova concorrencia real -- sob RefreshDatabase tudo roda em uma transacao, e a
     * insercao do concorrente e revertida junto com a nossa. Que o INDICE recusa e
     * provado em EsquemaTest, escrevendo direto no banco.
     */
    expect(Atendimento::where('paciente_id', $paciente->user_id)->count())->toBe(0);
});

// =====================================================================
// Transicoes -- RN-13
// =====================================================================

it('aceita todas as transicoes legais da doc 6.2 pela Action', function () {
    $acao = app(AlterarStatusAction::class);
    $autor = $this->medico->user->fresh();

    foreach (StatusAtendimento::cases() as $origem) {
        foreach ($origem->transicoesPermitidas() as $destino) {
            $atendimento = forcarStatus(
                Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
                $origem
            );

            $resultado = $acao->execute(
                atendimento: $atendimento,
                novoStatus: $destino,
                autor: $autor,
                desfecho: $destino === StatusAtendimento::Finalizado ? 'ALTA' : null,
            );

            expect($resultado->status)->toBe(
                $destino,
                "{$origem->value} -> {$destino->value} deveria ter passado."
            );
        }
    }
});

it('recusa toda transicao ilegal com TransicaoInvalidaException', function () {
    // As 55 combinacoes que a doc 6.2 nao preve.
    $acao = app(AlterarStatusAction::class);
    $autor = $this->medico->user->fresh();
    $recusadas = 0;

    foreach (StatusAtendimento::cases() as $origem) {
        foreach (StatusAtendimento::cases() as $destino) {
            if ($origem->podeTransitarPara($destino)) {
                continue;
            }

            $atendimento = forcarStatus(
                Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
                $origem
            );

            expect(fn () => $acao->execute($atendimento, $destino, $autor, desfecho: 'ALTA'))
                ->toThrow(TransicaoInvalidaException::class);

            // Nada mudou no banco.
            expect($atendimento->fresh()->status)->toBe($origem);

            $recusadas++;
        }
    }

    expect($recusadas)->toBe(55);
})->group('lento');

it('nao altera o banco quando a transicao e recusada', function () {
    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::AguardandoTriagem
    );
    $historicoAntes = $atendimento->statusHistorico()->count();

    expect(fn () => app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::EmExame, $this->medico->user->fresh()
    ))->toThrow(TransicaoInvalidaException::class);

    expect($atendimento->fresh()->status)->toBe(StatusAtendimento::AguardandoTriagem)
        ->and($atendimento->statusHistorico()->count())->toBe($historicoAntes);
});

// =====================================================================
// Historico e permanencia -- RN-15, RF-39
// =====================================================================

it('acrescenta ao historico sem sobrescrever e calcula a permanencia', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18 14:00:00'));

    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );

    Carbon::setTestNow(Carbon::parse('2026-08-18 14:25:00'));

    app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::EmAtendimento, $this->medico->user->fresh(), 'Chamado pelo medico.'
    );

    $historico = $atendimento->fresh()->statusHistorico()->orderBy('criado_em')->get();

    expect($historico)->toHaveCount(2)
        // RN-15: a primeira linha continua intacta.
        ->and($historico[0]->status_novo)->toBe('AGUARDANDO_TRIAGEM')
        ->and($historico[1]->status_anterior)->toBe('AGUARDANDO_TRIAGEM')
        ->and($historico[1]->status_novo)->toBe('EM_ATENDIMENTO')
        // RF-39: 25 minutos aguardando triagem.
        ->and($historico[1]->permanencia_segundos)->toBe(1500)
        ->and($historico[1]->observacao)->toBe('Chamado pelo medico.');

    Carbon::setTestNow();
});

it('grava primeiro_atendimento_em apenas na primeira entrada em EM_ATENDIMENTO', function () {
    // O tempo e congelado ANTES da abertura: congelar depois faria a referencia ficar no
    // futuro e a permanencia sair negativa.
    Carbon::setTestNow(Carbon::parse('2026-08-18 14:00:00'));

    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );
    $autor = $this->medico->user->fresh();
    $acao = app(AlterarStatusAction::class);

    Carbon::setTestNow(Carbon::parse('2026-08-18 14:10:00'));
    $acao->execute($atendimento, StatusAtendimento::EmAtendimento, $autor);
    $primeiro = $atendimento->fresh()->primeiro_atendimento_em;

    // Sai e volta: o marco do primeiro atendimento nao pode ser reescrito, senao o
    // indicador de tempo ate o primeiro atendimento passa a medir a ultima volta.
    Carbon::setTestNow(Carbon::parse('2026-08-18 15:00:00'));
    $acao->execute($atendimento->fresh(), StatusAtendimento::EmObservacao, $autor);
    $acao->execute($atendimento->fresh(), StatusAtendimento::EmAtendimento, $autor);

    expect($atendimento->fresh()->primeiro_atendimento_em->equalTo($primeiro))->toBeTrue();

    Carbon::setTestNow();
});

// =====================================================================
// Finalizacao -- RN-14
// =====================================================================

it('recusa finalizar sem desfecho', function () {
    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::EmAtendimento
    );

    expect(fn () => app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::Finalizado, $this->medico->user->fresh()
    ))->toThrow(DesfechoObrigatorioException::class);

    expect($atendimento->fresh()->status)->toBe(StatusAtendimento::EmAtendimento);
});

it('recusa desfecho fora do dominio', function () {
    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::EmAtendimento
    );

    expect(fn () => app(FinalizarAtendimentoAction::class)->execute(
        $atendimento, 'CURADO_POR_MILAGRE', $this->medico->user->fresh()
    ))->toThrow(DesfechoObrigatorioException::class);
});

it('finaliza gravando desfecho, horario e encerrando a fila', function () {
    Event::fake([StatusAtendimentoAlterado::class]);

    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::EmAtendimento
    );

    $fila = FilaItem::factory()->create([
        'atendimento_id' => $atendimento->id,
        'situacao' => 'AGUARDANDO',
    ]);

    app(FinalizarAtendimentoAction::class)->execute(
        $atendimento, 'ALTA', $this->medico->user->fresh(), 'Sintomas resolvidos.'
    );

    $atendimento->refresh();

    expect($atendimento->status)->toBe(StatusAtendimento::Finalizado)
        ->and($atendimento->desfecho)->toBe('ALTA')
        ->and($atendimento->finalizado_em)->not->toBeNull()
        // Sem isto o paciente continuaria inflando a carga do profissional que o atendeu.
        ->and($fila->fresh()->situacao)->toBe('CONCLUIDO')
        ->and($fila->fresh()->saiu_em)->not->toBeNull();

    Event::assertDispatched(StatusAtendimentoAlterado::class);
});

it('marca o item de fila como desistencia ao cancelar', function () {
    // doc 6.2: "qualquer nao terminal -> CANCELADO. Encerra fila_item como DESISTENCIA".
    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::AguardandoAtendimento
    );
    $fila = FilaItem::factory()->create([
        'atendimento_id' => $atendimento->id,
        'situacao' => 'AGUARDANDO',
    ]);

    app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::Cancelado, $this->medico->user->fresh(), 'Paciente evadiu.'
    );

    expect($fila->fresh()->situacao)->toBe('DESISTENCIA');
});

it('libera a ativo_key ao finalizar, permitindo novo atendimento', function () {
    // D-07: o mecanismo por tras da RN-07.
    $paciente = Paciente::factory()->create();
    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: $paciente, unidade: $this->unidade, autor: $this->autor,
    );

    expect(DB::table('atendimento')->where('id', $atendimento->id)->value('ativo_key'))
        ->toEqual($paciente->user_id);

    // AGUARDANDO_TRIAGEM nao vai direto a FINALIZADO (doc 6.2): passa por atendimento.
    app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::EmAtendimento, $this->medico->user->fresh()
    );

    app(FinalizarAtendimentoAction::class)->execute(
        $atendimento->fresh(), 'ALTA', $this->medico->user->fresh()
    );

    expect(DB::table('atendimento')->where('id', $atendimento->id)->value('ativo_key'))->toBeNull();
});

// =====================================================================
// Auditoria
// =====================================================================

it('audita abertura e mudanca de status', function () {
    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );

    app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::EmAtendimento, $this->medico->user->fresh()
    );

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'atendimento.abrir',
        'atendimento_id' => $atendimento->id,
    ]);

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'atendimento.alterar_status',
        'atendimento_id' => $atendimento->id,
    ]);
});

// =====================================================================
// Rotas e autorizacao -- RN-12
// =====================================================================

it('nega alteracao de status a tecnico que nao e responsavel nem supervisor', function () {
    // RN-12: a permissao estatica nao basta.
    $tecnico = Profissional::factory()->tecnicoEnfermagem()->create(['unidade_id' => $this->unidade->id]);
    $tecnico->user->assignRole('tecnico_enfermagem');

    $atendimento = forcarStatus(
        Atendimento::factory()->create(['unidade_id' => $this->unidade->id]),
        StatusAtendimento::AguardandoAtendimento
    );

    $this->actingAs($tecnico->user->fresh())
        ->put(route('atendimentos.status', $atendimento->id), ['status' => 'EM_ATENDIMENTO'])
        ->assertForbidden();

    expect($atendimento->fresh()->status)->toBe(StatusAtendimento::AguardandoAtendimento);
});

it('exibe a linha do tempo consolidada', function () {
    // RF-22
    $atendimento = app(AbrirAtendimentoAction::class)->execute(
        paciente: Paciente::factory()->create(), unidade: $this->unidade, autor: $this->autor,
    );
    app(AlterarStatusAction::class)->execute(
        $atendimento, StatusAtendimento::EmAtendimento, $this->medico->user->fresh()
    );

    $this->actingAs($this->medico->user->fresh())
        ->get(route('atendimentos.show', $atendimento->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Atendimentos/Show')
            ->has('linhaDoTempo', 2)
            ->where('linhaDoTempo.0.para', 'Aguardando triagem')
            ->where('linhaDoTempo.1.para', 'Em atendimento')
            // Só as transições legais são oferecidas na tela.
            ->has('transicoesPermitidas', 5)
        );
});

it('oferece uma visao global para retomar atendimentos sem procurar o paciente', function () {
    $antigo = Atendimento::factory()->create([
        'unidade_id' => $this->unidade->id,
        'admitido_em' => now()->subHours(2),
    ]);
    $recente = Atendimento::factory()->create([
        'unidade_id' => $this->unidade->id,
        'admitido_em' => now()->subHour(),
    ]);
    $encerrado = Atendimento::factory()->finalizado()->create([
        'unidade_id' => $this->unidade->id,
        'finalizado_em' => now(),
    ]);

    $this->actingAs($this->medico->user->fresh())
        ->get(route('atendimentos.geral'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Atendimentos/Geral')
            ->has('emAndamento', 2)
            ->where('emAndamento.0.id', $antigo->id)
            ->where('emAndamento.1.id', $recente->id)
            ->where('emAndamento.0.paciente_nome', $antigo->paciente->nomeExibicao())
            ->has('recentes', 1)
            ->where('recentes.0.id', $encerrado->id)
        );
});

it('separa atendimentos em andamento dos finalizados', function () {
    // RF-18
    $paciente = Paciente::factory()->create();
    $acao = app(AbrirAtendimentoAction::class);

    $antigo = $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);
    forcarStatus($antigo, StatusAtendimento::Finalizado);

    $acao->execute(paciente: $paciente, unidade: $this->unidade, autor: $this->autor);

    $this->actingAs($this->medico->user->fresh())
        ->get(route('atendimentos.index', $paciente->user_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Atendimentos/Index')
            ->has('emAndamento', 1)
            ->has('finalizados', 1)
        );
});

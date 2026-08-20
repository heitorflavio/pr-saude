<?php

declare(strict_types=1);

use App\Actions\Prontuario\RegistrarDiagnosticoAction;
use App\Actions\Prontuario\RegistrarNotaClinicaAction;
use App\Actions\Prontuario\RetificarRegistroAction;
use App\Enums\StatusAtendimento;
use App\Enums\TipoRegistroClinico;
use App\Events\IntegridadeProntuarioViolada;
use App\Events\RegistroRetificado;
use App\Exceptions\DiagnosticoInvalidoException;
use App\Exceptions\RegistroClinicoInvalidoException;
use App\Exceptions\RegistroImutavelException;
use App\Jobs\VerificarIntegridadeProntuarioJob;
use App\Models\Atendimento;
use App\Models\AuditoriaLog;
use App\Models\Cid10;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\RegistroClinico;
use App\Models\Unidade;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Prontuario\HashEncadeadoService;
use App\Services\Prontuario\ProntuarioConsolidadoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->unidade = Unidade::factory()->create();

    $this->medico = Profissional::factory()->medico()->create([
        'unidade_id' => $this->unidade->id,
        'nome_completo' => 'Ana Costa',
        'conselho_tipo' => 'CRM',
        'conselho_numero' => '123456',
        'conselho_uf' => 'SP',
    ]);
    $this->medico->user->assignRole('medico');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $this->medico->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);
    $this->autor = $this->medico->user->fresh();

    $this->paciente = Paciente::factory()->create(['nome_completo' => 'João da Silva']);

    $this->atendimento = Atendimento::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'profissional_responsavel_id' => $this->medico->user_id,
        'status' => StatusAtendimento::EmAtendimento,
    ]);
});

function notaSoap(array $sobrescreve = []): array
{
    return [
        'subjetivo' => 'Dor abdominal em cólica há 6 h, náusea, sem febre.',
        'objetivo' => 'PA 130/85, FC 92, abdome doloroso à palpação em FID, Blumberg +.',
        'avaliacao' => 'Abdome agudo inflamatório. HD: colecistite aguda.',
        'plano' => 'Hemograma, PCR, US de abdome. Dipirona 1 g IV. Reavaliar em 1 h.',
        ...$sobrescreve,
    ];
}

/*
|--------------------------------------------------------------------------
| RF-45 / doc §9.2 — a nota SOAP
|--------------------------------------------------------------------------
*/

it('registra a nota clínica com o SOAP em quatro colunas separadas', function () {
    $registro = app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
    );

    // A separação é o ponto: "quais hipóteses foram levantadas?" precisa ser uma consulta
    // sobre a coluna `avaliacao`, não um projeto de mineração de texto (doc §9.2).
    expect($registro->subjetivo)->toContain('cólica')
        ->and($registro->objetivo)->toContain('Blumberg')
        ->and($registro->avaliacao)->toContain('colecistite')
        ->and($registro->plano)->toContain('US de abdome')
        ->and($registro->conteudo_livre)->toBeNull();

    expect(DB::table('registro_clinico')->where('id', $registro->id)->exists())->toBeTrue();
});

it('grava snapshot do nome e do conselho do autor, que não acompanha mudança de cadastro', function () {
    $registro = app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
    );

    expect($registro->autor_nome)->toBe('Ana Costa')
        ->and($registro->autor_conselho)->toBe('CRM/SP 123456');

    // O cadastro muda depois -- o documento assinado não muda junto (doc §9.3).
    $this->medico->update(['nome_completo' => 'Ana Costa Pereira', 'conselho_uf' => 'RJ']);

    expect($registro->fresh()->autor_nome)->toBe('Ana Costa')
        ->and($registro->fresh()->autor_conselho)->toBe('CRM/SP 123456');
});

it('recusa nota sem nenhum conteúdo', function () {
    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: ['subjetivo' => '   ', 'plano' => ''],
    );
})->throws(RegistroClinicoInvalidoException::class);

it('recusa SOAP em tipo de registro que não usa SOAP', function () {
    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::SumarioAlta,
        autor: $this->autor,
        conteudo: ['avaliacao' => 'Alta melhorado.'],
    );
})->throws(RegistroClinicoInvalidoException::class);

it('recusa adendo criado como nota avulsa: ele nasce da retificação', function () {
    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::Adendo,
        autor: $this->autor,
        conteudo: ['conteudo_livre' => 'Correção.'],
    );
})->throws(RegistroClinicoInvalidoException::class);

it('recusa registro em atendimento encerrado', function () {
    // ck_atend_finalizado exige desfecho E `finalizado_em` -- RN-14 vale no banco.
    $this->atendimento->update([
        'status' => StatusAtendimento::Finalizado,
        'desfecho' => 'ALTA',
        'finalizado_em' => now(),
    ]);

    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento->fresh(),
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
    );
})->throws(RegistroClinicoInvalidoException::class);

/*
|--------------------------------------------------------------------------
| RF-49 / RN-16 / RN-17 — imutabilidade
|--------------------------------------------------------------------------
*/

it('impede UPDATE em registro clínico já persistido', function () {
    $registro = RegistroClinico::factory()->create(['atendimento_id' => $this->atendimento->id]);

    expect(fn () => $registro->update(['avaliacao' => 'outra coisa']))
        ->toThrow(RegistroImutavelException::class);
});

it('impede DELETE de registro clínico', function () {
    $registro = RegistroClinico::factory()->create(['atendimento_id' => $this->atendimento->id]);

    expect(fn () => $registro->delete())->toThrow(RegistroImutavelException::class);
    expect(fn () => $registro->forceDelete())->toThrow(RegistroImutavelException::class);
});

/*
|--------------------------------------------------------------------------
| RF-50 / doc §9.3 — retificação por adendo
|--------------------------------------------------------------------------
*/

it('retifica criando adendo e o conteúdo original permanece inalterado', function () {
    Event::fake([RegistroRetificado::class]);

    $original = app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
    );

    $antes = DB::table('registro_clinico')->where('id', $original->id)->first();

    $adendo = app(RetificarRegistroAction::class)->execute(
        original: $original,
        autor: $this->autor,
        motivo: 'Correção de hipótese diagnóstica após ultrassom.',
        conteudoCorrigido: [
            'avaliacao' => 'US sem sinais de colecistite. Apêndice espessado. HD retificada: apendicite aguda.',
            'plano' => 'Avaliação da cirurgia geral.',
        ],
    );

    $depois = DB::table('registro_clinico')->where('id', $original->id)->first();

    /*
     * O ponto de RF-50: a hipótese errada permanece visível. Em sindicância, o que se
     * avalia é se a conduta foi razoável DIANTE DA INFORMAÇÃO DISPONÍVEL NAQUELE
     * MOMENTO -- e isso só é reconstituível se o registro original sobreviver intacto.
     */
    expect((array) $depois)->toBe((array) $antes)
        ->and($depois->avaliacao)->toContain('colecistite');

    expect($adendo->tipo)->toBe(TipoRegistroClinico::Adendo)
        ->and($adendo->registro_retificado_id)->toBe($original->id)
        ->and($adendo->motivo_retificacao)->toContain('ultrassom')
        ->and($adendo->atendimento_id)->toBe($original->atendimento_id);

    expect($original->fresh()->foiRetificado())->toBeTrue();

    Event::assertDispatched(RegistroRetificado::class);
});

it('exige motivo na retificação', function () {
    $original = RegistroClinico::factory()->create(['atendimento_id' => $this->atendimento->id]);

    app(RetificarRegistroAction::class)->execute(
        original: $original,
        autor: $this->autor,
        motivo: '   ',
        conteudoCorrigido: ['avaliacao' => 'nova'],
    );
})->throws(RegistroClinicoInvalidoException::class);

it('recusa adendo de adendo', function () {
    $original = RegistroClinico::factory()->create(['atendimento_id' => $this->atendimento->id]);

    $adendo = app(RetificarRegistroAction::class)->execute(
        original: $original,
        autor: $this->autor,
        motivo: 'Correção inicial.',
        conteudoCorrigido: ['avaliacao' => 'nova'],
    );

    app(RetificarRegistroAction::class)->execute(
        original: $adendo,
        autor: $this->autor,
        motivo: 'Correção da correção.',
        conteudoCorrigido: ['avaliacao' => 'outra'],
    );
})->throws(RegistroClinicoInvalidoException::class);

it('o CHECK do banco recusa adendo sem original e sem motivo', function () {
    // Camada 2 da imutabilidade: mesmo escrevendo direto no banco, ck_registro_adendo
    // impede o adendo órfão.
    expect(fn () => DB::table('registro_clinico')->insert([
        'uuid' => (string) Str::uuid(),
        'atendimento_id' => $this->atendimento->id,
        'tipo' => 'ADENDO',
        'conteudo_livre' => 'Correção solta.',
        'sigiloso' => 0,
        'autor_id' => $this->medico->user_id,
        'autor_nome' => 'Ana Costa',
        'hash_conteudo' => str_repeat('a', 64),
        'criado_em' => now(),
    ]))->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| doc §9.4 — encadeamento de hash
|--------------------------------------------------------------------------
*/

it('encadeia o hash: cada registro carrega o hash do anterior do mesmo atendimento', function () {
    $acao = app(RegistrarNotaClinicaAction::class);

    $primeiro = $acao->execute($this->atendimento, TipoRegistroClinico::Anamnese, $this->autor, notaSoap());
    $segundo = $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap(['plano' => 'Reavaliar em 2 h.']));

    expect($primeiro->hash_anterior)->toBeNull()
        ->and($segundo->hash_anterior)->toBe($primeiro->hash_conteudo)
        ->and($segundo->hash_conteudo)->not->toBe($primeiro->hash_conteudo);

    expect(app(HashEncadeadoService::class)->verificarCadeia($this->atendimento->id)['integra'])->toBeTrue();
});

it('a cadeia de um atendimento não se mistura com a de outro', function () {
    $outro = Atendimento::factory()->create([
        'paciente_id' => Paciente::factory()->create()->user_id,
        'unidade_id' => $this->unidade->id,
        'status' => StatusAtendimento::EmAtendimento,
    ]);

    $acao = app(RegistrarNotaClinicaAction::class);
    $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());
    $doOutro = $acao->execute($outro, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    expect($doOutro->hash_anterior)->toBeNull();
});

it('detecta alteração feita por fora da aplicação como CONTEUDO_ALTERADO', function () {
    $acao = app(RegistrarNotaClinicaAction::class);
    $acao->execute($this->atendimento, TipoRegistroClinico::Anamnese, $this->autor, notaSoap());
    $alvo = $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());
    $acao->execute($this->atendimento, TipoRegistroClinico::Observacao, $this->autor, ['conteudo_livre' => 'Paciente estável.']);

    /*
     * A adulteração que interessa é justamente esta: quem tem acesso administrativo ao
     * SGBD escapa das camadas 1 (model) e 2 (CHECK). O UPDATE cru é o cenário real de
     * ameaça -- por isso o teste o executa de verdade em vez de simular.
     */
    DB::table('registro_clinico')
        ->where('id', $alvo->id)
        ->update(['avaliacao' => 'Hipótese reescrita depois do evento adverso.']);

    $resultado = app(HashEncadeadoService::class)->verificarCadeia($this->atendimento->id);

    expect($resultado['integra'])->toBeFalse()
        ->and(collect($resultado['quebras'])->firstWhere('id', $alvo->id)['motivo'])->toBe('CONTEUDO_ALTERADO');
});

it('detecta remoção de registro do meio da cadeia como ELO_ROMPIDO', function () {
    $acao = app(RegistrarNotaClinicaAction::class);
    $acao->execute($this->atendimento, TipoRegistroClinico::Anamnese, $this->autor, notaSoap());
    $meio = $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());
    $ultimo = $acao->execute($this->atendimento, TipoRegistroClinico::Observacao, $this->autor, ['conteudo_livre' => 'Paciente estável.']);

    DB::table('registro_clinico')->where('id', $meio->id)->delete();

    $resultado = app(HashEncadeadoService::class)->verificarCadeia($this->atendimento->id);

    // O elo se rompe no registro seguinte: o `hash_anterior` dele aponta para algo que
    // não está mais lá.
    expect($resultado['integra'])->toBeFalse()
        ->and(collect($resultado['quebras'])->firstWhere('id', $ultimo->id)['motivo'])->toBe('ELO_ROMPIDO');
});

it('a adulteração não é mascarada nos registros seguintes', function () {
    $acao = app(RegistrarNotaClinicaAction::class);
    $alvo = $acao->execute($this->atendimento, TipoRegistroClinico::Anamnese, $this->autor, notaSoap());
    $seguinte = $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    DB::table('registro_clinico')->where('id', $alvo->id)->update(['plano' => 'reescrito']);

    $quebras = collect(app(HashEncadeadoService::class)->verificarCadeia($this->atendimento->id)['quebras']);

    // Só o registro adulterado acusa; o seguinte continua íntegro porque seu
    // `hash_anterior` bate com o hash GRAVADO no anterior, não com o recalculado.
    expect($quebras->where('id', $alvo->id))->toHaveCount(1)
        ->and($quebras->where('id', $seguinte->id))->toHaveCount(0);
});

it('o job agendado registra a quebra em auditoria e emite evento', function () {
    Event::fake([IntegridadeProntuarioViolada::class]);

    $acao = app(RegistrarNotaClinicaAction::class);
    $alvo = $acao->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    DB::table('registro_clinico')->where('id', $alvo->id)->update(['avaliacao' => 'reescrito']);

    app(VerificarIntegridadeProntuarioJob::class, ['atendimentoId' => $this->atendimento->id])
        ->handle(app(HashEncadeadoService::class), app(AuditoriaService::class));

    expect(AuditoriaLog::where('acao', 'prontuario.integridade_violada')
        ->where('atendimento_id', $this->atendimento->id)
        ->exists())->toBeTrue();

    Event::assertDispatched(IntegridadeProntuarioViolada::class);
});

it('o job não alarma quando a cadeia está íntegra', function () {
    Event::fake([IntegridadeProntuarioViolada::class]);

    app(RegistrarNotaClinicaAction::class)
        ->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    app(VerificarIntegridadeProntuarioJob::class, ['atendimentoId' => $this->atendimento->id])
        ->handle(app(HashEncadeadoService::class), app(AuditoriaService::class));

    Event::assertNotDispatched(IntegridadeProntuarioViolada::class);
});

/*
|--------------------------------------------------------------------------
| RF-46 — diagnóstico com CID-10
|--------------------------------------------------------------------------
*/

it('registra diagnóstico com CID-10 e natureza', function () {
    $codigo = Cid10::query()->value('codigo');

    $diagnostico = app(RegistrarDiagnosticoAction::class)->execute(
        atendimento: $this->atendimento,
        cid10Codigo: mb_strtolower((string) $codigo),
        autor: $this->autor,
        natureza: 'DEFINITIVO',
        principal: true,
    );

    expect($diagnostico->cid10_codigo)->toBe($codigo)
        ->and($diagnostico->natureza)->toBe('DEFINITIVO')
        ->and($diagnostico->principal)->toBeTrue()
        ->and($diagnostico->registrado_por)->toBe($this->medico->user_id);
});

it('recusa CID-10 inexistente', function () {
    app(RegistrarDiagnosticoAction::class)->execute(
        atendimento: $this->atendimento,
        cid10Codigo: 'Z99.9',
        autor: $this->autor,
    );
})->throws(DiagnosticoInvalidoException::class);

it('recusa suspeita marcada como diagnóstico principal', function () {
    app(RegistrarDiagnosticoAction::class)->execute(
        atendimento: $this->atendimento,
        cid10Codigo: (string) Cid10::query()->value('codigo'),
        autor: $this->autor,
        natureza: 'SUSPEITA',
        principal: true,
    );
})->throws(DiagnosticoInvalidoException::class);

it('recusa segundo diagnóstico principal no mesmo atendimento', function () {
    $codigos = Cid10::query()->limit(2)->pluck('codigo');
    $acao = app(RegistrarDiagnosticoAction::class);

    $acao->execute($this->atendimento, (string) $codigos[0], $this->autor, 'DEFINITIVO', true);
    $acao->execute($this->atendimento, (string) $codigos[1], $this->autor, 'DEFINITIVO', true);
})->throws(DiagnosticoInvalidoException::class);

/*
|--------------------------------------------------------------------------
| doc §9.6 — sigilo, e RF-51 — consolidado
|--------------------------------------------------------------------------
*/

it('o consolidado atravessa todos os atendimentos do paciente', function () {
    app(RegistrarNotaClinicaAction::class)
        ->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    // Episódio anterior, já encerrado: é justamente ele que a visão por atendimento
    // esconde e o consolidado precisa mostrar (RF-51).
    $anterior = Atendimento::factory()->create([
        'paciente_id' => $this->paciente->user_id,
        'unidade_id' => $this->unidade->id,
        'status' => StatusAtendimento::Finalizado,
        'desfecho' => 'ALTA',
        'admitido_em' => now()->subDays(10),
        'finalizado_em' => now()->subDays(10)->addHours(3),
    ]);
    RegistroClinico::factory()->create([
        'atendimento_id' => $anterior->id,
        'avaliacao' => 'Cefaleia tensional.',
    ]);

    $episodios = app(ProntuarioConsolidadoService::class)->episodios($this->paciente->fresh(), $this->autor);

    expect($episodios)->toHaveCount(2)
        ->and($episodios[0]['registros'])->toHaveCount(1)
        ->and($episodios[1]['registros'][0]['avaliacao'])->toBe('Cefaleia tensional.');
});

it('a linha do tempo marca o registro retificado e aponta o adendo', function () {
    $original = app(RegistrarNotaClinicaAction::class)
        ->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    app(RetificarRegistroAction::class)->execute(
        original: $original,
        autor: $this->autor,
        motivo: 'Correção após ultrassom.',
        conteudoCorrigido: ['avaliacao' => 'Apendicite aguda.'],
    );

    $linha = app(ProntuarioConsolidadoService::class)->linhaDoTempo($this->atendimento, $this->autor);

    expect($linha)->toHaveCount(2)
        ->and($linha[0]['retificado'])->toBeTrue()
        ->and($linha[0]['retificado_por'][0]['motivo'])->toBe('Correção após ultrassom.')
        ->and($linha[1]['retifica'])->toBe($original->id);
});

it('omite o registro sigiloso de quem não pode ler nota médica, sem indicar que existe', function () {
    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
        sigiloso: true,
    );

    $tecnico = Profissional::factory()->create(['unidade_id' => $this->unidade->id, 'categoria' => 'TECNICO_ENFERMAGEM']);
    $tecnico->user->assignRole('tecnico_enfermagem');

    $linha = app(ProntuarioConsolidadoService::class)
        ->linhaDoTempo($this->atendimento, $tecnico->user->fresh());

    // doc §9.6: o item some por inteiro. Exibir "1 registro oculto" seria pior que
    // omitir -- cria ansiedade sem informação.
    expect($linha)->toBeEmpty();
});

it('marcar como sigiloso é auditado', function () {
    app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
        sigiloso: true,
    );

    $log = AuditoriaLog::where('acao', 'prontuario.criar')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->dados_depois['sigiloso'])->toBeTrue();
});

it('o sigilo do original acompanha o adendo', function () {
    $original = app(RegistrarNotaClinicaAction::class)->execute(
        atendimento: $this->atendimento,
        tipo: TipoRegistroClinico::EvolucaoMedica,
        autor: $this->autor,
        conteudo: notaSoap(),
        sigiloso: true,
    );

    $adendo = app(RetificarRegistroAction::class)->execute(
        original: $original,
        autor: $this->autor,
        motivo: 'Correção após exame.',
        conteudoCorrigido: ['avaliacao' => 'Revisado.'],
    );

    // Retificar não é o caminho para tornar público o que o médico decidiu não exibir.
    expect($adendo->sigiloso)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Rotas — a ausência é a garantia (RN-16, RN-17)
|--------------------------------------------------------------------------
*/

it('não existe rota de UPDATE nem de DELETE para registro clínico', function () {
    $rotas = collect(Route::getRoutes())->filter(
        fn ($rota) => str_contains($rota->uri(), 'registros')
    );

    expect($rotas)->not->toBeEmpty();

    foreach ($rotas as $rota) {
        expect(array_intersect($rota->methods(), ['PUT', 'PATCH', 'DELETE']))->toBeEmpty();
    }
});

it('registra a nota pela rota e devolve o prontuário atualizado', function () {
    $resposta = $this->actingAs($this->autor)->post(
        route('prontuario.store', $this->atendimento),
        ['tipo' => 'EVOLUCAO_MEDICA', ...notaSoap()],
    );

    $resposta->assertRedirect();

    expect(RegistroClinico::where('atendimento_id', $this->atendimento->id)->count())->toBe(1);
});

it('a rota recusa tipo ADENDO como nota avulsa', function () {
    $this->actingAs($this->autor)
        ->post(route('prontuario.store', $this->atendimento), [
            'tipo' => 'ADENDO',
            'conteudo_livre' => 'Correção.',
        ])
        ->assertSessionHasErrors('tipo');
});

it('o técnico de enfermagem não registra nota médica', function () {
    $tecnico = Profissional::factory()->create(['unidade_id' => $this->unidade->id, 'categoria' => 'TECNICO_ENFERMAGEM']);
    $tecnico->user->assignRole('tecnico_enfermagem');
    $this->atendimento->update(['profissional_responsavel_id' => $tecnico->user_id]);

    $this->actingAs($tecnico->user->fresh())
        ->post(route('prontuario.store', $this->atendimento), [
            'tipo' => 'EVOLUCAO_MEDICA',
            ...notaSoap(),
        ])
        ->assertForbidden();
});

it('exporta o prontuário do atendimento em PDF', function () {
    app(RegistrarNotaClinicaAction::class)
        ->execute($this->atendimento, TipoRegistroClinico::EvolucaoMedica, $this->autor, notaSoap());

    $resposta = $this->actingAs($this->autor)->get(route('prontuario.pdf', $this->atendimento));

    $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($resposta->getContent())->toStartWith('%PDF');
});

it('a exportação em PDF é auditada como leitura', function () {
    $this->actingAs($this->autor)->get(route('prontuario.pdf', $this->atendimento))->assertOk();

    expect(AuditoriaLog::where('acao', 'prontuario.exportar_pdf')->exists())->toBeTrue();
});

it('o consolidado exige vínculo assistencial e registra a leitura', function () {
    $this->actingAs($this->autor)
        ->get(route('prontuario.consolidado', $this->paciente))
        ->assertOk();

    expect(AuditoriaLog::where('acao', 'prontuario.ler_consolidado')->exists())->toBeTrue();
});

it('sem vínculo assistencial, o prontuário do atendimento exige justificativa (RN-28)', function () {
    $outroMedico = Profissional::factory()->medico()->create(['unidade_id' => $this->unidade->id]);
    $outroMedico->user->assignRole('medico');
    ProfissionalDisponibilidade::factory()->create([
        'profissional_id' => $outroMedico->user_id,
        'situacao' => 'DISPONIVEL',
        'fim_em' => null,
    ]);

    // O middleware `vinculo` passou a resolver o paciente também pelo atendimento: sem
    // isso, `atendimentos/{atendimento}/prontuario` -- a rota que o plantão usa o dia
    // inteiro -- ficaria fora do break the glass.
    $this->actingAs($outroMedico->user->fresh())
        ->get(route('prontuario.show', $this->atendimento))
        ->assertForbidden();

    expect(AuditoriaLog::where('acao', 'prontuario.quebra_sigilo.negada')->exists())->toBeTrue();
});

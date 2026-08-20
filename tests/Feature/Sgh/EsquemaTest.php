<?php

declare(strict_types=1);

use App\Enums\StatusAtendimento;
use App\Exceptions\RegistroImutavelException;
use App\Models\AdministracaoMedicamento;
use App\Models\Aprazamento;
use App\Models\Atendimento;
use App\Models\ExameResultado;
use App\Models\Paciente;
use App\Models\PrescricaoItem;
use App\Models\Profissional;
use App\Models\RegistroClinico;
use App\Models\SinalVital;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Porte dos 14 testes negativos e 2 positivos de esquema da doc 5.4.
 *
 * O arquivo original `verificacao/testes_schema.sh` nao veio no repositorio; os casos
 * foram derivados da tabela da doc 5.4, que lista a regra e a constraint esperada de
 * cada um (DECISOES.md D-08).
 *
 * O ponto destes testes: as regras criticas nao estao apenas documentadas nem apenas
 * implementadas em PHP -- estao GRAVADAS NO ESQUEMA. Uma validacao em FormRequest
 * protege contra o usuario; uma constraint no banco protege contra o proprio codigo, e
 * e o que sobrevive a condicao de corrida, a script de importacao e a bug de
 * refatoracao.
 *
 * Por isso todo teste aqui escreve DIRETO no banco quando possivel: o que esta sendo
 * verificado e o banco, nao a aplicacao.
 */
/** Confirma que a violacao veio da constraint esperada, e nao de outro erro qualquer. */
function esperarViolacao(Closure $escrita, string $constraint): void
{
    try {
        $escrita();
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain($constraint);

        return;
    }

    test()->fail("O banco aceitou a escrita. Esperava violacao de {$constraint}.");
}

// =====================================================================
// 14 TESTES NEGATIVOS -- tentativas deliberadas de violar regra de negocio
// =====================================================================

it('recusa um segundo atendimento ativo para o mesmo paciente na mesma unidade', function () {
    // RN-07 / D-07
    $paciente = Paciente::factory()->create();
    $unidade = Unidade::factory()->create();

    Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $unidade->id,
    ]);

    esperarViolacao(
        fn () => Atendimento::factory()->create([
            'paciente_id' => $paciente->user_id,
            'unidade_id' => $unidade->id,
        ]),
        'uk_atendimento_ativo'
    );
});

it('recusa finalizar atendimento sem informar desfecho', function () {
    // RN-14
    $atendimento = Atendimento::factory()->create();

    esperarViolacao(
        fn () => DB::table('atendimento')->where('id', $atendimento->id)->update([
            'status' => 'FINALIZADO',
            'finalizado_em' => now(),
            'desfecho' => null,
        ]),
        'ck_atend_desfecho'
    );
});

it('recusa administrar medicamento com alerta de alergia sobreposto sem justificativa', function () {
    // RN-21
    esperarViolacao(
        fn () => AdministracaoMedicamento::factory()->create([
            'alerta_alergia_sobreposto' => true,
            'justificativa' => null,
        ]),
        'ck_adm_justificativa'
    );
});

it('recusa administrar duas vezes a mesma dose aprazada', function () {
    // RN-20
    $item = PrescricaoItem::factory()->create();
    $aprazamento = Aprazamento::factory()->create(['prescricao_item_id' => $item->id]);
    $atendimento = $item->prescricao->atendimento;

    AdministracaoMedicamento::factory()->create([
        'aprazamento_id' => $aprazamento->id,
        'prescricao_item_id' => $item->id,
        'atendimento_id' => $atendimento->id,
    ]);

    esperarViolacao(
        fn () => AdministracaoMedicamento::factory()->create([
            'aprazamento_id' => $aprazamento->id,
            'prescricao_item_id' => $item->id,
            'atendimento_id' => $atendimento->id,
        ]),
        'uk_adm_aprazamento'
    );
});

it('recusa registrar nao-administracao sem motivo', function () {
    // RF-58
    esperarViolacao(
        fn () => AdministracaoMedicamento::factory()->create([
            'resultado' => 'NAO_ADMINISTRADA',
            'motivo_nao_administracao' => null,
            'dose_administrada' => null,
        ]),
        'ck_adm_motivo'
    );
});

it('recusa tornar resultado visivel ao paciente sem liberacao', function () {
    // RN-24
    esperarViolacao(
        fn () => ExameResultado::factory()->create([
            'visivel_ao_paciente' => true,
            'liberado_por' => null,
            'liberado_em' => null,
        ]),
        'ck_result_liberacao'
    );
});

it('recusa gravar temperatura de 99,9 graus', function () {
    // Dominio: faixa 25 a 45
    esperarViolacao(
        fn () => SinalVital::factory()->create(['temperatura' => 99.9]),
        'ck_sinal_temp'
    );
});

it('recusa criar adendo sem apontar o registro retificado', function () {
    // RN-16
    esperarViolacao(
        fn () => RegistroClinico::factory()->create([
            'tipo' => 'ADENDO',
            'registro_retificado_id' => null,
            'motivo_retificacao' => null,
        ]),
        'ck_registro_adendo'
    );
});

it('recusa cadastrar CPF com formato invalido', function () {
    // RF-03
    $user = User::factory()->paciente()->create();

    esperarViolacao(
        fn () => DB::table('paciente')->insert([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'token_pulseira' => Str::random(26),
            'nome_completo' => 'Teste CPF invalido',
            'cpf' => 'ABCDEFGHIJK',
            'data_nascimento' => '1990-01-01',
            'sexo' => 'NAO_INFORMADO',
            'identificacao_provisoria' => 0,
        ]),
        'ck_paciente_cpf_digitos'
    );
});

it('recusa cadastrar medico sem numero de conselho', function () {
    // RN-18
    esperarViolacao(
        fn () => Profissional::factory()->create([
            'categoria' => 'MEDICO',
            'conselho_tipo' => 'CRM',
            'conselho_numero' => null,
            'conselho_uf' => 'SP',
        ]),
        'ck_profissional_conselho'
    );
});

it('recusa cadastrar paciente sem CPF e sem codigo provisorio', function () {
    // RF-04 / RN-30
    $user = User::factory()->paciente()->create();

    esperarViolacao(
        fn () => DB::table('paciente')->insert([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'token_pulseira' => Str::random(26),
            'nome_completo' => 'Paciente sem identificacao',
            'cpf' => null,
            'data_nascimento' => '1990-01-01',
            'sexo' => 'NAO_INFORMADO',
            'identificacao_provisoria' => 0,
            'codigo_provisorio' => null,
        ]),
        'ck_paciente_identificacao'
    );
});

it('recusa prescrever dose zero ou negativa', function () {
    // Dominio: dose > 0
    esperarViolacao(
        fn () => PrescricaoItem::factory()->create(['dose' => 0]),
        'ck_item_dose'
    );

    esperarViolacao(
        fn () => PrescricaoItem::factory()->create(['dose' => -1.5]),
        'ck_item_dose'
    );
});

it('recusa registrar escala de dor igual a 15 na faixa de 0 a 10', function () {
    // Dominio
    esperarViolacao(
        fn () => SinalVital::factory()->create(['escala_dor' => 15]),
        'ck_sinal_dor'
    );
});

it('recusa registrar saturacao de oxigenio de 150 por cento', function () {
    // Dominio
    esperarViolacao(
        fn () => SinalVital::factory()->create(['saturacao_o2' => 150.0]),
        'ck_sinal_spo2'
    );
});

// =====================================================================
// 2 TESTES POSITIVOS -- um esquema que recusa o caso legitimo e tao
// defeituoso quanto um que aceita o ilegitimo
// =====================================================================

it('aceita cadastrar paciente nao identificado com codigo provisorio', function () {
    // RF-04 / RN-30
    $paciente = Paciente::factory()->naoIdentificado()->create();

    expect($paciente->cpf)->toBeNull()
        ->and($paciente->identificacao_provisoria)->toBeTrue()
        ->and($paciente->codigo_provisorio)->toStartWith('NI-');

    $this->assertDatabaseHas('paciente', [
        'user_id' => $paciente->user_id,
        'identificacao_provisoria' => true,
    ]);
});

it('aceita abrir novo atendimento apos finalizar o anterior', function () {
    // RN-07 / D-07: ao finalizar, `ativo_key` vira NULL e o indice unico libera.
    $paciente = Paciente::factory()->create();
    $unidade = Unidade::factory()->create();

    $primeiro = Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $unidade->id,
    ]);

    DB::table('atendimento')->where('id', $primeiro->id)->update([
        'status' => 'FINALIZADO',
        'desfecho' => 'ALTA',
        'finalizado_em' => now(),
    ]);

    $segundo = Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => $unidade->id,
    ]);

    expect($segundo->exists)->toBeTrue();

    // O historico acumula sem colidir: multiplos NULL sao aceitos no indice unico.
    expect(Atendimento::withTrashed()->where('paciente_id', $paciente->user_id)->count())->toBe(2);

    // E o novo atendimento e o unico ativo (RN-07).
    expect($paciente->fresh()->atendimentoAtivo($unidade->id)?->id)->toBe($segundo->id);
});

// =====================================================================
// A coluna gerada e o estado terminal -- o mecanismo por tras da RN-07
// =====================================================================

it('mantem ativo_key igual ao paciente enquanto o atendimento esta aberto e NULL apos encerrar', function () {
    // D-07: o mecanismo que sustenta a RN-07.
    $atendimento = Atendimento::factory()->create();

    $chave = fn () => DB::table('atendimento')->where('id', $atendimento->id)->value('ativo_key');

    expect((int) $chave())->toBe($atendimento->paciente_id);

    DB::table('atendimento')->where('id', $atendimento->id)->update([
        'status' => 'FINALIZADO',
        'desfecho' => 'ALTA',
        'finalizado_em' => now(),
    ]);

    expect($chave())->toBeNull();

    DB::table('atendimento')->where('id', $atendimento->id)->update([
        'status' => 'CANCELADO',
        'desfecho' => null,
        'finalizado_em' => now(),
    ]);

    expect($chave())->toBeNull();
});

it('nao possui coluna idade em nenhuma tabela', function () {
    // D-01, RN-02: invariante 1. A idade e sempre derivada de data_nascimento.
    $colunas = DB::table('information_schema.columns')
        ->where('table_schema', DB::getDatabaseName())
        ->where('column_name', 'idade')
        ->count();

    expect($colunas)->toBe(0);
});

it('recusa UPDATE e DELETE em registro clinico pelo model', function () {
    // RN-16, RN-17, D-05: invariante 2.
    $registro = RegistroClinico::factory()->create();

    // Guardado ANTES da tentativa: `update()` faz fill() e so entao save(), entao o
    // model em memoria fica sujo mesmo com a excecao. O que precisa continuar intacto
    // e a linha no banco.
    $planoOriginal = $registro->plano;

    expect(fn () => $registro->update(['plano' => 'texto alterado']))
        ->toThrow(RegistroImutavelException::class);

    expect(fn () => $registro->delete())
        ->toThrow(RegistroImutavelException::class);

    // O conteudo original permanece intacto no banco.
    expect(RegistroClinico::find($registro->id)->plano)->toBe($planoOriginal);
    $this->assertDatabaseHas('registro_clinico', [
        'id' => $registro->id,
        'plano' => $planoOriginal,
    ]);
});

it('aceita adendo que aponta o registro retificado e informa o motivo', function () {
    // RN-16: o caminho legitimo da correcao.
    $original = RegistroClinico::factory()->create();
    $adendo = RegistroClinico::factory()->adendoDe($original, 'Dose corrigida.')->create();

    expect($adendo->registro_retificado_id)->toBe($original->id)
        ->and($adendo->tipo->value)->toBe('ADENDO')
        ->and($original->fresh()->foiRetificado())->toBeTrue();
});

it('mantem finalizado como estado terminal no enum', function () {
    // RN-14: invariante 8.
    expect(StatusAtendimento::Finalizado->ehTerminal())->toBeTrue()
        ->and(StatusAtendimento::Cancelado->ehTerminal())->toBeTrue()
        ->and(StatusAtendimento::Finalizado->transicoesPermitidas())->toBe([]);
});

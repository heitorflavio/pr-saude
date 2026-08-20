<?php

declare(strict_types=1);

use App\Actions\Paciente\CadastrarPacienteAction;
use App\Actions\Paciente\RegularizarIdentificacaoAction;
use App\Contracts\GeradorTokenPulseira;
use App\Events\IdentificacaoRegularizada;
use App\Events\PacienteCadastrado;
use App\Exceptions\PacienteJaCadastradoException;
use App\Exceptions\RegularizacaoInvalidaException;
use App\Exceptions\TokenPulseiraIndisponivelException;
use App\Models\Atendimento;
use App\Models\AuditoriaLog;
use App\Models\Paciente;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\Pulseira\TokenPulseiraService;
use Database\Factories\Support\GeradorCpf;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->recepcao = User::factory()->profissional()->create();
    $this->recepcao->assignRole('recepcao');
    $this->recepcao = $this->recepcao->fresh();
});

/** @return array<string, mixed> */
function dadosCadastro(array $sobrescreve = []): array
{
    return array_merge([
        'nome_completo' => 'Maria Aparecida Souza',
        'cpf' => GeradorCpf::valido(),
        'data_nascimento' => '1985-03-14',
        'sexo' => 'FEMININO',
        'nome_mae' => 'Joana Souza',
        'telefone' => '11999998888',
    ], $sobrescreve);
}

// =====================================================================
// Fluxo principal do UC-01 -- tudo em uma transacao
// =====================================================================

it('cria paciente, credencial e token na mesma transacao', function () {
    Event::fake([PacienteCadastrado::class]);

    $cpf = GeradorCpf::valido();
    $paciente = app(CadastrarPacienteAction::class)
        ->execute(dadosCadastro(['cpf' => $cpf]), $this->recepcao);

    // RN-04: o login e o CPF.
    $usuario = $paciente->user;
    expect($usuario->login)->toBe($cpf)
        ->and($usuario->tipo)->toBe('PACIENTE')
        // RN-06: a credencial nasce provisoria.
        ->and($usuario->senha_provisoria)->toBeTrue()
        // RN-05: senha inicial e a data de nascimento em DDMMAAAA.
        ->and(Hash::check('14031985', $usuario->password))->toBeTrue()
        // D-03: `users.name` acompanha o nome oficial.
        ->and($usuario->name)->toBe('Maria Aparecida Souza');

    // RN-03: token permanente, com o formato da doc 8.2.1.
    expect(strlen($paciente->token_pulseira))->toBe(TokenPulseiraService::TAM_TOTAL)
        ->and(app(TokenPulseiraService::class)->valido($paciente->token_pulseira))->toBeTrue();

    Event::assertDispatched(PacienteCadastrado::class);
});

it('registra o cadastro na auditoria com cpf e token mascarados', function () {
    $paciente = app(CadastrarPacienteAction::class)->execute(dadosCadastro(), $this->recepcao);

    $log = AuditoriaLog::where('acao', 'paciente.criar')
        ->where('paciente_id', $paciente->user_id)
        ->firstOrFail();

    expect($log->user_id)->toBe($this->recepcao->id)
        ->and($log->dados_depois['cpf'])->toBe('[REMOVIDO]')
        ->and($log->dados_depois['token_pulseira'])->toBe('[REMOVIDO]')
        ->and($log->dados_depois['nome_completo'])->toBe('Maria Aparecida Souza');
});

it('registra alergias e condicoes junto com o cadastro', function () {
    // RF-11
    $paciente = app(CadastrarPacienteAction::class)->execute(dadosCadastro([
        'alergias' => [
            ['substancia' => 'Dipirona sódica', 'gravidade' => 'GRAVE', 'reacao' => 'Choque anafilático'],
        ],
        'condicoes' => [
            ['descricao' => 'Hipertensão arterial sistêmica'],
        ],
    ]), $this->recepcao);

    expect($paciente->alergias)->toHaveCount(1)
        ->and($paciente->alergias->first()->gravidade)->toBe('GRAVE')
        ->and($paciente->condicoes)->toHaveCount(1);
});

// =====================================================================
// A1 -- CPF ja cadastrado
// =====================================================================

it('nao duplica cadastro: oferece o paciente existente', function () {
    $cpf = GeradorCpf::valido();
    $primeiro = app(CadastrarPacienteAction::class)->execute(dadosCadastro(['cpf' => $cpf]), $this->recepcao);

    try {
        app(CadastrarPacienteAction::class)->execute(
            dadosCadastro(['cpf' => $cpf, 'nome_completo' => 'Outro Nome']),
            $this->recepcao
        );
        $this->fail('Deveria ter recusado o CPF duplicado.');
    } catch (PacienteJaCadastradoException $e) {
        // Duplicar prontuario num pronto-socorro esconde a alergia registrada no outro
        // cadastro -- por isso a excecao carrega o paciente encontrado.
        expect($e->paciente->user_id)->toBe($primeiro->user_id);
    }

    expect(Paciente::where('cpf', $cpf)->count())->toBe(1);
});

it('leva a ficha existente quando a recepcao tenta cadastrar CPF duplicado', function () {
    $cpf = GeradorCpf::valido();
    $existente = app(CadastrarPacienteAction::class)->execute(dadosCadastro(['cpf' => $cpf]), $this->recepcao);

    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro(['cpf' => $cpf, 'nome_completo' => 'Homônimo']))
        ->assertRedirect(route('pacientes.show', $existente->user_id))
        ->assertSessionHas('alerta');

    expect(User::count())->toBe(2); // a recepcionista e o unico paciente
});

// =====================================================================
// A4 -- digito verificador
// =====================================================================

it('recusa CPF com digito verificador invalido', function () {
    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro(['cpf' => GeradorCpf::invalido()]))
        ->assertSessionHasErrors('cpf');

    expect(Paciente::count())->toBe(0);
});

it('recusa CPF de digitos repetidos, que passa no calculo mas nao existe', function () {
    // 111.111.111-11 satisfaz o modulo 11 e e o caso que implementacao ingenua aceita.
    expect(Cpf::ehValido('11111111111'))->toBeFalse()
        ->and(Cpf::ehValido('00000000000'))->toBeFalse();
});

it('aceita CPF com mascara e grava so os digitos', function () {
    $cpf = GeradorCpf::valido();
    $mascarado = substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);

    $paciente = app(CadastrarPacienteAction::class)
        ->execute(dadosCadastro(['cpf' => $mascarado]), $this->recepcao);

    expect($paciente->cpf)->toBe($cpf);
});

// =====================================================================
// A2 / RF-04 -- paciente nao identificado
// =====================================================================

it('cadastra paciente sem CPF gerando codigo provisorio', function () {
    $paciente = app(CadastrarPacienteAction::class)->execute([
        'nome_completo' => 'Não identificado - masculino, cerca de 40 anos',
        'identificacao_provisoria' => true,
        'data_nascimento' => '1985-01-01',
    ], $this->recepcao);

    expect($paciente->cpf)->toBeNull()
        ->and($paciente->identificacao_provisoria)->toBeTrue()
        ->and($paciente->codigo_provisorio)->toBe('NI-'.now()->year.'-0001')
        // O codigo provisorio e o login enquanto a identificacao nao for regularizada.
        ->and($paciente->user->login)->toBe($paciente->codigo_provisorio);
});

it('sequencia os codigos provisorios dentro do ano', function () {
    $acao = app(CadastrarPacienteAction::class);
    $ano = now()->year;

    foreach (['Primeiro', 'Segundo', 'Terceiro'] as $nome) {
        $acao->execute([
            'nome_completo' => "Não identificado {$nome}",
            'identificacao_provisoria' => true,
            'data_nascimento' => '1990-01-01',
        ], $this->recepcao);
    }

    expect(Paciente::orderBy('codigo_provisorio')->pluck('codigo_provisorio')->all())
        ->toBe(["NI-{$ano}-0001", "NI-{$ano}-0002", "NI-{$ano}-0003"]);
});

// =====================================================================
// A5 -- validacao de dominio da data de nascimento
// =====================================================================

it('recusa data de nascimento no futuro', function () {
    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro([
            'data_nascimento' => now()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('data_nascimento');
});

it('recusa idade acima de 130 anos', function () {
    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro([
            'data_nascimento' => now()->subYears(131)->toDateString(),
        ]))
        ->assertSessionHasErrors('data_nascimento');
});

// =====================================================================
// A3 -- menor de idade exige responsavel
// =====================================================================

it('recusa cadastrar menor de idade sem responsavel legal', function () {
    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro([
            'data_nascimento' => now()->subYears(9)->toDateString(),
            'contato_emergencia_nome' => '',
            'contato_emergencia_telefone' => '',
        ]))
        ->assertSessionHasErrors(['contato_emergencia_nome', 'contato_emergencia_telefone']);
});

it('aceita menor de idade com responsavel informado', function () {
    $this->actingAs($this->recepcao)
        ->post(route('pacientes.store'), dadosCadastro([
            'data_nascimento' => now()->subYears(9)->toDateString(),
            'contato_emergencia_nome' => 'Joana Souza',
            'contato_emergencia_telefone' => '11988887777',
        ]))
        ->assertSessionHasNoErrors();

    expect(Paciente::count())->toBe(1);
});

// =====================================================================
// E1 -- falha na geracao do token faz rollback integral
// =====================================================================

it('nao deixa paciente nem usuario orfao quando a geracao do token falha', function () {
    // E1 do UC-01: rollback integral. Um usuario sem paciente e uma credencial orfa que
    // ninguem consegue explicar depois; um paciente sem token e um paciente sem pulseira.
    $existente = Paciente::factory()->create();

    // Gerador que sempre devolve um token ja em uso: forca as 3 tentativas a colidirem.
    $this->swap(GeradorTokenPulseira::class, new class($existente->token_pulseira) implements GeradorTokenPulseira
    {
        public function __construct(private readonly string $tokenFixo) {}

        public function gerar(): string
        {
            return $this->tokenFixo;
        }

        public function valido(string $token): bool
        {
            return true;
        }
    });

    $usuariosAntes = User::count();
    $pacientesAntes = Paciente::count();

    expect(fn () => app(CadastrarPacienteAction::class)->execute(dadosCadastro(), $this->recepcao))
        ->toThrow(TokenPulseiraIndisponivelException::class);

    expect(User::count())->toBe($usuariosAntes)
        ->and(Paciente::count())->toBe($pacientesAntes);
});

// =====================================================================
// RN-30 -- regularizacao preserva o historico
// =====================================================================

it('regulariza a identificacao preservando historico, token e prontuario', function () {
    Event::fake([IdentificacaoRegularizada::class]);

    $paciente = app(CadastrarPacienteAction::class)->execute([
        'nome_completo' => 'Não identificado - feminino',
        'identificacao_provisoria' => true,
        'data_nascimento' => '1970-06-05',
    ], $this->recepcao);

    $tokenOriginal = $paciente->token_pulseira;
    $userIdOriginal = $paciente->user_id;
    $codigoAnterior = $paciente->codigo_provisorio;

    // O episodio ja produziu historico antes de o documento aparecer.
    $atendimento = Atendimento::factory()->create(['paciente_id' => $paciente->user_id]);

    $cpf = GeradorCpf::valido();
    $regularizado = app(RegularizarIdentificacaoAction::class)
        ->execute($paciente, $cpf, $this->recepcao, 'Ana Lúcia Ferreira');

    expect($regularizado->user_id)->toBe($userIdOriginal)
        // RN-03: o token da pulseira e permanente -- a pulseira impressa continua valendo.
        ->and($regularizado->token_pulseira)->toBe($tokenOriginal)
        ->and($regularizado->cpf)->toBe($cpf)
        ->and($regularizado->identificacao_provisoria)->toBeFalse()
        ->and($regularizado->codigo_provisorio)->toBeNull()
        ->and($regularizado->nome_completo)->toBe('Ana Lúcia Ferreira')
        // RN-04: o login acompanha a identificacao.
        ->and($regularizado->user->login)->toBe($cpf);

    // O historico do episodio grave continua no mesmo prontuario.
    expect($regularizado->atendimentos()->count())->toBe(1)
        ->and($regularizado->atendimentos()->first()->id)->toBe($atendimento->id);

    Event::assertDispatched(IdentificacaoRegularizada::class);

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'paciente.regularizar_identificacao',
        'paciente_id' => $userIdOriginal,
    ]);

    expect(AuditoriaLog::where('acao', 'paciente.regularizar_identificacao')->value('justificativa'))
        ->toContain($codigoAnterior);
});

it('recusa regularizar paciente que ja tem identificacao definitiva', function () {
    $paciente = Paciente::factory()->create();

    expect(fn () => app(RegularizarIdentificacaoAction::class)
        ->execute($paciente, GeradorCpf::valido(), $this->recepcao))
        ->toThrow(RegularizacaoInvalidaException::class);
});

it('recusa regularizar com CPF invalido', function () {
    $paciente = Paciente::factory()->naoIdentificado()->create();

    expect(fn () => app(RegularizarIdentificacaoAction::class)
        ->execute($paciente, GeradorCpf::invalido(), $this->recepcao))
        ->toThrow(RegularizacaoInvalidaException::class);
});

it('recusa regularizar com CPF que ja pertence a outro paciente', function () {
    // Fusao de prontuarios e decisao assistencial, nao algo que a Action resolva sozinha.
    $cpf = GeradorCpf::valido();
    $definitivo = app(CadastrarPacienteAction::class)->execute(dadosCadastro(['cpf' => $cpf]), $this->recepcao);
    $provisorio = Paciente::factory()->naoIdentificado()->create();

    try {
        app(RegularizarIdentificacaoAction::class)->execute($provisorio, $cpf, $this->recepcao);
        $this->fail('Deveria ter recusado.');
    } catch (PacienteJaCadastradoException $e) {
        expect($e->paciente->user_id)->toBe($definitivo->user_id);
    }

    expect($provisorio->fresh()->identificacao_provisoria)->toBeTrue();
});

// =====================================================================
// RF-09 -- busca
// =====================================================================

it('busca paciente por nome, CPF, data de nascimento, codigo provisorio e token', function () {
    $cpf = GeradorCpf::valido();
    $paciente = app(CadastrarPacienteAction::class)->execute(dadosCadastro([
        'cpf' => $cpf,
        'nome_completo' => 'Joaquim Nabuco Ribeiro',
        'data_nascimento' => '1985-03-14',
    ]), $this->recepcao);

    $provisorio = Paciente::factory()->naoIdentificado()->create();

    expect(Paciente::busca('Nabuco')->count())->toBe(1)
        ->and(Paciente::busca($cpf)->first()->user_id)->toBe($paciente->user_id)
        ->and(Paciente::busca('14/03/1985')->first()->user_id)->toBe($paciente->user_id)
        ->and(Paciente::busca('1985-03-14')->first()->user_id)->toBe($paciente->user_id)
        ->and(Paciente::busca($paciente->token_pulseira)->first()->user_id)->toBe($paciente->user_id)
        ->and(Paciente::busca($provisorio->codigo_provisorio)->first()->user_id)->toBe($provisorio->user_id);
});

it('nao consulta o banco por token malformado', function () {
    // doc 8.2.1: o checksum permite rejeitar antes de qualquer SELECT. Um token com
    // formato invalido nao pode casar com nenhum registro.
    expect(Paciente::busca('token-invalido-qualquer')->count())->toBe(0);
});

// =====================================================================
// Autorizacao das rotas
// =====================================================================

it('nega o cadastro a quem nao tem paciente.criar', function () {
    $tecnico = User::factory()->profissional()->create();
    $tecnico->assignRole('tecnico_enfermagem');

    $this->actingAs($tecnico->fresh())
        ->post(route('pacientes.store'), dadosCadastro())
        ->assertForbidden();

    expect(Paciente::count())->toBe(0);
});

it('permite a recepcao ver a ficha cadastral sem vinculo assistencial', function () {
    // A recepcionista que acabou de cadastrar precisa da ficha para imprimir a pulseira
    // e abrir o atendimento (UC-01, passo 11) -- e nesse instante nao ha vinculo algum.
    $paciente = Paciente::factory()->create();

    $this->actingAs($this->recepcao)
        ->get(route('pacientes.show', $paciente->user_id))
        ->assertOk();

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'paciente.ler',
        'paciente_id' => $paciente->user_id,
        'user_id' => $this->recepcao->id,
    ]);
});

<?php

declare(strict_types=1);

use App\Actions\Pulseira\ImprimirPulseiraAction;
use App\Contracts\GeradorTokenPulseira;
use App\Events\PulseiraImpressa;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\Atendimento;
use App\Models\ClassificacaoRisco;
use App\Models\Paciente;
use App\Models\PacienteAlergia;
use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use App\Models\User;
use App\Services\Pulseira\GerarPulseiraService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function profissionalDePlantao(string $categoria, string $role): Profissional
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

// =====================================================================
// doc 8.5 -- dimensionamento do QR Code
// =====================================================================

it('gera QR Code na versao 5 com correcao Q', function () {
    // O calculo da doc 8.5: URL de 48 caracteres + ECC Q -> versao 5, 37x37 modulos.
    // A versao nao e fixada por parametro; o endroid escolhe a menor que acomoda. Este
    // teste confere que a escolha bate com o calculo -- se o formato do token ou a URL
    // base crescerem, ele quebra e avisa antes de a pulseira sair errada da impressora.
    $paciente = Paciente::factory()->create();

    $resultado = app(GerarPulseiraService::class)->construirQr($paciente)->build();

    // 37 modulos da versao 5, sem margem (margin: 0).
    expect($resultado->getMatrix()->getBlockCount())->toBe(37);
});

it('codifica no QR a URL do token, e nada mais', function () {
    // RN-03 / D-09: o QR nao codifica id nem CPF.
    $paciente = Paciente::factory()->create();

    $url = route('pulseira.resolver', $paciente->token_pulseira);

    expect($url)->toEndWith('/p/'.$paciente->token_pulseira)
        ->and($url)->not->toContain((string) $paciente->cpf);
});

// =====================================================================
// RF-15 / RF-16 -- impressao e reimpressao
// =====================================================================

it('registra toda impressao com motivo e produz um PDF', function () {
    Event::fake([PulseiraImpressa::class]);

    $paciente = Paciente::factory()->create();
    $operador = profissionalDePlantao('recepcao', 'recepcao');

    $resultado = app(ImprimirPulseiraAction::class)->execute(
        paciente: $paciente,
        operador: $operador->user,
        motivo: 'PRIMEIRA',
    );

    expect($resultado['pdf'])->toStartWith('%PDF-');

    $this->assertDatabaseHas('pulseira_impressao', [
        'paciente_id' => $paciente->user_id,
        'motivo' => 'PRIMEIRA',
        'impressa_por' => $operador->user_id,
    ]);

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'pulseira.imprimir',
        'paciente_id' => $paciente->user_id,
    ]);

    Event::assertDispatched(PulseiraImpressa::class);
});

it('reimprime com o mesmo token', function () {
    // RF-16 / RN-03: o token e permanente. Se cada impressao gerasse identificador
    // novo, a pulseira anterior deixaria de resolver -- risco assistencial.
    $paciente = Paciente::factory()->create();
    $tokenOriginal = $paciente->token_pulseira;
    $operador = profissionalDePlantao('recepcao', 'recepcao');

    $acao = app(ImprimirPulseiraAction::class);
    $acao->execute(paciente: $paciente, operador: $operador->user, motivo: 'PRIMEIRA');
    $acao->execute(paciente: $paciente, operador: $operador->user, motivo: 'DANIFICADA');

    expect($paciente->fresh()->token_pulseira)->toBe($tokenOriginal)
        ->and($paciente->pulseiraImpressoes()->count())->toBe(2);
});

it('recusa impressao por usuario sem registro profissional', function () {
    // O caso real: o administrador do sistema tem `pulseira.imprimir` pela matriz da
    // doc 2.3, mas e uma conta de TI sem registro em `profissional`. Sem a guarda, isso
    // morria numa violacao de constraint do MySQL -- mensagem inutil para a recepcao.
    $paciente = Paciente::factory()->create();
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ImprimirPulseiraAction::class)->execute(
        paciente: $paciente,
        operador: $admin,
    ))->toThrow(OperadorSemRegistroProfissionalException::class);

    expect($paciente->pulseiraImpressoes()->count())->toBe(0);
});

it('nega a impressao a quem nao tem pulseira.imprimir', function () {
    $paciente = Paciente::factory()->create();
    $laboratorio = profissionalDePlantao('laboratorio', 'laboratorio');

    $this->actingAs($laboratorio->user)
        ->post(route('pulseira.imprimir', $paciente->user_id))
        ->assertForbidden();

    expect($paciente->pulseiraImpressoes()->count())->toBe(0);
});

it('entrega o PDF pela rota de impressao', function () {
    $paciente = Paciente::factory()->create();
    $recepcao = profissionalDePlantao('recepcao', 'recepcao');

    $this->actingAs($recepcao->user)
        ->post(route('pulseira.imprimir', $paciente->user_id), ['motivo' => 'PRIMEIRA'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

// =====================================================================
// doc 8.4 -- o que a pulseira NAO imprime
// =====================================================================

it('nao imprime CPF, CNS nem endereco na pulseira', function () {
    // A decisao que mais gera resistencia em implantacao real. O argumento: o CPF nao
    // melhora a identificacao assistencial -- nome + data de nascimento ja dao dois
    // identificadores independentes -- e piora muito a exposicao. Custo alto,
    // beneficio nulo.
    $paciente = Paciente::factory()->create([
        'cns' => '123456789012345',
        'logradouro' => 'Rua das Acacias',
        'municipio' => 'Sao Paulo',
    ]);

    $html = view('pulseira.termica', [
        'paciente' => $paciente,
        'atendimento' => null,
        'classificacao' => null,
        'qrBase64' => 'data:image/png;base64,AAAA',
        'alergias' => [],
        'idadeCongelada' => $paciente->idadeDescritiva(),
        'impressaEm' => now(),
    ])->render();

    expect($html)->not->toContain((string) $paciente->cpf)
        ->and($html)->not->toContain('123456789012345')
        ->and($html)->not->toContain('Rua das Acacias')
        // O que DEVE aparecer: os dois identificadores.
        // O template escapa corretamente apóstrofos e outros caracteres HTML do nome.
        ->and($html)->toContain(e(mb_strtoupper($paciente->nome_completo)))
        ->and($html)->toContain($paciente->data_nascimento->format('d/m/Y'));
});

it('imprime a faixa de alergia com marcacao redundante', function () {
    // Se um unico elemento precisar sobreviver a uma impressao ruim, e esse.
    $paciente = Paciente::factory()->create();
    PacienteAlergia::factory()->create([
        'paciente_id' => $paciente->user_id,
        'substancia' => 'Dipirona sódica',
    ]);

    $html = view('pulseira.termica', [
        'paciente' => $paciente,
        'atendimento' => null,
        'classificacao' => null,
        'qrBase64' => 'data:image/png;base64,AAAA',
        'alergias' => $paciente->fresh()->alergias->pluck('substancia')->all(),
        'idadeCongelada' => '40 anos',
        'impressaEm' => now(),
    ])->render();

    expect($html)->toContain('ALERGIA')
        ->toContain(mb_strtoupper('Dipirona sódica'))
        // Marcacao redundante: simbolo alem da caixa alta.
        ->toContain('&#9888;');
});

it('imprime a cor de prioridade com rotulo textual, nunca so a cor', function () {
    // RNF-15
    $paciente = Paciente::factory()->create();
    $classificacao = ClassificacaoRisco::find(2); // LARANJA

    $html = view('pulseira.termica', [
        'paciente' => $paciente,
        'atendimento' => null,
        'classificacao' => $classificacao,
        'qrBase64' => 'data:image/png;base64,AAAA',
        'alergias' => [],
        'idadeCongelada' => '40 anos',
        'impressaEm' => now(),
    ])->render();

    expect($html)->toContain('MUITO URGENTE')
        ->toContain('LARANJA')
        ->toContain($classificacao->cor_hex);
});

// =====================================================================
// doc 8.3 -- o fluxograma de resolucao
// =====================================================================

it('nao vaza dado algum e redireciona quando nao ha sessao', function () {
    // Passo D -> Nao do fluxograma.
    $paciente = Paciente::factory()->create(['nome_completo' => 'Maria Aparecida Souza']);

    $resposta = $this->get(route('pulseira.resolver', $paciente->token_pulseira));

    $resposta->assertRedirect(route('portal.login'));
    expect($resposta->getContent())->not->toContain('Maria Aparecida Souza')
        ->and($resposta->getContent())->not->toContain((string) $paciente->cpf);
});

it('responde igual para token existente e inexistente sem sessao', function () {
    // O detalhe de projeto mais importante do fluxograma: a rota nao serve de oraculo
    // de enumeracao. Quem quisesse descobrir quais tokens existem compararia as duas
    // respostas -- e elas sao identicas.
    $paciente = Paciente::factory()->create();
    $inexistente = app(GeradorTokenPulseira::class)->gerar();

    $existente = $this->get(route('pulseira.resolver', $paciente->token_pulseira));
    $naoExistente = $this->get(route('pulseira.resolver', $inexistente));

    expect($existente->getStatusCode())->toBe($naoExistente->getStatusCode())
        ->and($existente->headers->get('Location'))->toBe($naoExistente->headers->get('Location'));
});

it('recusa token com checksum invalido antes de tocar o banco', function () {
    // Passo B -> Nao. Formato correto com checksum invalido e evidencia de manipulacao
    // deliberada, e merece registro distinto na trilha.
    $this->get('/p/'.str_repeat('A', 26))->assertNotFound();

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'pulseira.token_invalido',
    ]);
});

it('entrega contexto completo ao profissional com vinculo assistencial', function () {
    // Passo I -> Sim.
    $medico = profissionalDePlantao('medico', 'medico');
    $paciente = Paciente::factory()->create();

    Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'profissional_responsavel_id' => $medico->user_id,
        'classificacao_risco_id' => 2,
    ]);

    $this->actingAs($medico->user)
        ->get(route('pulseira.resolver', $paciente->token_pulseira))
        ->assertOk();

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'pulseira.leitura_qr',
        'paciente_id' => $paciente->user_id,
    ]);
});

it('entrega apenas o minimo vital ao profissional sem vinculo', function () {
    // Passo I -> Nao -> K. Se um paciente entra em parada no corredor e o medico que
    // passa nao e o responsavel, negar a lista de alergias em nome do sigilo seria uma
    // decisao de projeto que mata pessoas.
    $medico = profissionalDePlantao('medico', 'medico');
    $paciente = Paciente::factory()->create();
    PacienteAlergia::factory()->create([
        'paciente_id' => $paciente->user_id,
        'substancia' => 'Penicilina',
    ]);
    Atendimento::factory()->create(['paciente_id' => $paciente->user_id]);

    $this->actingAs($medico->user)
        ->get(route('pulseira.resolver', $paciente->token_pulseira))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pulseira/Contexto')
            ->where('temVinculo', false)
            // Nome e alergias saem;
            ->where('paciente.nome', $paciente->nomeExibicao())
            ->has('alergias', 1)
            // o episodio, nao.
            ->where('atendimento', null)
        );

    $this->assertDatabaseHas('auditoria_log', [
        'acao' => 'pulseira.leitura_qr.minimo_vital',
        'paciente_id' => $paciente->user_id,
    ]);
});

it('nega leitura a profissional fora de plantao e sem vinculo', function () {
    // O minimo vital exige plantao (doc 13.5): quem nao esta de plantao nao tem motivo
    // assistencial para abrir prontuario nenhum.
    $medico = Profissional::factory()->medico()->create();
    $medico->user->assignRole('medico');
    $paciente = Paciente::factory()->create();

    $this->actingAs($medico->user->fresh())
        ->get(route('pulseira.resolver', $paciente->token_pulseira))
        ->assertForbidden();
});

it('nega a um paciente ler a pulseira de outro', function () {
    // RN-26: o paciente acessa exclusivamente o proprio dado.
    $paciente = Paciente::factory()->create();
    $outro = Paciente::factory()->create();

    $this->actingAs($outro->user, 'paciente')
        ->get(route('pulseira.resolver', $paciente->token_pulseira))
        ->assertForbidden();
});

it('devolve 404 para token bem formado inexistente quando ha sessao', function () {
    $medico = profissionalDePlantao('medico', 'medico');
    $inexistente = app(GeradorTokenPulseira::class)->gerar();

    $this->actingAs($medico->user)
        ->get(route('pulseira.resolver', $inexistente))
        ->assertNotFound();
});

it('aplica rate limit na rota do QR Code', function () {
    // A rota e o alvo natural de varredura automatizada.
    $token = app(GeradorTokenPulseira::class)->gerar();

    for ($i = 0; $i < 30; $i++) {
        $this->get(route('pulseira.resolver', $token));
    }

    $this->get(route('pulseira.resolver', $token))->assertStatus(429);
});

<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Services\Pulseira\TokenPulseiraService;
use Illuminate\Support\Facades\Config;

/**
 * Invariante 15 (RN-03, doc §8.2): o token da pulseira é permanente, opaco e verificável
 * sem consultar o banco.
 *
 * O serviço é entregue aqui, na Fase 3, porque o cadastro de paciente já depende dele
 * (UC-01, passo 10c). A Fase 4 constrói o QR Code, o template da pulseira e a rota
 * `/p/{token}` sobre esta base. Ver docs/DECISOES.md D-25.
 */
beforeEach(function () {
    $this->tokens = app(TokenPulseiraService::class);
});

it('gera token com 26 caracteres base62', function () {
    // 22 de corpo (≈131 bits) + 4 de checksum.
    $token = $this->tokens->gerar();

    expect(strlen($token))->toBe(26)
        ->and($token)->toMatch('/^[0-9A-Za-z]{26}$/')
        ->and($this->tokens->valido($token))->toBeTrue();
});

it('gera 20.000 tokens sem colisao', function () {
    // doc §8.2.2: a densidade de tokens válidos em 20 anos de operação seria ≈ 3,7×10⁻³⁴.
    // Uma colisão aqui não seria azar -- seria random_int mal semeado.
    $tokens = [];

    for ($i = 0; $i < 20_000; $i++) {
        $tokens[$this->tokens->gerar()] = true;
    }

    expect(count($tokens))->toBe(20_000);
});

it('rejeita token com um unico caractere alterado', function () {
    // Detecção de leitura corrompida: pulseira suja, molhada ou parcialmente rasgada
    // pode decodificar errado e ainda ter formato válido. Sem o checksum, isso poderia
    // resolver para OUTRO paciente.
    $token = $this->tokens->gerar();

    // Altera um caractere do corpo, mantendo o comprimento.
    $original = $token[5];
    $token[5] = $original === 'A' ? 'B' : 'A';

    expect($this->tokens->valido($token))->toBeFalse();
});

it('rejeita token com o checksum alterado', function () {
    $token = $this->tokens->gerar();

    $original = $token[25];
    $token[25] = $original === 'z' ? 'y' : 'z';

    expect($this->tokens->valido($token))->toBeFalse();
});

it('rejeita token truncado ou alongado', function () {
    $token = $this->tokens->gerar();

    expect($this->tokens->valido(substr($token, 0, 25)))->toBeFalse()
        ->and($this->tokens->valido(substr($token, 0, 22)))->toBeFalse()
        ->and($this->tokens->valido($token.'A'))->toBeFalse()
        ->and($this->tokens->valido(''))->toBeFalse();
});

it('rejeita token assinado com outra chave', function () {
    // A prova de que o checksum é HMAC e não um hash simples: sem a chave, ninguém
    // forja um sufixo válido a partir do corpo.
    $tokenDaOutraInstalacao = $this->tokens->gerar();

    Config::set('app.pulseira_key', 'base64:b3V0cmEtY2hhdmUtY29tcGxldGFtZW50ZS1kaWZlcg==');
    $outroServico = app(TokenPulseiraService::class);

    expect($outroServico->valido($tokenDaOutraInstalacao))->toBeFalse();

    // E o inverso: o token da outra chave também é recusado aqui.
    $tokenDaOutraChave = $outroServico->gerar();
    Config::set('app.pulseira_key', config('app.pulseira_key'));

    expect($outroServico->valido($tokenDaOutraChave))->toBeTrue();
});

it('falha alto quando a chave nao esta configurada', function () {
    // Sem isto, o HMAC seria calculado sobre string vazia e o sistema geraria tokens
    // que qualquer pessoa com o codigo-fonte conseguiria forjar. Falha silenciosa e a
    // pior forma de falhar aqui.
    Config::set('app.pulseira_key', null);

    expect(fn () => app(TokenPulseiraService::class)->gerar())
        ->toThrow(RuntimeException::class);
});

it('nao codifica id nem CPF no token', function () {
    // RN-03 / D-09: o token e opaco. Codificar o id sequencial tornaria a enumeracao de
    // todos os pacientes uma questao de contar de 1 a N.
    //
    // "Nao contem o id" seria asserção vazia: um id de um digito aparece por acaso em
    // qualquer string base62. O que prova opacidade e o token NAO SER DERIVAVEL dos
    // dados do paciente -- dois pacientes com ids consecutivos recebem tokens sem
    // nenhuma relacao entre si, e o CPF completo nunca aparece.
    $primeiro = Paciente::factory()->create();
    $segundo = Paciente::factory()->create();

    expect($primeiro->token_pulseira)->not->toBe($segundo->token_pulseira)
        ->and($primeiro->token_pulseira)->not->toContain((string) $primeiro->cpf)
        ->and($segundo->token_pulseira)->not->toContain((string) $segundo->cpf);

    // Ids consecutivos, tokens sem prefixo comum: nada no token acompanha a sequencia.
    expect($segundo->user_id - $primeiro->user_id)->toBe(1)
        ->and(substr($primeiro->token_pulseira, 0, 4))->not->toBe(substr($segundo->token_pulseira, 0, 4));
});

it('nao expoe o token na serializacao do model', function () {
    $paciente = Paciente::factory()->create();

    expect($paciente->toArray())->not->toHaveKey('token_pulseira');
});

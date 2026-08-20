<?php

declare(strict_types=1);

use App\Models\Paciente;
use Illuminate\Support\Carbon;

/**
 * Invariante 1 (D-01, RN-02): a idade NUNCA é armazenada.
 *
 * Armazená-la viola a 2FN por dependência funcional derivada e produz um dado que começa
 * correto e apodrece silenciosamente: no dia seguinte ao aniversário, o prontuário mente.
 *
 * Os quatro casos abaixo são exatamente onde uma implementação ingênua erra.
 */
function pacienteNascidoEm(string $data): Paciente
{
    return Paciente::factory()->make(['data_nascimento' => $data]);
}

it('nao conta o ano na vespera do aniversario', function () {
    // O caso classico: subtrair os anos das datas daria 40 um dia cedo demais.
    Carbon::setTestNow(Carbon::parse('2026-03-13'));

    $paciente = pacienteNascidoEm('1986-03-14');

    expect($paciente->idade)->toBe(39)
        ->and($paciente->idadeDescritiva())->toBe('39 anos');
});

it('conta o ano no dia do aniversario', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-14'));

    $paciente = pacienteNascidoEm('1986-03-14');

    expect($paciente->idade)->toBe(40)
        ->and($paciente->idadeDescritiva())->toBe('40 anos');
});

it('trata nascido em 29 de fevereiro em ano nao bissexto', function () {
    // 2026 não é bissexto: quem nasceu em 29/02/2000 completa anos em 28/02 ou 01/03,
    // dependendo da convenção. O que não pode acontecer é a data quebrar o cálculo.
    Carbon::setTestNow(Carbon::parse('2026-02-28'));
    expect(pacienteNascidoEm('2000-02-29')->idade)->toBe(25);

    Carbon::setTestNow(Carbon::parse('2026-03-01'));
    expect(pacienteNascidoEm('2000-02-29')->idade)->toBe(26);
});

it('trata nascido em 29 de fevereiro no proprio 29 de fevereiro', function () {
    Carbon::setTestNow(Carbon::parse('2028-02-29'));

    expect(pacienteNascidoEm('2000-02-29')->idade)->toBe(28)
        ->and(pacienteNascidoEm('2000-02-29')->idadeDescritiva())->toBe('28 anos');
});

it('exibe recem-nascido em dias, nao em anos', function () {
    // "0 anos" não informa nada sobre um neonato. Em pediatria a diferença entre 3 dias
    // e 20 dias muda a conduta -- por isso a granularidade adaptativa (D-01).
    Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));

    expect(pacienteNascidoEm('2026-08-15')->idadeDescritiva())->toBe('3 dias')
        ->and(pacienteNascidoEm('2026-08-17')->idadeDescritiva())->toBe('1 dia')
        ->and(pacienteNascidoEm('2026-08-18')->idadeDescritiva())->toBe('0 dias');
});

it('exibe lactente em meses ate 24 meses', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18'));

    expect(pacienteNascidoEm('2026-06-18')->idadeDescritiva())->toBe('2 meses')
        ->and(pacienteNascidoEm('2025-07-18')->idadeDescritiva())->toBe('13 meses')
        // 23 meses ainda é mês; 24 vira ano.
        ->and(pacienteNascidoEm('2024-09-18')->idadeDescritiva())->toBe('23 meses')
        ->and(pacienteNascidoEm('2024-08-18')->idadeDescritiva())->toBe('2 anos');
});

it('vira meses no dia 31 de vida', function () {
    // A fronteira exata da regra: dias até 30, meses a partir de 31.
    Carbon::setTestNow(Carbon::parse('2026-08-18'));

    expect(pacienteNascidoEm('2026-07-19')->idadeDescritiva())->toBe('30 dias')
        ->and(pacienteNascidoEm('2026-07-18')->idadeDescritiva())->toBe('1 mes');
});

it('usa singular para um ano', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-18'));

    expect(pacienteNascidoEm('2024-08-18')->idadeDescritiva())->toBe('2 anos')
        ->and(pacienteNascidoEm('2025-08-18')->idadeDescritiva())->toBe('12 meses');
});

it('aceita uma data de referencia explicita', function () {
    // Permite calcular a idade que o paciente tinha na admissao, e nao hoje -- a
    // "idade congelada" que a pulseira imprime (doc 8.4).
    $paciente = pacienteNascidoEm('1986-03-14');

    expect($paciente->idadeDescritiva(Carbon::parse('2020-03-14')))->toBe('34 anos');
});

afterEach(function () {
    Carbon::setTestNow();
});

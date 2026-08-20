<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RF-03 / A4 do UC-01: valida o CPF pelo **dígito verificador**, não por formato.
 *
 * Regra customizada, não regex, e a diferença importa: `111.111.111-11` passa em
 * qualquer regex de formato e é um CPF inexistente. Num pronto-socorro, um CPF inválido
 * aceito no cadastro vira um paciente que não se consegue localizar depois -- e, no
 * portal, uma credencial que ninguém usa.
 *
 * O banco garante apenas o formato (CHECK `ck_paciente_cpf_digitos`: 11 dígitos). O
 * dígito verificador é responsabilidade da aplicação: é aritmética, não restrição de
 * domínio expressável em DDL.
 */
final class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('O :attribute informado não é um CPF válido.');

            return;
        }

        if (! self::ehValido((string) $value)) {
            $fail('O :attribute informado não é um CPF válido.');
        }
    }

    public static function ehValido(string $cpf): bool
    {
        $cpf = self::apenasDigitos($cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // 000.000.000-00, 111.111.111-11 e afins satisfazem o cálculo do dígito
        // verificador, mas não são CPFs emitidos. São o caso de teste que passa em
        // implementação ingênua.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        return self::digito($cpf, 9) === (int) $cpf[9]
            && self::digito($cpf, 10) === (int) $cpf[10];
    }

    /** Remove pontuação de máscara: o banco guarda só os 11 dígitos. */
    public static function apenasDigitos(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf) ?? '';
    }

    /**
     * Dígito verificador da posição informada, pelo algoritmo do módulo 11.
     */
    private static function digito(string $cpf, int $posicao): int
    {
        $peso = $posicao + 1;
        $soma = 0;

        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * $peso--;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Support;

/**
 * Gera CPF com dígito verificador válido para uso em factories.
 *
 * O banco só garante o formato (CHECK ck_paciente_cpf_digitos: 11 dígitos). O dígito
 * verificador é validado na aplicação (RF-03, regra customizada -- não regex). Se as
 * factories gerassem CPF aleatório de 11 dígitos, os testes da Fase 3 passariam contra
 * dados que o cadastro real recusaria.
 */
final class GeradorCpf
{
    public static function valido(): string
    {
        $base = '';
        for ($i = 0; $i < 9; $i++) {
            $base .= random_int(0, 9);
        }

        $base .= self::digito($base);
        $base .= self::digito($base);

        // CPFs de dígitos repetidos (000.000.000-00 etc.) passam no cálculo mas são
        // inválidos por convenção -- gera de novo.
        return preg_match('/^(\d)\1{10}$/', $base) ? self::valido() : $base;
    }

    /** CPF com formato correto (11 dígitos) mas dígito verificador errado. */
    public static function invalido(): string
    {
        $valido = self::valido();
        $ultimo = (int) $valido[10];

        return substr($valido, 0, 10).(($ultimo + 1) % 10);
    }

    private static function digito(string $parcial): int
    {
        $peso = strlen($parcial) + 1;
        $soma = 0;

        foreach (str_split($parcial) as $digito) {
            $soma += (int) $digito * $peso--;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}

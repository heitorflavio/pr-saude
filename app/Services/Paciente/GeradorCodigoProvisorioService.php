<?php

declare(strict_types=1);

namespace App\Services\Paciente;

use App\Models\Paciente;
use Illuminate\Support\Facades\DB;

/**
 * RF-04 / A2 do UC-01: identificação provisória para paciente sem CPF.
 *
 * Formato `NI-2026-0031`: "não identificado", ano e sequencial do ano. O código é o
 * **login** desse paciente enquanto a identificação não for regularizada (RN-30).
 *
 * Legível de propósito: alguém vai ditar esse código por telefone entre setores, e
 * `NI-2026-0031` sobrevive a isso melhor que um UUID.
 */
final class GeradorCodigoProvisorioService
{
    private const PREFIXO = 'NI';

    public function gerar(?int $ano = null): string
    {
        $ano ??= (int) now()->year;

        /*
         * `lockForUpdate` dentro da transação da Action: duas recepcionistas cadastrando
         * pacientes não identificados ao mesmo tempo produziriam o mesmo sequencial. O
         * índice único `uk_paciente_provisorio` é a garantia final, mas o lock evita que
         * o caso comum vire erro visível ao usuário.
         */
        $ultimo = Paciente::withTrashed()
            ->where('codigo_provisorio', 'like', self::PREFIXO."-{$ano}-%")
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->orderByDesc('codigo_provisorio')
            ->value('codigo_provisorio');

        $sequencial = $ultimo === null ? 1 : ((int) substr($ultimo, -4)) + 1;

        return sprintf('%s-%d-%04d', self::PREFIXO, $ano, $sequencial);
    }
}

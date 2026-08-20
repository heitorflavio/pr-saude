<?php

declare(strict_types=1);

namespace App\Services\Exame;

use App\Models\ExameFaixaCritica;
use Illuminate\Support\Str;

final class AvaliadorResultadoService
{
    public function sinalizar(string $analito, mixed $valor, ?float $referenciaMin, ?float $referenciaMax, ?string $unidade = null): string
    {
        if (! is_numeric($valor)) {
            return 'INDETERMINADO';
        }

        $numero = (float) $valor;
        // Doc §11.2: a tabela é editável pelo laboratório; uma mudança de protocolo não
        // exige deploy e resultados antigos continuam com a referência gravada.
        $faixa = ExameFaixaCritica::query()->where('ativo', true)->get()->first(
            fn (ExameFaixaCritica $f) => $this->normalizar($f->analito) === $this->normalizar($analito)
                && ($f->unidade === null || $unidade === null || $this->normalizar($f->unidade) === $this->normalizar($unidade))
        );

        if ($faixa !== null && (($faixa->critico_min !== null && $numero <= (float) $faixa->critico_min)
            || ($faixa->critico_max !== null && $numero >= (float) $faixa->critico_max))) {
            return 'CRITICO';
        }

        return match (true) {
            $referenciaMin !== null && $numero < $referenciaMin => 'BAIXO',
            $referenciaMax !== null && $numero > $referenciaMax => 'ALTO',
            default => 'NORMAL',
        };
    }

    private function normalizar(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}

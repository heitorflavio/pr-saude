<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Atendimento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RN-09: a cor da pulseira precisa refletir a classificação vigente.
 *
 * Uma pulseira verde num paciente laranja é **pior que nenhuma pulseira**, porque
 * comunica ativamente a informação errada — e a faixa de cor é justamente o que a equipe
 * lê de relance, com o paciente parcialmente coberto pelo lençol.
 *
 * O evento sinaliza a necessidade; a impressão continua sendo ato de um profissional
 * (RF-15), não automação silenciosa.
 */
final class ReimpressaoPulseiraNecessaria
{
    use Dispatchable;

    public function __construct(
        public readonly Atendimento $atendimento,
        public readonly string $motivo = 'RECLASSIFICACAO',
    ) {}
}

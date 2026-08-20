<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Atendimento;

/**
 * RN-07 / D-07: no máximo um atendimento não finalizado por paciente por unidade.
 *
 * A garantia é do **banco** — coluna gerada `ativo_key` + `uk_atendimento_ativo` — e não
 * de uma verificação em PHP. Duas recepcionistas clicando ao mesmo tempo passariam por
 * qualquer `if()`; não passam pelo índice único.
 *
 * Esta exceção é a tradução daquela violação para uma mensagem que serve a quem está no
 * balcão: o atendimento já existe, e o caminho é abrir a ficha dele.
 */
final class AtendimentoAtivoExistenteException extends DominioException
{
    public function __construct(public readonly ?Atendimento $atendimento = null)
    {
        $numero = $atendimento?->numero;

        parent::__construct(
            $numero !== null
                ? "Este paciente já possui o atendimento {$numero} em andamento nesta unidade (RN-07)."
                : 'Este paciente já possui um atendimento em andamento nesta unidade (RN-07).'
        );
    }
}

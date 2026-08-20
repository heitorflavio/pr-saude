<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\StatusAtendimento;

/**
 * RN-13: toda mudança de status passa por `StatusAtendimento::podeTransitarPara()`.
 *
 * A máquina de estados existe para impedir sequências que não fazem sentido clínico —
 * um atendimento não vai de `AGUARDANDO_TRIAGEM` direto para `EM_EXAME`, porque
 * ninguém colheu material de um paciente que não foi avaliado.
 *
 * RN-14: `FINALIZADO` e `CANCELADO` são terminais. Reabrir um atendimento encerrado
 * apagaria a fronteira do episódio — o que veio depois é outro atendimento, com outro
 * número.
 */
final class TransicaoInvalidaException extends DominioException
{
    public function __construct(
        public readonly StatusAtendimento $origem,
        public readonly StatusAtendimento $destino,
    ) {
        $permitidas = array_map(
            fn (StatusAtendimento $status) => $status->rotulo(),
            $origem->transicoesPermitidas()
        );

        $mensagem = $origem->ehTerminal()
            ? "O atendimento está {$origem->rotulo()} e não admite nova transição (RN-14)."
            : sprintf(
                'Transição inválida: %s → %s. A partir de %s só é possível ir para: %s.',
                $origem->rotulo(),
                $destino->rotulo(),
                $origem->rotulo(),
                implode(', ', $permitidas),
            );

        parent::__construct($mensagem);
    }
}

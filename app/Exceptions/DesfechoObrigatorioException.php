<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * RN-14: `FINALIZADO` exige desfecho.
 *
 * Um atendimento encerrado sem registro do que aconteceu com o paciente — alta,
 * internação, evasão, óbito — é um episódio sem conclusão. O `CHECK ck_atend_desfecho`
 * recusa no banco; esta exceção recusa antes, com mensagem útil.
 */
final class DesfechoObrigatorioException extends DominioException
{
    public static function paraFinalizar(): self
    {
        return new self(
            'Finalizar o atendimento exige informar o desfecho: alta, encaminhamento, '
            .'internação, evasão, óbito ou transferência (RN-14).'
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

final class FilaInvalidaException extends DominioException
{
    public static function atendimentoEncerrado(): self
    {
        return new self('Não é possível mexer na fila de um atendimento já encerrado.');
    }

    public static function semClassificacao(): self
    {
        return new self(
            'O atendimento precisa ser triado antes de entrar na fila de um profissional: '
            .'sem classificação de risco não há como ordená-lo (RN-10).'
        );
    }

    public static function semItemAtivo(): self
    {
        return new self('Este atendimento não está em nenhuma fila.');
    }

    public static function mesmoProfissional(): self
    {
        return new self('O paciente já está na fila deste profissional.');
    }

    public static function justificativaObrigatoria(): self
    {
        return new self('Informe o motivo da transferência entre filas.');
    }
}

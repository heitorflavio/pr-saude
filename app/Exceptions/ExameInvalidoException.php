<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\SituacaoExame;

final class ExameInvalidoException extends DominioException
{
    public static function transicao(SituacaoExame $origem, SituacaoExame $destino): self
    {
        return new self("Não é permitido mover o exame de {$origem->rotulo()} para {$destino->rotulo()}.");
    }

    public static function resultadoExistente(): self
    {
        return new self('Esta solicitação já possui resultado.');
    }

    public static function valorCriticoSemCiencia(): self
    {
        return new self('Resultado crítico exige liberação por médico, registrando sua ciência (RN-25).');
    }

    public static function atendimentoEncerrado(): self
    {
        return new self('Não é possível solicitar exame em atendimento encerrado.');
    }
}

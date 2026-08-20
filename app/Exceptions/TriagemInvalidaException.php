<?php

declare(strict_types=1);

namespace App\Exceptions;

final class TriagemInvalidaException extends DominioException
{
    public static function atendimentoJaTriado(): self
    {
        return new self(
            'Este atendimento já foi triado. Para corrigir a gravidade, use a '
            .'reclassificação de risco — a triagem anterior permanece no histórico (doc §7.5).'
        );
    }

    public static function atendimentoEncerrado(): self
    {
        return new self('Não é possível triar um atendimento já encerrado.');
    }

    public static function semTriagemAnterior(): self
    {
        return new self(
            'Não há triagem para reclassificar. Realize a triagem inicial primeiro.'
        );
    }

    public static function justificativaObrigatoria(): self
    {
        return new self(
            'A reclassificação exige justificativa: é ela que permite reconstruir o '
            .'raciocínio clínico numa auditoria de evento adverso (doc §7.5).'
        );
    }
}

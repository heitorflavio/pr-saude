<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DiagnosticoInvalidoException extends DominioException
{
    public static function atendimentoEncerrado(): self
    {
        return new self('Não é possível registrar diagnóstico em atendimento encerrado.');
    }

    public static function cid10Inexistente(string $codigo): self
    {
        return new self("O código CID-10 \"{$codigo}\" não existe no catálogo.");
    }

    /**
     * Um atendimento tem um diagnóstico principal — é ele que responde "por que este
     * paciente esteve aqui" na estatística e no faturamento. Dois principais tornam a
     * resposta ambígua.
     */
    public static function principalJaDefinido(string $codigo): self
    {
        return new self(
            "Já existe diagnóstico principal neste atendimento ({$codigo}). Marque o novo "
            .'como principal apenas se o anterior deixar de sê-lo.'
        );
    }

    public static function suspeitaNaoPodeSerPrincipal(): self
    {
        return new self(
            'Uma hipótese ainda em suspeita não é o diagnóstico principal do atendimento. '
            .'Confirme-a como definitiva antes de marcá-la.'
        );
    }

    public static function duplicado(string $codigo): self
    {
        return new self("O CID-10 {$codigo} já foi registrado neste atendimento.");
    }
}

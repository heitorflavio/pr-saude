<?php

declare(strict_types=1);

namespace App\Enums;

enum SituacaoExame: string
{
    case Solicitado = 'SOLICITADO';
    case Coletado = 'COLETADO';
    case EmExecucao = 'EM_EXECUCAO';
    case Concluido = 'CONCLUIDO';
    case Liberado = 'LIBERADO';
    case Cancelado = 'CANCELADO';

    /** @return list<self> */
    public function transicoesPermitidas(): array
    {
        return match ($this) {
            self::Solicitado => [self::Coletado, self::Cancelado],
            self::Coletado => [self::EmExecucao, self::Cancelado],
            self::EmExecucao => [self::Concluido],
            self::Concluido => [self::Liberado],
            self::Liberado, self::Cancelado => [],
        };
    }

    public function podeTransitarPara(self $destino): bool
    {
        return in_array($destino, $this->transicoesPermitidas(), true);
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::Coletado => 'Coletado',
            self::EmExecucao => 'Em execução',
            self::Concluido => 'Concluído — aguardando liberação',
            self::Liberado => 'Liberado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function rotuloPaciente(): string
    {
        return match ($this) {
            self::Solicitado => 'Aguardando realização do exame',
            self::Coletado, self::EmExecucao => 'Exame em realização',
            self::Concluido => 'Exame realizado — resultado em análise médica',
            self::Liberado => 'Resultado disponível',
            self::Cancelado => 'Exame cancelado',
        };
    }
}

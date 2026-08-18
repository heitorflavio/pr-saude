<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * As cinco cores do Protocolo de Manchester. Espelha `classificacao_risco.cor_nome`.
 *
 * Os atributos operacionais (tempo-alvo, peso de ordenacao, hex) vivem na TABELA
 * `classificacao_risco`, nao aqui -- D-03: um hospital que adote outro protocolo altera
 * dados, nao codigo. Este enum existe para dar nome tipado a cor em codigo e para
 * carregar o que a tabela nao tem: o rotulo textual e o icone.
 *
 * RNF-15: a cor NUNCA e o unico indicador. Toda exibicao de prioridade usa
 * cor + rotulo() + icone(). Cerca de 8% dos homens tem alguma deficiencia de visao de
 * cores; num pronto-socorro isso e risco assistencial, nao detalhe de acessibilidade.
 */
enum CorPrioridade: string
{
    case Vermelho = 'VERMELHO';
    case Laranja = 'LARANJA';
    case Amarelo = 'AMARELO';
    case Verde = 'VERDE';
    case Azul = 'AZUL';

    /** Nome clinico do nivel, como na coluna `classificacao_risco.nome`. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Vermelho => 'Emergencia',
            self::Laranja => 'Muito urgente',
            self::Amarelo => 'Urgente',
            self::Verde => 'Pouco urgente',
            self::Azul => 'Nao urgente',
        };
    }

    /** Identificador de icone (lucide), para acompanhar cor e rotulo. */
    public function icone(): string
    {
        return match ($this) {
            self::Vermelho => 'octagon-alert',
            self::Laranja => 'triangle-alert',
            self::Amarelo => 'circle-alert',
            self::Verde => 'circle-check',
            self::Azul => 'circle-dot',
        };
    }

    /** Peso de ordenacao da fila: menor = mais prioritario (RN-10). */
    public function pesoOrdenacao(): int
    {
        return match ($this) {
            self::Vermelho => 1,
            self::Laranja => 2,
            self::Amarelo => 3,
            self::Verde => 4,
            self::Azul => 5,
        };
    }

    /** Tempo-alvo oficial de espera, em minutos (Manchester). */
    public function tempoAlvoMinutos(): int
    {
        return match ($this) {
            self::Vermelho => 0,
            self::Laranja => 10,
            self::Amarelo => 60,
            self::Verde => 120,
            self::Azul => 240,
        };
    }

    /** RN-11: vermelho nao entra em fila, vai direto para atendimento. */
    public function exigeAtendimentoImediato(): bool
    {
        return $this === self::Vermelho;
    }
}

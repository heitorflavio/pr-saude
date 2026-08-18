<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Vias de administracao de medicamento. Espelha o ENUM `via` de `prescricao_item`,
 * `administracao_medicamento` e `medicamento.classe_via`.
 *
 * `rotuloPaciente()` existe porque o portal do paciente usa linguagem acessivel
 * (doc 12.3): "na veia", nunca "IV". A sigla e vocabulario da equipe, nao do paciente.
 */
enum ViaAdministracao: string
{
    case Oral = 'ORAL';
    case Intravenosa = 'IV';
    case Intramuscular = 'IM';
    case Subcutanea = 'SC';
    case Topico = 'TOPICO';
    case Inalatorio = 'INALATORIO';
    case Retal = 'RETAL';
    case Oftalmico = 'OFTALMICO';
    case Sublingual = 'SL';
    case Outra = 'OUTRA';

    /** Rotulo para a equipe. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Oral => 'Oral',
            self::Intravenosa => 'Intravenosa (IV)',
            self::Intramuscular => 'Intramuscular (IM)',
            self::Subcutanea => 'Subcutanea (SC)',
            self::Topico => 'Topica',
            self::Inalatorio => 'Inalatoria',
            self::Retal => 'Retal',
            self::Oftalmico => 'Oftalmica',
            self::Sublingual => 'Sublingual (SL)',
            self::Outra => 'Outra',
        };
    }

    /** Rotulo para o portal do paciente, sem sigla e sem jargao (doc 12.3). */
    public function rotuloPaciente(): string
    {
        return match ($this) {
            self::Oral => 'pela boca',
            self::Intravenosa => 'na veia',
            self::Intramuscular => 'no musculo',
            self::Subcutanea => 'sob a pele',
            self::Topico => 'na pele',
            self::Inalatorio => 'inalada',
            self::Retal => 'via retal',
            self::Oftalmico => 'no olho',
            self::Sublingual => 'debaixo da lingua',
            self::Outra => 'outra via',
        };
    }

    /** Vias injetaveis exigem cuidado adicional de conferencia. */
    public function ehInjetavel(): bool
    {
        return in_array($this, [self::Intravenosa, self::Intramuscular, self::Subcutanea], strict: true);
    }
}

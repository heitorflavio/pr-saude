<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de registro no prontuario. Espelha o ENUM `tipo` de `registro_clinico`.
 */
enum TipoRegistroClinico: string
{
    case Anamnese = 'ANAMNESE';
    case EvolucaoMedica = 'EVOLUCAO_MEDICA';
    case EvolucaoEnfermagem = 'EVOLUCAO_ENFERMAGEM';
    case Observacao = 'OBSERVACAO';
    case Adendo = 'ADENDO';
    case SumarioAlta = 'SUMARIO_ALTA';
    case Intercorrencia = 'INTERCORRENCIA';

    public function rotulo(): string
    {
        return match ($this) {
            self::Anamnese => 'Anamnese',
            self::EvolucaoMedica => 'Evolucao medica',
            self::EvolucaoEnfermagem => 'Evolucao de enfermagem',
            self::Observacao => 'Observacao',
            self::Adendo => 'Adendo (retificacao)',
            self::SumarioAlta => 'Sumario de alta',
            self::Intercorrencia => 'Intercorrencia',
        };
    }

    /**
     * RN-16: adendo e o unico tipo que exige `registro_retificado_id` e
     * `motivo_retificacao`. O banco garante isso pelo CHECK ck_registro_adendo;
     * este metodo permite a Action recusar antes de chegar la, com mensagem util.
     */
    public function exigeRegistroRetificado(): bool
    {
        return $this === self::Adendo;
    }

    /** Tipos que seguem a estrutura SOAP em quatro colunas (doc 9.2). */
    public function usaSoap(): bool
    {
        return in_array($this, [
            self::Anamnese,
            self::EvolucaoMedica,
            self::EvolucaoEnfermagem,
        ], strict: true);
    }
}

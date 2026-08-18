<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Maquina de estados do atendimento (doc 6.1 a 6.3).
 *
 * As transicoes legais moram aqui, e nao em controllers, por dois motivos: uma
 * transicao ilegal vira erro detectavel em teste unitario sem banco (RN-13), e a regra
 * nao se espalha. A alternativa -- tabela `transicao_permitida` -- daria flexibilidade
 * de configuracao que este dominio nao pede: a maquina de estados de um pronto-socorro
 * nao muda por parametrizacao, muda por decisao clinica que exige revisao de codigo.
 */
enum StatusAtendimento: string
{
    case AguardandoTriagem = 'AGUARDANDO_TRIAGEM';
    case AguardandoAtendimento = 'AGUARDANDO_ATENDIMENTO';
    case EmAtendimento = 'EM_ATENDIMENTO';
    case AguardandoExame = 'AGUARDANDO_EXAME';
    case EmExame = 'EM_EXAME';
    case AguardandoMedicacao = 'AGUARDANDO_MEDICACAO';
    case EmObservacao = 'EM_OBSERVACAO';
    case Finalizado = 'FINALIZADO';
    case Cancelado = 'CANCELADO';

    /**
     * Tabela de transicoes da doc 6.2.
     *
     * @return array<int, self>
     */
    public function transicoesPermitidas(): array
    {
        return match ($this) {
            self::AguardandoTriagem => [
                self::AguardandoAtendimento, self::EmAtendimento, self::Cancelado,
            ],
            self::AguardandoAtendimento => [
                self::EmAtendimento, self::AguardandoAtendimento, self::Cancelado,
            ],
            self::EmAtendimento => [
                self::AguardandoExame, self::AguardandoMedicacao,
                self::EmObservacao, self::Finalizado, self::Cancelado,
            ],
            self::AguardandoExame => [
                self::EmExame, self::EmAtendimento, self::Cancelado,
            ],
            self::EmExame => [
                self::EmAtendimento, self::EmObservacao, self::Cancelado,
            ],
            self::AguardandoMedicacao => [
                self::EmObservacao, self::EmAtendimento, self::Finalizado, self::Cancelado,
            ],
            self::EmObservacao => [
                self::EmAtendimento, self::AguardandoExame,
                self::AguardandoMedicacao, self::Finalizado, self::Cancelado,
            ],
            // RN-14: estados terminais nao tem saida.
            self::Finalizado, self::Cancelado => [],
        };
    }

    public function podeTransitarPara(self $destino): bool
    {
        return in_array($destino, $this->transicoesPermitidas(), strict: true);
    }

    public function ehTerminal(): bool
    {
        return $this->transicoesPermitidas() === [];
    }

    /** Rotulo para a equipe, em pt-BR. */
    public function rotulo(): string
    {
        return match ($this) {
            self::AguardandoTriagem => 'Aguardando triagem',
            self::AguardandoAtendimento => 'Aguardando atendimento',
            self::EmAtendimento => 'Em atendimento',
            self::AguardandoExame => 'Aguardando exame',
            self::EmExame => 'Em exame',
            self::AguardandoMedicacao => 'Aguardando medicacao',
            self::EmObservacao => 'Em observacao',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Rotulo exibido ao paciente no portal, em linguagem acessivel (doc 12.3).
     * O paciente nunca ve `AGUARDANDO_EXAME`; ve "Aguardando realizacao de exame".
     */
    public function rotuloPaciente(): string
    {
        return match ($this) {
            self::AguardandoTriagem => 'Aguardando avaliacao inicial',
            self::AguardandoAtendimento => 'Na fila para atendimento',
            self::EmAtendimento => 'Em atendimento',
            self::AguardandoExame => 'Aguardando realizacao de exame',
            self::EmExame => 'Exame em andamento',
            self::AguardandoMedicacao => 'Aguardando medicacao',
            self::EmObservacao => 'Em observacao',
            self::Finalizado => 'Atendimento concluido',
            self::Cancelado => 'Atendimento cancelado',
        };
    }
}

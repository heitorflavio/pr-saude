<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Criticidade da espera na fila (doc 7.3.1).
 *
 * Este enum NAO reordena a fila. Envelhecimento automatico de prioridade (aging) e
 * proibido, e a justificativa e clinica, nao tecnica: um paciente azul que espera tres
 * horas nao se torna mais grave que um laranja que acabou de chegar. Promove-lo
 * automaticamente inverteria a logica de seguranca do paciente.
 *
 * A resposta correta e tornar a espera VISIVEL e convocar um profissional a
 * reclassificar -- a decisao permanece clinica; o sistema apenas garante que ninguem
 * seja esquecido.
 */
enum SituacaoEspera: string
{
    case AtendimentoImediato = 'ATENDIMENTO_IMEDIATO';
    case DentroDoAlvo = 'DENTRO_DO_ALVO';
    case ProximoDoAlvo = 'PROXIMO_DO_ALVO';
    case AlvoExcedido = 'ALVO_EXCEDIDO';
    case EsperaCritica = 'ESPERA_CRITICA';

    public function rotulo(): string
    {
        return match ($this) {
            self::AtendimentoImediato => 'Atendimento imediato',
            self::DentroDoAlvo => 'Dentro do tempo-alvo',
            self::ProximoDoAlvo => 'Proximo do tempo-alvo',
            self::AlvoExcedido => 'Tempo-alvo excedido',
            self::EsperaCritica => 'Espera critica',
        };
    }

    /** O que o sistema faz em cada caso (tabela da doc 7.3.1). */
    public function acaoDoSistema(): string
    {
        return match ($this) {
            self::AtendimentoImediato => 'Vermelho nunca espera: atendimento imediato.',
            self::DentroDoAlvo => 'Nenhuma.',
            self::ProximoDoAlvo => 'Destaque visual na fila do profissional.',
            self::AlvoExcedido => 'Alerta no painel, registro no indicador de qualidade e sugestao de reavaliacao de risco.',
            self::EsperaCritica => 'Notificacao a coordenacao do plantao e entrada no relatorio gerencial.',
        };
    }

    /** RF-33: a partir daqui o sistema sugere reavaliacao de risco. */
    public function sugereReavaliacao(): bool
    {
        return $this === self::AlvoExcedido || $this === self::EsperaCritica;
    }
}

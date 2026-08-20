<?php

declare(strict_types=1);

namespace App\Services\Fila;

use App\Enums\SituacaoEspera;

/**
 * doc §7.3.1 — o problema da inanição, e por que ele **não** é resolvido por
 * envelhecimento de prioridade.
 *
 * Ordenação estritamente lexicográfica por prioridade produz *starvation*: se pacientes
 * laranja chegam continuamente, o azul nunca é chamado. A solução clássica em
 * escalonamento de processos é *aging* — aumentar a prioridade conforme a espera cresce.
 *
 * **Aqui essa solução é inadequada, e a justificativa é clínica, não técnica.** Um
 * paciente azul que espera três horas não se torna mais grave que um laranja que acabou
 * de chegar. Promovê-lo automaticamente inverteria a lógica de segurança do paciente e
 * poderia matar alguém.
 *
 * Este serviço, portanto, **não reordena nada**. Ele classifica a criticidade da espera
 * para exibição e alerta — e o que o sistema faz é convocar um profissional a
 * **reclassificar**, porque quem espera três horas pode, de fato, ter piorado. A decisão
 * permanece clínica; o sistema apenas garante que ninguém seja esquecido.
 */
final class AvaliadorEsperaService
{
    public function avaliar(int $esperaMinutos, int $tempoAlvoMinutos): SituacaoEspera
    {
        // Vermelho tem tempo-alvo zero: nunca espera, e dividir por zero não faria
        // sentido nem aritmética nem clinicamente.
        if ($tempoAlvoMinutos === 0) {
            return SituacaoEspera::AtendimentoImediato;
        }

        $razao = $esperaMinutos / $tempoAlvoMinutos;

        return match (true) {
            $razao < 0.75 => SituacaoEspera::DentroDoAlvo,
            $razao < 1.00 => SituacaoEspera::ProximoDoAlvo,
            $razao < 2.00 => SituacaoEspera::AlvoExcedido,
            default => SituacaoEspera::EsperaCritica,
        };
    }
}

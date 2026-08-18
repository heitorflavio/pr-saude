<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassificacaoRisco;
use Illuminate\Database\Seeder;

/**
 * Os cinco niveis do Protocolo de Manchester, com os tempos-alvo oficiais
 * (0/10/60/120/240 min). Carga inicial identica a do schema.sql.
 *
 * D-03: estes valores sao DADO, nao codigo. Um hospital que adote outro protocolo
 * altera esta carga -- e a fila, as views e os indicadores continuam funcionando.
 */
class ClassificacaoRiscoSeeder extends Seeder
{
    public function run(): void
    {
        $niveis = [
            [
                'id' => 1,
                'nome' => 'Emergência',
                'cor_nome' => 'VERMELHO',
                'cor_hex' => '#D32F2F',
                'tempo_alvo_minutos' => 0,
                'peso_ordenacao' => 1,
                'exige_atendimento_imediato' => true,
                'descricao' => 'Atendimento imediato. Risco iminente de morte.',
            ],
            [
                'id' => 2,
                'nome' => 'Muito urgente',
                'cor_nome' => 'LARANJA',
                'cor_hex' => '#F57C00',
                'tempo_alvo_minutos' => 10,
                'peso_ordenacao' => 2,
                'exige_atendimento_imediato' => false,
                'descricao' => 'Atendimento praticamente imediato.',
            ],
            [
                'id' => 3,
                'nome' => 'Urgente',
                'cor_nome' => 'AMARELO',
                'cor_hex' => '#FBC02D',
                'tempo_alvo_minutos' => 60,
                'peso_ordenacao' => 3,
                'exige_atendimento_imediato' => false,
                'descricao' => 'Atendimento rápido, mas o paciente pode aguardar.',
            ],
            [
                'id' => 4,
                'nome' => 'Pouco urgente',
                'cor_nome' => 'VERDE',
                'cor_hex' => '#388E3C',
                'tempo_alvo_minutos' => 120,
                'peso_ordenacao' => 4,
                'exige_atendimento_imediato' => false,
                'descricao' => 'Pode aguardar atendimento ou ser encaminhado.',
            ],
            [
                'id' => 5,
                'nome' => 'Não urgente',
                'cor_nome' => 'AZUL',
                'cor_hex' => '#1976D2',
                'tempo_alvo_minutos' => 240,
                'peso_ordenacao' => 5,
                'exige_atendimento_imediato' => false,
                'descricao' => 'Pode aguardar atendimento ou ser encaminhado.',
            ],
        ];

        foreach ($niveis as $nivel) {
            ClassificacaoRisco::updateOrCreate(['id' => $nivel['id']], $nivel);
        }
    }
}

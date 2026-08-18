<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\AtendimentoStatusHistorico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AtendimentoStatusHistorico>
 */
class AtendimentoStatusHistoricoFactory extends Factory
{
    protected $model = AtendimentoStatusHistorico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'status_anterior' => 'AGUARDANDO_TRIAGEM',
            'status_novo' => 'AGUARDANDO_ATENDIMENTO',
            'alterado_por' => User::factory(),
            'permanencia_segundos' => fake()->numberBetween(60, 3600),
            'criado_em' => now(),
        ];
    }
}

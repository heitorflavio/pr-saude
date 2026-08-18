<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\Prescricao;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescricao>
 */
class PrescricaoFactory extends Factory
{
    protected $model = Prescricao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'prescrito_por' => Profissional::factory()->medico(),
            'status' => 'VIGENTE',
            'vigencia_inicio' => now(),
            'criado_em' => now(),
        ];
    }

    public function suspensa(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'SUSPENSA',
            'suspensa_em' => now(),
            'motivo_suspensao' => 'Suspensa por reavaliação clínica.',
        ]);
    }
}

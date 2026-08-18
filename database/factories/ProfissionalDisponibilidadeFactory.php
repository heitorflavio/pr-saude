<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profissional;
use App\Models\ProfissionalDisponibilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfissionalDisponibilidade>
 */
class ProfissionalDisponibilidadeFactory extends Factory
{
    protected $model = ProfissionalDisponibilidade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profissional_id' => Profissional::factory(),
            'situacao' => 'DISPONIVEL',
            'inicio_em' => now()->subHours(2),
            // `fim_em` nulo = situação vigente. É o que a vw_carga_profissional usa.
            'fim_em' => null,
        ];
    }

    public function encerrada(): static
    {
        return $this->state(fn (array $attributes) => ['fim_em' => now()]);
    }

    public function foraPlantao(): static
    {
        return $this->state(fn (array $attributes) => ['situacao' => 'FORA_PLANTAO']);
    }
}

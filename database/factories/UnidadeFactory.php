<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unidade>
 */
class UnidadeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'UPA '.fake()->unique()->city(),
            'cnes' => fake()->unique()->numerify('#######'),
            'fuso_horario' => 'America/Sao_Paulo',
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }
}

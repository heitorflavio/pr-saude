<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Exame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exame>
 */
class ExameFactory extends Factory
{
    protected $model = Exame::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('EX###')),
            'nome' => ucfirst(fake()->unique()->words(3, true)),
            'tipo' => 'LABORATORIAL',
            'prazo_padrao_minutos' => 60,
            'ativo' => true,
        ];
    }

    public function imagem(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'IMAGEM',
            'prazo_padrao_minutos' => 90,
        ]);
    }
}

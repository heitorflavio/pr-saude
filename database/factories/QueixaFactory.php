<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Queixa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queixa>
 */
class QueixaFactory extends Factory
{
    protected $model = Queixa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descricao' => ucfirst(fake()->unique()->words(3, true)),
            'fluxograma_manchester' => fake()->randomElement([
                'Dor torácica', 'Dispneia em adulto', 'Dor abdominal em adulto', 'Cefaleia',
            ]),
            'ativo' => true,
        ];
    }
}

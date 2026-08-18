<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Paciente;
use App\Models\PacienteCondicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PacienteCondicao>
 */
class PacienteCondicaoFactory extends Factory
{
    protected $model = PacienteCondicao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'descricao' => fake()->randomElement([
                'Hipertensão arterial sistêmica',
                'Diabetes mellitus tipo 2',
                'Asma',
                'Insuficiência cardíaca',
                'Epilepsia',
            ]),
            'desde' => fake()->dateTimeBetween('-20 years', '-1 year')->format('Y-m-d'),
        ];
    }
}

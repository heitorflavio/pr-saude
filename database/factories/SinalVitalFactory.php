<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\Profissional;
use App\Models\SinalVital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Valores dentro das faixas dos CHECK ck_sinal_dor, ck_sinal_spo2 e ck_sinal_temp.
 *
 * @extends Factory<SinalVital>
 */
class SinalVitalFactory extends Factory
{
    protected $model = SinalVital::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'pressao_sistolica' => fake()->numberBetween(90, 160),
            'pressao_diastolica' => fake()->numberBetween(55, 100),
            'frequencia_cardiaca' => fake()->numberBetween(50, 120),
            'frequencia_respiratoria' => fake()->numberBetween(12, 28),
            'saturacao_o2' => fake()->randomFloat(1, 88, 100),
            'temperatura' => fake()->randomFloat(1, 35.5, 40.0),
            'glicemia' => fake()->randomFloat(1, 60, 250),
            'escala_dor' => fake()->numberBetween(0, 10),
            'aferido_por' => Profissional::factory()->enfermeiro(),
            'aferido_em' => now(),
        ];
    }

    public function doAtendimento(Atendimento $atendimento): static
    {
        return $this->state(fn (array $attributes) => ['atendimento_id' => $atendimento->id]);
    }
}

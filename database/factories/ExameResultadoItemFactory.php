<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExameResultado;
use App\Models\ExameResultadoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExameResultadoItem>
 */
class ExameResultadoItemFactory extends Factory
{
    protected $model = ExameResultadoItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exame_resultado_id' => ExameResultado::factory(),
            'analito' => fake()->randomElement(['Hemoglobina', 'Leucócitos', 'Potássio', 'Creatinina']),
            'valor' => (string) fake()->randomFloat(2, 1, 20),
            'unidade' => 'mg/dL',
            // doc 11.3: a faixa é gravada no resultado, não só no catálogo.
            'referencia_min' => 3.5000,
            'referencia_max' => 5.5000,
            'sinalizacao' => 'NORMAL',
        ];
    }

    public function critico(): static
    {
        return $this->state(fn (array $attributes) => ['sinalizacao' => 'CRITICO']);
    }
}

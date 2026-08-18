<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\Cid10;
use App\Models\Diagnostico;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnostico>
 */
class DiagnosticoFactory extends Factory
{
    protected $model = Diagnostico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            // Depende do Cid10Seeder. R51 = Cefaleia.
            'cid10_codigo' => Cid10::query()->inRandomOrder()->value('codigo') ?? 'R51',
            'natureza' => 'SUSPEITA',
            'principal' => false,
            'registrado_por' => Profissional::factory()->medico(),
            'criado_em' => now(),
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'principal' => true,
            'natureza' => 'DEFINITIVO',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aprazamento;
use App\Models\PrescricaoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aprazamento>
 */
class AprazamentoFactory extends Factory
{
    protected $model = Aprazamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescricao_item_id' => PrescricaoItem::factory(),
            'sequencia' => 1,
            'horario_previsto' => now()->startOfHour(),
            'situacao' => 'PENDENTE',
        ];
    }

    public function administrada(): static
    {
        return $this->state(fn (array $attributes) => ['situacao' => 'ADMINISTRADA']);
    }

    public function atrasada(int $minutos = 45): static
    {
        return $this->state(fn (array $attributes) => [
            'horario_previsto' => now()->subMinutes($minutos),
        ]);
    }
}

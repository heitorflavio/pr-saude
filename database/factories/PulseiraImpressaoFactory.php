<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\PulseiraImpressao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PulseiraImpressao>
 */
class PulseiraImpressaoFactory extends Factory
{
    protected $model = PulseiraImpressao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'motivo' => 'PRIMEIRA',
            'impressa_por' => Profissional::factory()->recepcao(),
            'criado_em' => now(),
        ];
    }

    /** RF-16: a reimpressão usa o mesmo token (RN-03). */
    public function reimpressao(string $motivo = 'REIMPRESSAO'): static
    {
        return $this->state(fn (array $attributes) => ['motivo' => $motivo]);
    }

    /** RN-09: reclassificação obriga nova pulseira, com a nova cor. */
    public function porReclassificacao(): static
    {
        return $this->state(fn (array $attributes) => ['motivo' => 'RECLASSIFICACAO']);
    }
}

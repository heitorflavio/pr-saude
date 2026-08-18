<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescricaoItem>
 */
class PrescricaoItemFactory extends Factory
{
    protected $model = PrescricaoItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescricao_id' => Prescricao::factory(),
            'medicamento_id' => Medicamento::factory(),
            // ck_item_dose exige dose > 0.
            'dose' => fake()->randomFloat(3, 0.5, 1000),
            'unidade_dose' => 'mg',
            'via' => 'IV',
            // ck_item_frequencia: medicação de horário exige frequência.
            'frequencia_horas' => 6,
            'duracao_horas' => 48,
            'se_necessario' => false,
            'status' => 'VIGENTE',
        ];
    }

    /** doc 10.5: medicação SOS/PRN não é aprazada, e por isso não tem frequência. */
    public function seNecessario(): static
    {
        return $this->state(fn (array $attributes) => [
            'se_necessario' => true,
            'frequencia_horas' => null,
        ]);
    }

    public function doMedicamento(Medicamento $medicamento): static
    {
        return $this->state(fn (array $attributes) => [
            'medicamento_id' => $medicamento->id,
            'via' => $medicamento->classe_via,
        ]);
    }
}

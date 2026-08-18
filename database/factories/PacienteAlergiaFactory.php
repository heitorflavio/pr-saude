<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\PacienteAlergia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PacienteAlergia>
 */
class PacienteAlergiaFactory extends Factory
{
    protected $model = PacienteAlergia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'substancia' => fake()->randomElement([
                'Dipirona sódica', 'Amoxicilina', 'Ácido acetilsalicílico',
                'Sulfato de morfina', 'Metronidazol',
            ]),
            'gravidade' => fake()->randomElement(['LEVE', 'MODERADA', 'GRAVE', 'DESCONHECIDA']),
            'reacao' => fake()->randomElement(['Urticária', 'Broncoespasmo', 'Angioedema', 'Choque anafilático']),
        ];
    }

    /** RN-21: alergia vinculada ao catálogo -- o princípio ativo vem do medicamento. */
    public function doMedicamento(Medicamento $medicamento): static
    {
        return $this->state(fn (array $attributes) => [
            'medicamento_id' => $medicamento->id,
            'substancia' => $medicamento->principio_ativo,
        ]);
    }

    public function grave(): static
    {
        return $this->state(fn (array $attributes) => ['gravidade' => 'GRAVE']);
    }
}

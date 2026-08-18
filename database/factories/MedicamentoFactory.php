<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Medicamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicamento>
 */
class MedicamentoFactory extends Factory
{
    protected $model = Medicamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome_comercial' => ucfirst(fake()->unique()->word()),
            'principio_ativo' => ucfirst(fake()->unique()->word()),
            'concentracao' => fake()->numberBetween(1, 500).' mg/mL',
            'forma_farmaceutica' => fake()->randomElement(['comprimido', 'ampola', 'frasco-ampola']),
            'classe_via' => 'IV',
            'injetavel' => true,
            'alta_vigilancia' => false,
            'controlado' => false,
            'unidade_dose_padrao' => 'mg',
            'ativo' => true,
        ];
    }

    /** RN-22: exige dupla checagem por um segundo profissional. */
    public function altaVigilancia(): static
    {
        return $this->state(fn (array $attributes) => ['alta_vigilancia' => true]);
    }

    public function controlado(): static
    {
        return $this->state(fn (array $attributes) => ['controlado' => true]);
    }

    /** RN-21: a verificação de alergia é por princípio ativo. */
    public function comPrincipioAtivo(string $principioAtivo): static
    {
        return $this->state(fn (array $attributes) => ['principio_ativo' => $principioAtivo]);
    }
}

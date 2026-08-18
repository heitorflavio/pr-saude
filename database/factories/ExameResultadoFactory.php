<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExameResultado;
use App\Models\ExameSolicitacao;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExameResultado>
 */
class ExameResultadoFactory extends Factory
{
    protected $model = ExameResultado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exame_solicitacao_id' => ExameSolicitacao::factory(),
            'laudo' => fake()->paragraph(),
            'conclusao' => fake()->sentence(),
            'possui_valor_critico' => false,
            'executado_por' => Profissional::factory()->laboratorio(),
            'executado_em' => now(),
            // RN-24: só fica visível ao paciente depois de liberado.
            'visivel_ao_paciente' => false,
            'criado_em' => now(),
        ];
    }

    /**
     * RN-24: o CHECK ck_result_liberacao recusa `visivel_ao_paciente = TRUE` sem
     * `liberado_por` e `liberado_em`. Esta state preenche os três juntos.
     */
    public function liberado(): static
    {
        return $this->state(fn (array $attributes) => [
            'liberado_por' => Profissional::factory()->medico(),
            'liberado_em' => now(),
            'visivel_ao_paciente' => true,
        ]);
    }

    /** RN-25: valor crítico bloqueia a liberação antes da ciência médica. */
    public function comValorCritico(): static
    {
        return $this->state(fn (array $attributes) => ['possui_valor_critico' => true]);
    }
}

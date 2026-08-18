<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\Exame;
use App\Models\ExameSolicitacao;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExameSolicitacao>
 */
class ExameSolicitacaoFactory extends Factory
{
    protected $model = ExameSolicitacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'exame_id' => Exame::factory(),
            'solicitado_por' => Profissional::factory()->medico(),
            'carater' => 'ROTINA',
            'situacao' => 'SOLICITADO',
            'solicitado_em' => now(),
        ];
    }

    public function urgente(): static
    {
        return $this->state(fn (array $attributes) => ['carater' => 'URGENTE']);
    }

    public function coletado(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao' => 'COLETADO',
            'coletado_em' => now(),
            'coletado_por' => Profissional::factory()->tecnicoEnfermagem(),
        ]);
    }

    /** ck_solic_cancelamento: cancelamento exige motivo. */
    public function cancelado(string $motivo = 'Solicitação duplicada.'): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao' => 'CANCELADO',
            'cancelado_em' => now(),
            'cancelado_por' => Profissional::factory()->medico(),
            'motivo_cancelamento' => $motivo,
        ]);
    }
}

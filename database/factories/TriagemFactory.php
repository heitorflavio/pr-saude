<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\Profissional;
use App\Models\Triagem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Triagem>
 */
class TriagemFactory extends Factory
{
    protected $model = Triagem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            // 4 = VERDE (pouco urgente). Catálogo do ClassificacaoRiscoSeeder.
            'classificacao_risco_id' => 4,
            'realizada_por' => Profissional::factory()->enfermeiro(),
            'queixa_principal' => fake()->sentence(),
            'reclassificacao' => false,
            'criado_em' => now(),
        ];
    }

    public function comRisco(int $classificacaoRiscoId): static
    {
        return $this->state(fn (array $attributes) => [
            'classificacao_risco_id' => $classificacaoRiscoId,
        ]);
    }

    /**
     * doc 7.5: a reclassificação é encadeada e a triagem anterior permanece intacta.
     */
    public function reclassificacaoDe(Triagem $anterior, int $novoRiscoId): static
    {
        return $this->state(fn (array $attributes) => [
            'atendimento_id' => $anterior->atendimento_id,
            'classificacao_risco_id' => $novoRiscoId,
            'reclassificacao' => true,
            'triagem_anterior_id' => $anterior->id,
            'justificativa_classificacao' => 'Piora do quadro durante a espera.',
        ]);
    }
}

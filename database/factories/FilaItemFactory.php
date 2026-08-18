<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilaItem>
 */
class FilaItemFactory extends Factory
{
    protected $model = FilaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'profissional_id' => Profissional::factory()->medico(),
            'classificacao_risco_id' => 4,
            'situacao' => 'AGUARDANDO',
            'entrou_em' => now(),
            'criado_por' => User::factory(),
        ];
    }

    public function comRisco(int $classificacaoRiscoId): static
    {
        return $this->state(fn (array $attributes) => [
            'classificacao_risco_id' => $classificacaoRiscoId,
        ]);
    }

    /** Permite montar cenários de fila com tempos de espera controlados. */
    public function esperandoHa(int $minutos): static
    {
        return $this->state(fn (array $attributes) => [
            'entrou_em' => now()->subMinutes($minutos),
        ]);
    }

    /** Fila geral, sem atribuição a profissional. */
    public function semProfissional(): static
    {
        return $this->state(fn (array $attributes) => ['profissional_id' => null]);
    }
}

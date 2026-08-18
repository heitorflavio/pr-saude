<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\AtendimentoSintoma;
use App\Models\Queixa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AtendimentoSintoma>
 */
class AtendimentoSintomaFactory extends Factory
{
    protected $model = AtendimentoSintoma::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            // O CHECK ck_sintoma_conteudo exige queixa OU descrição livre.
            'queixa_id' => Queixa::factory(),
            'descricao_livre' => null,
        ];
    }

    public function descricaoLivre(string $descricao): static
    {
        return $this->state(fn (array $attributes) => [
            'queixa_id' => null,
            'descricao_livre' => $descricao,
        ]);
    }
}

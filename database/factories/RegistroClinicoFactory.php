<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoRegistroClinico;
use App\Models\Atendimento;
use App\Models\Profissional;
use App\Models\RegistroClinico;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegistroClinico>
 */
class RegistroClinicoFactory extends Factory
{
    protected $model = RegistroClinico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conteudo = fake()->paragraph();

        return [
            'uuid' => (string) Str::uuid(),
            'atendimento_id' => Atendimento::factory(),
            'tipo' => TipoRegistroClinico::EvolucaoMedica,
            // doc 9.2: SOAP em quatro colunas.
            'subjetivo' => fake()->sentence(),
            'objetivo' => fake()->sentence(),
            'avaliacao' => fake()->sentence(),
            'plano' => $conteudo,
            'sigiloso' => false,
            'autor_id' => Profissional::factory()->medico(),
            // Snapshots: o registro não muda se o cadastro do autor mudar.
            'autor_nome' => fake()->name(),
            'autor_conselho' => 'CRM/SP '.fake()->numerify('######'),
            // O encadeamento real é do HashEncadeadoService (Fase 8).
            'hash_conteudo' => hash('sha256', $conteudo),
            'hash_anterior' => null,
            'criado_em' => now(),
        ];
    }

    /**
     * RN-16: adendo exige o registro retificado E o motivo -- o CHECK
     * ck_registro_adendo recusa qualquer uma das duas faltando.
     */
    public function adendoDe(RegistroClinico $original, string $motivo = 'Correção de dose registrada.'): static
    {
        return $this->state(fn (array $attributes) => [
            'atendimento_id' => $original->atendimento_id,
            'tipo' => TipoRegistroClinico::Adendo,
            'registro_retificado_id' => $original->id,
            'motivo_retificacao' => $motivo,
        ]);
    }

    /** RF-77: registro sigiloso é omitido no portal do paciente, sem indicar que existe. */
    public function sigiloso(): static
    {
        return $this->state(fn (array $attributes) => ['sigiloso' => true]);
    }
}

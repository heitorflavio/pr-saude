<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoRegistroClinico;
use App\Models\Atendimento;
use App\Models\Profissional;
use App\Models\RegistroClinico;
use App\Services\Prontuario\HashEncadeadoService;
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
        return [
            'uuid' => (string) Str::uuid(),
            'atendimento_id' => Atendimento::factory(),
            'tipo' => TipoRegistroClinico::EvolucaoMedica,
            // doc 9.2: SOAP em quatro colunas.
            'subjetivo' => fake()->sentence(),
            'objetivo' => fake()->sentence(),
            'avaliacao' => fake()->sentence(),
            'plano' => fake()->paragraph(),
            'sigiloso' => false,
            'autor_id' => Profissional::factory()->medico(),
            // Snapshots: o registro não muda se o cadastro do autor mudar.
            'autor_nome' => fake()->name(),
            'autor_conselho' => 'CRM/SP '.fake()->numerify('######'),
            // O par de hashes é calculado em `configure()`, depois que as factories
            // aninhadas viram id de verdade -- aqui eles ainda seriam de um registro que
            // não existe.
            'criado_em' => now(),
        ];
    }

    /**
     * Fecha a cadeia de hash de verdade (doc §9.4).
     *
     * Um valor de fachada aqui faria `verificarCadeia()` acusar `CONTEUDO_ALTERADO` em
     * todo registro de teste -- e um detector que sempre alarma é um detector desligado.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (RegistroClinico $registro) {
            $hashes = app(HashEncadeadoService::class);

            $registro->hash_anterior ??= $hashes->ultimoHashDoAtendimento((int) $registro->atendimento_id);
            $registro->hash_conteudo = $hashes->calcularDoRegistro($registro);
        });
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

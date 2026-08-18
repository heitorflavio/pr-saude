<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profissional;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profissional>
 */
class ProfissionalFactory extends Factory
{
    protected $model = Profissional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->name();
        $matricula = fake()->unique()->numerify('MAT#####');

        return [
            'user_id' => User::factory()->profissional()->state(['name' => $nome, 'login' => $matricula]),
            'unidade_id' => Unidade::factory(),
            'nome_completo' => $nome,
            'matricula' => $matricula,
            // RN-18: médico e enfermeiro exigem conselho, garantido pelo CHECK
            // ck_profissional_conselho. O default aqui já nasce válido.
            'categoria' => 'MEDICO',
            'conselho_tipo' => 'CRM',
            'conselho_numero' => fake()->unique()->numerify('######'),
            'conselho_uf' => 'SP',
            'especialidade' => 'Clínica médica',
            'capacidade_fila' => 20,
            'ativo' => true,
        ];
    }

    public function medico(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'MEDICO',
            'conselho_tipo' => 'CRM',
            'conselho_numero' => fake()->unique()->numerify('######'),
            'conselho_uf' => 'SP',
        ]);
    }

    public function enfermeiro(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'ENFERMEIRO',
            'conselho_tipo' => 'COREN',
            'conselho_numero' => fake()->unique()->numerify('######'),
            'conselho_uf' => 'SP',
            'especialidade' => 'Urgência e emergência',
        ]);
    }

    public function tecnicoEnfermagem(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => 'TECNICO_ENFERMAGEM',
            'conselho_tipo' => 'COREN',
            'conselho_numero' => fake()->unique()->numerify('######'),
            'conselho_uf' => 'SP',
            'especialidade' => null,
        ]);
    }

    /** Categorias sem conselho profissional: o CHECK não as exige. */
    public function recepcao(): static
    {
        return $this->semConselho('RECEPCAO');
    }

    public function laboratorio(): static
    {
        return $this->semConselho('LABORATORIO');
    }

    public function admin(): static
    {
        return $this->semConselho('ADMIN');
    }

    private function semConselho(string $categoria): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => $categoria,
            'conselho_tipo' => null,
            'conselho_numero' => null,
            'conselho_uf' => null,
            'especialidade' => null,
        ]);
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Atendimento>
 */
class AtendimentoFactory extends Factory
{
    protected $model = Atendimento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            // RF-21: sequencial por ano e unidade. A geração segura sob concorrência
            // é responsabilidade da AbrirAtendimentoAction (Fase 5).
            'numero' => now()->year.'-'.fake()->unique()->numerify('######'),
            'paciente_id' => Paciente::factory(),
            'unidade_id' => Unidade::factory(),
            'status' => StatusAtendimento::AguardandoTriagem,
            'origem' => 'ESPONTANEA',
            'admitido_em' => now(),
            'aberto_por' => User::factory(),
        ];
    }

    public function comStatus(StatusAtendimento $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function emAtendimento(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusAtendimento::EmAtendimento,
            'primeiro_atendimento_em' => now(),
        ]);
    }

    /**
     * RN-14: FINALIZADO exige desfecho E finalizado_em -- os CHECK ck_atend_desfecho e
     * ck_atend_finalizado recusam o contrário. O estado terminal também libera a
     * `ativo_key`, permitindo um novo atendimento do mesmo paciente (RN-07).
     */
    public function finalizado(string $desfecho = 'ALTA'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusAtendimento::Finalizado,
            'desfecho' => $desfecho,
            'finalizado_em' => now(),
            'primeiro_atendimento_em' => now()->subMinutes(40),
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusAtendimento::Cancelado,
            'finalizado_em' => now(),
        ]);
    }
}

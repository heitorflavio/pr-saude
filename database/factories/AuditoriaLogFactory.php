<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditoriaLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditoriaLog>
 */
class AuditoriaLogFactory extends Factory
{
    protected $model = AuditoriaLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Snapshot das roles no instante do evento.
            'perfis_no_momento' => 'medico',
            // doc 14.3: leitura é auditada tanto quanto escrita.
            'acao' => fake()->randomElement(['prontuario.ler', 'paciente.ler', 'prescricao.criar']),
            'entidade' => 'RegistroClinico',
            'entidade_id' => fake()->numberBetween(1, 1000),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'criado_em' => now(),
        ];
    }

    /** RN-28: acesso sem vínculo assistencial exige justificativa (break the glass). */
    public function quebraDeSigilo(string $justificativa = 'Atendimento de urgência sem vínculo prévio.'): static
    {
        return $this->state(fn (array $attributes) => [
            'acao' => 'prontuario.quebra_sigilo',
            'justificativa' => $justificativa,
        ]);
    }
}

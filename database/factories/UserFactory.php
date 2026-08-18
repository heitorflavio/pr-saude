<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'login' => fake()->unique()->numerify('###########'),
            'tipo' => 'PROFISSIONAL',
            'senha_provisoria' => false,
            'ativo' => true,
            'tentativas_falhas' => 0,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function paciente(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'PACIENTE']);
    }

    public function profissional(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'PROFISSIONAL']);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'ADMIN']);
    }

    /** RN-06: força a troca de senha no primeiro acesso. */
    public function comSenhaProvisoria(): static
    {
        return $this->state(fn (array $attributes) => [
            'senha_provisoria' => true,
            'senha_alterada_em' => null,
        ]);
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }

    /** RNF-08: conta bloqueada por tentativas falhas sucessivas. */
    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => [
            'tentativas_falhas' => 5,
            'bloqueado_ate' => now()->addMinutes(15),
        ]);
    }
}

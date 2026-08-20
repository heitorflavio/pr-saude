<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\GeradorTokenPulseira;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\PacienteAlergia;
use App\Models\Unidade;
use App\Models\User;
use Database\Factories\Support\GeradorCpf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->name();
        $cpf = GeradorCpf::valido();

        return [
            // O login do paciente é o próprio CPF (RN-04).
            'user_id' => User::factory()->paciente()->state(['name' => $nome, 'login' => $cpf]),
            'uuid' => (string) Str::uuid(),
            // Token real do TokenPulseiraService: 22 base62 + 4 de HMAC (doc 8.2.1).
            // Str::random() aqui produziria um token sem checksum valido, e a rota
            // /p/{token} devolveria 404 para todo paciente de teste.
            'token_pulseira' => app(GeradorTokenPulseira::class)->gerar(),
            'nome_completo' => $nome,
            'cpf' => $cpf,
            'data_nascimento' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'sexo' => fake()->randomElement(['FEMININO', 'MASCULINO', 'OUTRO', 'NAO_INFORMADO']),
            'nome_mae' => fake()->name('female'),
            'telefone' => fake()->numerify('###########'),
            'municipio' => fake()->city(),
            'uf' => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'RS', 'BA']),
            'identificacao_provisoria' => false,
        ];
    }

    /** RF-04: paciente de urgência sem documento. O login passa a ser o código. */
    public function naoIdentificado(): static
    {
        return $this->state(function (array $attributes) {
            $codigo = 'NI-'.now()->year.'-'.fake()->unique()->numerify('####');

            return [
                'nome_completo' => 'Não identificado '.fake()->unique()->numerify('###'),
                'cpf' => null,
                'identificacao_provisoria' => true,
                'codigo_provisorio' => $codigo,
                'user_id' => User::factory()->paciente()->state(['login' => $codigo]),
            ];
        });
    }

    /** Recém-nascido: exercita a granularidade adaptativa da idade (D-01). */
    public function recemNascido(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_nascimento' => now()->subDays(3)->format('Y-m-d'),
        ]);
    }

    /** RN-06: credencial ainda não trocada pelo paciente. */
    public function comSenhaProvisoria(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->paciente()->comSenhaProvisoria(),
        ]);
    }

    /**
     * RF-11 / RN-21: alergia registrada, para exercitar o bloqueio da administração.
     * A alergia é vinculada por substância; a verificação real é por princípio ativo.
     */
    public function comAlergia(string $substancia = 'Dipirona sódica', string $gravidade = 'GRAVE'): static
    {
        return $this->afterCreating(function (Paciente $paciente) use ($substancia, $gravidade) {
            PacienteAlergia::factory()->create([
                'paciente_id' => $paciente->user_id,
                'substancia' => $substancia,
                'gravidade' => $gravidade,
            ]);
        });
    }

    /** RN-07: exatamente um atendimento não finalizado. */
    public function comAtendimentoAtivo(?Unidade $unidade = null): static
    {
        return $this->afterCreating(function (Paciente $paciente) use ($unidade) {
            Atendimento::factory()->create([
                'paciente_id' => $paciente->user_id,
                'unidade_id' => $unidade?->id ?? Unidade::factory(),
            ]);
        });
    }
}

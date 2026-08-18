<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExameAnexo;
use App\Models\ExameResultado;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExameAnexo>
 */
class ExameAnexoFactory extends Factory
{
    protected $model = ExameAnexo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->slug(3).'.pdf';

        return [
            'exame_resultado_id' => ExameResultado::factory(),
            'nome_original' => $nome,
            // Fora do document root: anexo não é servido por URL direta.
            'caminho' => 'exames/'.now()->format('Y/m').'/'.$nome,
            'mime' => 'application/pdf',
            'tamanho_bytes' => fake()->numberBetween(10_000, 5_000_000),
            'hash_sha256' => hash('sha256', $nome),
            'enviado_por' => Profissional::factory()->laboratorio(),
            'criado_em' => now(),
        ];
    }
}

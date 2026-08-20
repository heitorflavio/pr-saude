<?php

declare(strict_types=1);

use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Unidade;
use App\Models\User;
use Database\Seeders\DemonstracaoSeeder;

it('o seeder de demonstração entrega o ambiente navegável completo e é idempotente', function () {
    $this->seed(DemonstracaoSeeder::class);

    expect(Unidade::query()->count())->toBe(1)
        ->and(Profissional::query()->count())->toBe(8)
        ->and(Paciente::query()->count())->toBe(30)
        ->and(Atendimento::query()->count())->toBe(15)
        ->and(FilaItem::query()->count())->toBeGreaterThan(0)
        ->and(Atendimento::query()->distinct()->pluck('status'))->toHaveCount(8);

    foreach (['recepcao', 'enfermeiro_triagem', 'enfermeiro_assistencial', 'tecnico_enfermagem', 'medico', 'laboratorio', 'admin', 'auditor'] as $role) {
        expect(User::role($role)->whereHas('profissional')->exists())->toBeTrue();
    }

    $this->seed(DemonstracaoSeeder::class);

    expect(Profissional::query()->count())->toBe(8)
        ->and(Paciente::query()->count())->toBe(30)
        ->and(Atendimento::query()->count())->toBe(15);
});

it('a interface fornece salto de conteúdo, foco visível e prioridade redundante', function () {
    $shell = file_get_contents(resource_path('js/components/AppShell.vue'));
    $css = file_get_contents(resource_path('css/app.css'));
    $badge = file_get_contents(resource_path('js/components/sgh/BadgePrioridade.vue'));
    $triagem = file_get_contents(resource_path('js/pages/Triagem/Edit.vue'));

    expect($shell)->toContain('Pular para o conteúdo principal')
        ->and($css)->toContain(':focus-visible', 'prefers-reduced-motion')
        ->and($badge)->toContain('OctagonAlert', 'TriangleAlert', '{{ texto }}')
        ->and($triagem)->toContain('classificacao.nome', 'classificacao.cor', 'tempo_alvo_minutos');
});

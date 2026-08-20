<?php

declare(strict_types=1);

use App\Models\Atendimento;
use App\Models\FilaItem;
use App\Models\Paciente;
use App\Models\Unidade;
use Illuminate\Support\Facades\Route;

/**
 * A raiz é a única tela que qualquer pessoa alcança sem sessão. O que se testa aqui,
 * portanto, é o que ela **não** faz: não pede login e não carrega dado.
 */
it('a raiz é pública e renderiza a landing', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('a landing não carrega nenhum dado de paciente', function () {
    $paciente = Paciente::factory()->create(['nome_completo' => 'Joaquina Sigilosa']);
    $atendimento = Atendimento::factory()->create([
        'paciente_id' => $paciente->user_id,
        'unidade_id' => Unidade::factory()->create()->id,
        'classificacao_risco_id' => 2,
    ]);
    FilaItem::create([
        'atendimento_id' => $atendimento->id,
        'classificacao_risco_id' => 2,
        'situacao' => 'AGUARDANDO',
        'entrou_em' => now(),
        'criado_por' => $atendimento->aberto_por,
    ]);

    $resposta = $this->get('/')->assertOk();

    // Nem nome, nem número de atendimento, nem token de pulseira: a página é texto fixo,
    // e a única forma de garantir isso é não haver prop de dado para vazar.
    expect($resposta->content())
        ->not->toContain('Joaquina Sigilosa')
        ->not->toContain($atendimento->numero)
        ->not->toContain($paciente->token_pulseira);
});

it('os dois caminhos de entrada da landing apontam para rotas existentes', function () {
    $fonte = (string) file_get_contents(base_path('resources/js/pages/Welcome.vue'));

    preg_match_all("/route\('([^']+)'/", $fonte, $encontrados);
    $nomes = array_unique($encontrados[1]);

    // Equipe e paciente entram por guards diferentes, e a landing é o lugar onde essa
    // bifurcação fica explícita para quem chega sem saber qual porta é a sua.
    expect($nomes)->toContain('login')->toContain('portal.login');

    /*
     * O `route()` do Ziggy lança exceção no navegador quando o nome não existe -- uma
     * renomeação de rota derrubaria a página inteira, e a landing é justamente a tela
     * onde ninguém está logado para reportar o erro.
     */
    $inexistentes = array_values(array_filter($nomes, fn (string $nome) => Route::has($nome) === false));

    expect($inexistentes)->toBe([], 'Rotas citadas na landing que não existem: '.implode(', ', $inexistentes));
});

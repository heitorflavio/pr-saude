<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * A navegação lateral é a única parte da interface que não aparece em nenhum teste de
 * feature: as telas são visitadas direto pela rota. Foi por isso que um menu inteiro de
 * âncoras sem destino passou despercebido — `NavMain` redeclarava `NavItem` com o campo
 * chamado `url` enquanto `AppSidebar` montava os itens com `href`, e o TypeScript não
 * acusa nada porque cada lado está internamente consistente.
 */
function fonteDoComponente(string $caminho): string
{
    $arquivo = base_path($caminho);

    expect(file_exists($arquivo))->toBeTrue("Componente não encontrado: {$caminho}");

    return (string) file_get_contents($arquivo);
}

it('todo item da barra lateral aponta para uma rota GET que existe', function () {
    preg_match_all(
        "/href:\s*'([^']+)'/",
        fonteDoComponente('resources/js/components/AppSidebar.vue'),
        $encontrados,
    );

    $hrefs = $encontrados[1];

    expect($hrefs)->not->toBeEmpty('A barra lateral ficou sem nenhum item.');

    $uris = collect(Route::getRoutes())
        ->filter(fn ($rota) => in_array('GET', $rota->methods(), strict: true))
        ->map(fn ($rota) => '/'.ltrim($rota->uri(), '/'))
        ->all();

    // Um link de menu que devolve 404 é pior que a ausência dele: ensina o usuário a
    // desconfiar do menu inteiro, inclusive dos itens que funcionam.
    $semRota = array_values(array_diff($hrefs, $uris));

    expect($semRota)->toBe([], 'Itens da barra lateral sem rota: '.implode(', ', $semRota));
});

it('NavMain usa o contrato compartilhado de NavItem, sem redeclarar o campo', function () {
    $fonte = fonteDoComponente('resources/js/components/NavMain.vue');

    expect($fonte)->toContain(':href="item.href"')
        ->and($fonte)->toContain("from '@/types'")
        // A redeclaração local é o que permitiu os dois nomes divergirem em silêncio.
        ->and($fonte)->not->toContain('interface NavItem');
});

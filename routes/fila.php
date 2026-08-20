<?php

use App\Http\Controllers\FilaController;
use Illuminate\Support\Facades\Route;

/*
| Fila e painel do profissional (doc §7).
|
| A leitura vem das views `vw_fila_ordenada` e `vw_carga_profissional`: a ordenação da
| RN-10, a posição por ROW_NUMBER() e a carga ponderada já estão resolvidas lá.
*/
Route::middleware('auth')->group(function () {
    Route::get('fila', [FilaController::class, 'index'])->name('fila.index');

    // UC-05
    Route::get('atendimentos/{atendimento}/atribuir', [FilaController::class, 'atribuir'])
        ->name('fila.atribuir');

    Route::post('atendimentos/{atendimento}/atribuir', [FilaController::class, 'store'])
        ->name('fila.store');

    Route::post('atendimentos/{atendimento}/transferir', [FilaController::class, 'transferir'])
        ->name('fila.transferir');

    // Chamar o paciente. Passa pela AlterarStatusAction: e transicao de status, nao um
    // carimbo no fila_item -- RN-13 e RN-15 valem aqui como em qualquer outro caminho.
    Route::post('atendimentos/{atendimento}/chamar', [FilaController::class, 'chamar'])
        ->name('fila.chamar');
});

<?php

use App\Http\Controllers\TriagemController;
use Illuminate\Support\Facades\Route;

/*
| Triagem e reclassificação de risco (doc §7.5).
*/
Route::middleware('auth')->group(function () {
    Route::get('atendimentos/{atendimento}/triagem', [TriagemController::class, 'edit'])
        ->middleware('auditar:triagem.ler')
        ->name('triagem.edit');

    Route::post('atendimentos/{atendimento}/triagem', [TriagemController::class, 'store'])
        ->name('triagem.store');

    // Rota própria: reclassificar não é "editar a triagem". Cria um registro novo,
    // encadeado, e a anterior permanece intacta.
    Route::post('atendimentos/{atendimento}/reclassificar', [TriagemController::class, 'reclassificar'])
        ->name('triagem.reclassificar');
});

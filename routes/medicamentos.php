<?php

use App\Http\Controllers\MedicamentoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('medicamentos', [MedicamentoController::class, 'index'])->name('medicamentos.index');
    Route::get('atendimentos/{atendimento}/medicamentos', [MedicamentoController::class, 'show'])
        ->middleware(['vinculo', 'auditar:medicamento.ler'])->name('medicamentos.show');
    Route::post('atendimentos/{atendimento}/prescricoes', [MedicamentoController::class, 'store'])
        ->middleware('vinculo')->name('prescricoes.store');
    Route::post('prescricoes/{prescricao}/suspender', [MedicamentoController::class, 'suspender'])
        ->middleware('vinculo')->name('prescricoes.suspender');
    Route::get('aprazamentos/{aprazamento}/administrar', [MedicamentoController::class, 'conferir'])
        ->middleware('vinculo')->name('medicamentos.conferir');
    Route::post('aprazamentos/{aprazamento}/administrar', [MedicamentoController::class, 'administrar'])
        ->middleware('vinculo')->name('medicamentos.administrar');
});

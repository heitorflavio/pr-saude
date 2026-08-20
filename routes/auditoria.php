<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\QuebraSigiloController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('indicadores', [IndicadoresController::class, 'index'])->name('indicadores.index');

    // A justificativa só autoriza a releitura do destino guardado pelo middleware.
    Route::post('quebra-sigilo', [QuebraSigiloController::class, 'store'])->name('quebra-sigilo.store');
});

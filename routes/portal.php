<?php

use App\Http\Controllers\Portal\AcompanhamentoController;
use App\Http\Controllers\Portal\PortalLoginController;
use App\Http\Controllers\Portal\SenhaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:paciente')->group(function () {
    Route::get('portal/entrar', [PortalLoginController::class, 'form'])->name('portal.login');
    Route::post('portal/entrar', [PortalLoginController::class, 'autenticar'])->name('portal.autenticar');
});

Route::middleware('auth:paciente')->group(function () {
    Route::get('portal/senha', [SenhaController::class, 'form'])->name('portal.senha');
    Route::post('portal/senha', [SenhaController::class, 'atualizar'])->name('portal.senha.atualizar');

    Route::middleware(['senha.definitiva', 'portal.vigente'])->group(function () {
        // RN-27: todas as rotas de dado são GET; a ausência de escrita é o controle.
        Route::get('portal', [AcompanhamentoController::class, 'index'])->name('portal.acompanhamento');
        Route::get('portal/atendimento/{uuid}', [AcompanhamentoController::class, 'atendimento'])->name('portal.atendimento');
        Route::get('portal/medicamentos', [AcompanhamentoController::class, 'medicamentos'])->name('portal.medicamentos');
        Route::get('portal/exames', [AcompanhamentoController::class, 'exames'])->name('portal.exames');
        Route::get('portal/historico', [AcompanhamentoController::class, 'historico'])->name('portal.historico');
    });

    Route::post('portal/sair', [PortalLoginController::class, 'sair'])->name('portal.sair');
});

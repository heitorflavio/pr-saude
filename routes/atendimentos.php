<?php

use App\Http\Controllers\AtendimentoController;
use Illuminate\Support\Facades\Route;

/*
| Ciclo de vida do atendimento (doc §6).
|
| Todas as escritas passam pelas Actions: nenhuma rota aqui grava `atendimento.status`
| direto. É o que faz RN-13, RN-14 e RN-15 valerem em todos os caminhos, e não só nos
| que alguém lembrou de cobrir.
*/
Route::middleware('auth')->group(function () {
    // RF-18: atendimentos do paciente, em andamento e finalizados.
    Route::get('pacientes/{paciente}/atendimentos', [AtendimentoController::class, 'index'])
        ->middleware('auditar:atendimento.ler_status')
        ->name('atendimentos.index');

    Route::post('pacientes/{paciente}/atendimentos', [AtendimentoController::class, 'store'])
        ->name('atendimentos.store');

    // RF-22: linha do tempo consolidada. Exibe dado clínico -- auditada.
    Route::get('atendimentos/{atendimento}', [AtendimentoController::class, 'show'])
        ->middleware('auditar:atendimento.ler_status')
        ->name('atendimentos.show');

    Route::put('atendimentos/{atendimento}/status', [AtendimentoController::class, 'alterarStatus'])
        ->name('atendimentos.status');

    // RN-14: finalizar tem rota própria porque exige desfecho -- e um `status=FINALIZADO`
    // solto na rota genérica seria fácil de mandar sem ele.
    Route::post('atendimentos/{atendimento}/finalizar', [AtendimentoController::class, 'finalizar'])
        ->name('atendimentos.finalizar');
});

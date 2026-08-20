<?php

use App\Http\Controllers\ProntuarioController;
use Illuminate\Support\Facades\Route;

/*
| Prontuário e evolução — UC-08, RF-45 a RF-52 (doc §9).
|
| Não existe rota PUT nem DELETE para `registro_clinico`, e a ausência é a garantia
| (RN-16, RN-17). O que existe é `POST .../retificar`, que cria um adendo novo apontando
| para o original. Uma rota de edição, mesmo negada em controller, seria uma superfície
| a mais para alguém abrir por engano em uma refatoração futura.
*/
Route::middleware(['auth', 'vinculo'])->group(function () {
    // RF-47: linha do tempo do atendimento.
    Route::get('atendimentos/{atendimento}/prontuario', [ProntuarioController::class, 'show'])
        ->middleware('auditar:prontuario.ler')
        ->name('prontuario.show');

    // RF-51: consolidado, atravessando todos os atendimentos do paciente.
    Route::get('pacientes/{paciente}/prontuario', [ProntuarioController::class, 'consolidado'])
        ->name('prontuario.consolidado');

    // RF-52: exportação em PDF.
    Route::get('atendimentos/{atendimento}/prontuario/pdf', [ProntuarioController::class, 'exportar'])
        ->name('prontuario.pdf');

    // RF-45, RF-47, RF-48.
    Route::post('atendimentos/{atendimento}/prontuario', [ProntuarioController::class, 'store'])
        ->name('prontuario.store');

    // RF-50: retificação por adendo. Nunca UPDATE.
    Route::post('registros/{registro}/retificar', [ProntuarioController::class, 'retificar'])
        ->name('prontuario.retificar');

    // RF-46.
    Route::post('atendimentos/{atendimento}/diagnosticos', [ProntuarioController::class, 'diagnosticar'])
        ->name('diagnosticos.store');

    Route::get('cid10', [ProntuarioController::class, 'cid10'])->name('cid10.buscar');
});

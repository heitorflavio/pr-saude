<?php

use App\Http\Controllers\ExameController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('exames', [ExameController::class, 'index'])->name('exames.index');
    Route::get('atendimentos/{atendimento}/exames/solicitar', [ExameController::class, 'create'])
        ->middleware('vinculo')->name('exames.create');
    Route::get('exames/solicitacoes/{solicitacao}', [ExameController::class, 'show'])
        ->middleware(['vinculo', 'auditar:exame.ler'])->name('exames.show');
    Route::post('atendimentos/{atendimento}/exames', [ExameController::class, 'solicitar'])
        ->middleware('vinculo')->name('exames.solicitar');
    Route::post('exames/solicitacoes/{solicitacao}/situacao', [ExameController::class, 'alterar'])
        ->middleware('vinculo')->name('exames.situacao');
    Route::post('exames/solicitacoes/{solicitacao}/resultado', [ExameController::class, 'resultado'])
        ->middleware('vinculo')->name('exames.resultado');
    Route::post('exames/resultados/{resultado}/liberar', [ExameController::class, 'liberar'])
        ->middleware('vinculo')->name('exames.liberar');
    Route::get('exames/anexos/{anexo}', [ExameController::class, 'anexo'])
        ->middleware(['vinculo', 'auditar:exame.anexo'])->name('exames.anexo');
});

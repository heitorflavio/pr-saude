<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Painel inicial da equipe: leitura agregada do plantao, montada por permissao.
Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/pacientes.php';
require __DIR__.'/atendimentos.php';
require __DIR__.'/triagem.php';
require __DIR__.'/fila.php';
require __DIR__.'/prontuario.php';
require __DIR__.'/medicamentos.php';
require __DIR__.'/exames.php';
require __DIR__.'/pulseira.php';
require __DIR__.'/portal.php';
require __DIR__.'/auditoria.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

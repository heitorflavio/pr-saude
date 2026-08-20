<?php

use App\Http\Controllers\PulseiraController;
use Illuminate\Support\Facades\Route;

/*
| Pulseira: impressão (equipe) e resolução do QR Code (público autenticado).
*/

Route::middleware('auth')->group(function () {
    // RF-15 / RF-16. A reimpressão usa o mesmo token (RN-03).
    Route::post('pacientes/{paciente}/pulseira', [PulseiraController::class, 'imprimir'])
        ->name('pulseira.imprimir');
});

/*
| `GET /p/{token}` — a rota que o QR Code carrega.
|
| Deliberadamente **fora** de qualquer middleware de autenticação: o controller precisa
| responder a quem não tem sessão, e responder de forma indistinguível para token
| existente e inexistente. Um `auth` aqui redirecionaria para o login da equipe, o que
| revelaria menos nada — mas também impediria o paciente de chegar ao portal dele.
|
| Rate limit por IP: a rota é o alvo natural de varredura automatizada, e o checksum
| já descarta a maioria antes do banco (doc §8.2.1).
*/
Route::get('p/{token}', [PulseiraController::class, 'resolver'])
    ->middleware('throttle:30,1')
    ->name('pulseira.resolver');

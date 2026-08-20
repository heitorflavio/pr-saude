<?php

use App\Http\Controllers\PacienteController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do cadastro de paciente (UC-01).
|
| Todas sob o guard `web` (equipe). O portal do paciente é outro guard e outro arquivo
| de rotas (Fase 11) -- lá, por RN-27, não existe nenhuma rota de escrita além de senha
| e logout.
|
| `verified` fica de fora de propósito: `users.email` é nullable (RF-04) e a recepção
| precisa cadastrar paciente de urgência sem e-mail.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('pacientes', [PacienteController::class, 'index'])
        ->name('pacientes.index');

    Route::get('pacientes/novo', [PacienteController::class, 'create'])
        ->name('pacientes.create');

    Route::post('pacientes', [PacienteController::class, 'store'])
        ->name('pacientes.store');

    /*
     | Ficha CADASTRAL. Sem o middleware `vinculo`, de propósito.
     |
     | RN-28 exige vínculo assistencial para o PRONTUÁRIO, não para o cadastro. A
     | recepcionista precisa desta tela imediatamente após cadastrar, para imprimir a
     | pulseira e abrir o atendimento (UC-01, passo 11) -- e nesse instante ela não tem
     | vínculo assistencial nenhum, porque o atendimento ainda não existe.
     |
     | O acesso é amplo e integralmente auditado. O middleware `vinculo` entra nas rotas
     | de prontuário, na Fase 8.
     */
    Route::get('pacientes/{paciente}', [PacienteController::class, 'show'])
        ->middleware('auditar:paciente.ler')
        ->name('pacientes.show');

    Route::put('pacientes/{paciente}/identificacao', [PacienteController::class, 'regularizar'])
        ->name('pacientes.regularizar');
});

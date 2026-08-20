<?php

use App\Jobs\VerificarIntegridadeProntuarioJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| doc §9.4 -- verificação periódica da cadeia de hash do prontuário.
|
| De hora em hora, sobre a janela recente. A alternativa -- verificar sob demanda -- só
| encontra a adulteração quando alguém já desconfia; e a essa altura o registro alterado
| já sustentou decisões clínicas.
|
| `withoutOverlapping` porque a varredura pode passar da hora em base grande, e duas
| execuções simultâneas duplicariam o alarme sem acrescentar informação.
*/
Schedule::job(new VerificarIntegridadeProntuarioJob)
    ->hourly()
    ->withoutOverlapping()
    ->name('prontuario:verificar-integridade');

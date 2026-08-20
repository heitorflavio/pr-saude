<?php

namespace App\Providers;

use App\Contracts\GeradorTokenPulseira;
use App\Models\AdministracaoMedicamento;
use App\Models\Aprazamento;
use App\Models\Atendimento;
use App\Models\Diagnostico;
use App\Models\ExameAnexo;
use App\Models\ExameResultado;
use App\Models\ExameResultadoItem;
use App\Models\ExameSolicitacao;
use App\Models\FilaItem;
use App\Models\Prescricao;
use App\Models\PrescricaoItem;
use App\Models\RegistroClinico;
use App\Models\Scopes\DoPacienteAutenticadoScope;
use App\Models\SinalVital;
use App\Models\Triagem;
use App\Services\Pulseira\TokenPulseiraService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A implementacao e final de proposito: ninguem deve poder sobrescrever a
        // geracao de um identificador de seguranca por heranca. O contrato existe para
        // que E1 do UC-01 (rollback na falha do token) seja testavel mesmo assim.
        $this->app->singleton(GeradorTokenPulseira::class, TokenPulseiraService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RN-26: cada model clínico se fecha sobre o titular quando o guard do portal
        // está ativo. Controllers continuam filtrando explicitamente; isto é a rede de
        // segurança para um `where` esquecido.
        foreach ([
            Atendimento::class, RegistroClinico::class, Diagnostico::class,
            Prescricao::class, PrescricaoItem::class, Aprazamento::class,
            AdministracaoMedicamento::class, ExameSolicitacao::class,
            ExameResultado::class, ExameResultadoItem::class, ExameAnexo::class,
            Triagem::class, SinalVital::class, FilaItem::class,
        ] as $model) {
            $model::addGlobalScope(new DoPacienteAutenticadoScope);
        }
    }
}

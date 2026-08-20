<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Laravel 11+ não traz mais este trait por padrão. Ele é necessário aqui porque a
     * autorização de dado clínico é contextual: `$this->authorize('verContexto', $paciente)`
     * consulta a Policy, não só a permission. Ver CLAUDE.md, seção 4.
     */
    use AuthorizesRequests;
}

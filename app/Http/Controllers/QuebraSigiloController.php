<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class QuebraSigiloController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $solicitacao = $request->session()->pull('quebra_sigilo.solicitacao');
        abort_unless(is_array($solicitacao) && isset($solicitacao['destino'], $solicitacao['paciente_id']), 419);

        $request->session()->put('quebra_sigilo.pendente', [
            ...$solicitacao,
            'justificativa' => trim($dados['justificativa']),
        ]);

        return redirect()->to($solicitacao['destino']);
    }
}

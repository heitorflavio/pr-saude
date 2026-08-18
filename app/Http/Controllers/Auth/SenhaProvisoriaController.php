<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RN-06: troca obrigatória da senha provisória no primeiro acesso.
 */
final class SenhaProvisoriaController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('auth/SenhaProvisoria', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $validado = $request->validate([
            'senha' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'senha' => 'nova senha',
        ]);

        // A senha provisória do paciente é a data de nascimento (RN-05) e a do
        // profissional é entregue pelo administrador. Permitir "trocar" para a mesma
        // senha tornaria a exigência decorativa.
        if (Hash::check($validado['senha'], $usuario->password)) {
            throw ValidationException::withMessages([
                'senha' => 'A nova senha precisa ser diferente da senha provisória.',
            ]);
        }

        $usuario->forceFill([
            'password' => $validado['senha'],
            'senha_provisoria' => false,
            // RN-29: hora do servidor.
            'senha_alterada_em' => now(),
        ])->save();

        // A senha nunca chega ao log: o AuditoriaService mascara o campo.
        $this->auditoria->registrar(
            acao: 'usuario.senha_alterada',
            entidade: 'User',
            entidadeId: $usuario->id,
        );

        return redirect()->intended(route('dashboard'))
            ->with('status', 'Senha atualizada.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SenhaController extends Controller
{
    private const COMUNS = [
        '12345678', '123456789', 'password', 'senha123', 'qwerty123',
        '11111111', '00000000', 'admin123', 'brasil123',
    ];

    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function form(): Response
    {
        return Inertia::render('Portal/Senha');
    }

    public function atualizar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed', 'max:255'],
        ]);
        $usuario = $request->user('paciente');
        $paciente = $usuario->paciente;
        $senha = (string) $dados['password'];

        $fracas = array_filter([
            preg_replace('/\D/', '', (string) $paciente->cpf),
            $paciente->data_nascimento?->format('dmY'),
            $paciente->data_nascimento?->format('d/m/Y'),
        ]);

        if (in_array(mb_strtolower($senha), self::COMUNS, true)
            || in_array($senha, $fracas, true)
            || Hash::check($senha, $usuario->password)) {
            throw ValidationException::withMessages([
                'password' => 'Escolha uma senha diferente do CPF, da data de nascimento, da senha atual e de senhas comuns.',
            ]);
        }

        $usuario->update([
            'password' => $senha,
            'senha_provisoria' => false,
            'senha_alterada_em' => now(),
        ]);
        $request->session()->regenerate();

        $this->auditoria->registrar(
            acao: 'portal.senha_alterar', paciente: $paciente,
            entidade: 'User', entidadeId: $usuario->id,
            depois: ['senha_provisoria' => false], usuario: $usuario,
        );

        return redirect()->route('portal.acompanhamento')->with('status', 'Senha definida com segurança.');
    }
}

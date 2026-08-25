<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Events\AcessoPortalRealizado;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

final class PortalLoginController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function form(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function autenticar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'cpf' => ['required', 'string'],
            'senha' => ['required', 'string', 'max:255'],
        ]);
        $cpf = preg_replace('/\D/', '', (string) $dados['cpf']) ?? '';
        if (strlen($cpf) !== 11) {
            return $this->falha($request, $cpf, null);
        }

        $chaveIp = 'portal:ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($chaveIp, 30)) {
            $this->auditoria->registrar('portal.login_bloqueado', depois: ['origem' => 'ip']);

            return back()->withErrors(['cpf' => 'Muitas tentativas. Aguarde e tente novamente ou procure a recepção.']);
        }

        $usuario = User::query()->where('tipo', 'PACIENTE')->where('login', $cpf)->first();
        // M-7: CPF inexistente e senha errada sempre pagam uma verificação Argon2id.
        $hash = $usuario?->password ?? (string) config('portal.dummy_hash');
        $senhaCorreta = Hash::check((string) $dados['senha'], $hash);

        if ($usuario === null || ! $senhaCorreta || ! $usuario->ativo) {
            return $this->falha($request, $cpf, $usuario, $chaveIp);
        }

        // D-63: a M-9 (portal só durante o episódio e 30 dias após a alta) foi removida.
        // Resta exigir que a conta tenha de fato uma ficha de paciente por trás — um
        // `users.tipo = PACIENTE` órfão não tem dado nenhum para mostrar.
        $paciente = $usuario->paciente;
        if ($paciente === null) {
            $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'sem_ficha_de_paciente');

            return back()->withErrors(['cpf' => 'Credenciais inválidas.']);
        }

        // D-61: a posse da pulseira (M-3) deixou de ser exigida no primeiro acesso. A
        // janela de 72 h da senha provisória passa a ser a única barreira temporal, e a
        // troca obrigatória (RN-06) segue valendo antes de qualquer tela do portal.
        if ($usuario->senha_provisoria && ! $paciente->senhaProvisoriaVigente()) {
            $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'senha_inicial_expirada');

            return back()->withErrors(['cpf' => 'Senha inicial expirada. Solicite uma nova na recepção.']);
        }

        $usuario->update(['ultimo_login_em' => now()]);
        RateLimiter::clear($chaveIp);
        Auth::guard('paciente')->login($usuario);
        $request->session()->regenerate();

        $this->auditarTentativa('portal.login', $cpf, $usuario, 'sucesso');
        AcessoPortalRealizado::dispatch($paciente, $request->ip());

        return $usuario->senha_provisoria
            ? redirect()->route('portal.senha')
            : redirect()->route('portal.acompanhamento');
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::guard('paciente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function falha(Request $request, string $cpf, ?User $usuario, ?string $chaveIp = null): RedirectResponse
    {
        $chaveIp ??= 'portal:ip:'.hash('sha256', (string) $request->ip());
        RateLimiter::hit($chaveIp, decaySeconds: 900);

        // D-62: o bloqueio progressivo da conta (M-4, RNF-08) foi removido. A contenção de
        // força bruta passa a ser exclusivamente o limite por IP acima (M-5) -- que, ao
        // contrário do bloqueio por conta, ninguém consegue disparar contra um paciente
        // alheio só para trancá-lo fora do portal.
        $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'credenciais');

        // M-6: idêntica para CPF inexistente, senha errada e conta inativa.
        return back()->withErrors(['cpf' => 'Credenciais inválidas.']);
    }

    private function auditarTentativa(string $acao, string $cpf, ?User $usuario, string $resultado): void
    {
        $this->auditoria->registrar(
            acao: $acao,
            paciente: $usuario?->paciente,
            entidade: 'AutenticacaoPortal',
            entidadeId: $usuario?->id,
            // M-11; o serviço mascara o CPF e não replica a senha.
            depois: ['cpf' => $cpf, 'resultado' => $resultado],
            usuario: $usuario,
        );
    }
}

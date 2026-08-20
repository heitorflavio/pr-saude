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
        return Inertia::render('Portal/Login', [
            'pulseiraLida' => session()->has('portal.pulseira_token'),
        ]);
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

        if ($usuario === null || ! $senhaCorreta || ! $usuario->ativo || $usuario->estaBloqueado()) {
            return $this->falha($request, $cpf, $usuario, $chaveIp);
        }

        $paciente = $usuario->paciente;
        if ($paciente === null || ! $paciente->possuiAcessoVigente()) {
            $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'acesso_fora_da_janela');

            return back()->withErrors(['cpf' => 'Credenciais inválidas.']);
        }

        if ($usuario->senha_provisoria) {
            $tokenSessao = (string) $request->session()->get('portal.pulseira_token', '');
            // M-3: comparação constante do fator de posse.
            if ($tokenSessao === '' || ! hash_equals($paciente->token_pulseira, $tokenSessao)) {
                $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'pulseira_ausente');

                return back()->withErrors(['cpf' => 'No primeiro acesso, escaneie o QR Code da sua pulseira.']);
            }
            if (! $paciente->senhaProvisoriaVigente()) {
                $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'senha_inicial_expirada');

                return back()->withErrors(['cpf' => 'Senha inicial expirada. Solicite uma nova na recepção.']);
            }
        }

        $usuario->update([
            'tentativas_falhas' => 0, 'bloqueado_ate' => null, 'ultimo_login_em' => now(),
        ]);
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

        if ($usuario !== null) {
            $tentativas = $usuario->tentativas_falhas + 1;
            $segundos = match (true) {
                $tentativas >= 10 => 10 * 365 * 24 * 3600,
                $tentativas >= 8 => 3600,
                $tentativas >= 5 => 900,
                $tentativas >= 3 => 60,
                default => 0,
            };
            $usuario->update([
                'tentativas_falhas' => $tentativas,
                'bloqueado_ate' => $segundos > 0 ? now()->addSeconds($segundos) : null,
            ]);
        }

        $this->auditarTentativa('portal.login_falha', $cpf, $usuario, 'credenciais');

        // M-6: idêntica para CPF inexistente, senha errada, conta inativa ou bloqueada.
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

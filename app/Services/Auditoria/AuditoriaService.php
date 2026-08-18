<?php

declare(strict_types=1);

namespace App\Services\Auditoria;

use App\Models\Atendimento;
use App\Models\AuditoriaLog;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * doc §14.3.
 *
 * Auditar escrita é intuitivo. Auditar **leitura** é o que efetivamente protege dado de
 * saúde, porque o dano típico não é alteração: é bisbilhotagem. O caso clássico é o
 * funcionário que consulta o prontuário de um vizinho, de um colega ou de uma pessoa
 * pública. Sem log de leitura, isso é indetectável e, portanto, impune.
 */
final class AuditoriaService
{
    /**
     * Campos que nunca são replicados no log.
     *
     * O log não deve conter o dado sensível integralmente: isso criaria uma segunda base
     * com o mesmo risco e sem os mesmos controles -- e `auditoria_log` é justamente a
     * tabela mais consultada por quem investiga incidente.
     */
    private const CAMPOS_MASCARADOS = [
        'password', 'senha', 'senha_hash', 'remember_token',
        'token_pulseira', 'cpf', 'cns',
    ];

    private const MASCARA = '[REMOVIDO]';

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    public function registrar(
        string $acao,
        ?Paciente $paciente = null,
        ?Atendimento $atendimento = null,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?array $antes = null,
        ?array $depois = null,
        ?string $justificativa = null,
        ?User $usuario = null,
    ): AuditoriaLog {
        $usuario ??= Auth::user();

        return AuditoriaLog::create([
            'user_id' => $usuario?->id,
            // Snapshot dos perfis: as roles mudam, o log não pode mudar com elas.
            'perfis_no_momento' => $this->perfisNoMomento($usuario),
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'paciente_id' => $paciente?->user_id ?? $atendimento?->paciente_id,
            'atendimento_id' => $atendimento?->id,
            'justificativa' => $justificativa,
            'dados_antes' => $antes !== null ? $this->mascarar($antes) : null,
            'dados_depois' => $depois !== null ? $this->mascarar($depois) : null,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
            // RN-29: hora do servidor.
            'criado_em' => now(),
        ]);
    }

    /** Atalho para o caso mais frequente e mais importante: leitura de dado clínico. */
    public function registrarLeitura(
        string $acao,
        ?Paciente $paciente = null,
        ?Atendimento $atendimento = null,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?string $justificativa = null,
    ): AuditoriaLog {
        return $this->registrar(
            acao: $acao,
            paciente: $paciente,
            atendimento: $atendimento,
            entidade: $entidade,
            entidadeId: $entidadeId,
            justificativa: $justificativa,
        );
    }

    private function perfisNoMomento(?User $usuario): ?string
    {
        if ($usuario === null) {
            return null;
        }

        $roles = $usuario->getRoleNames();

        return $roles->isEmpty() ? null : $roles->implode(',');
    }

    /**
     * Mascara em profundidade: `dados_antes` e `dados_depois` costumam vir de
     * `$model->getAttributes()`, mas também de payloads aninhados de formulário.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function mascarar(array $dados): array
    {
        foreach ($dados as $campo => $valor) {
            if (is_string($campo) && in_array(mb_strtolower($campo), self::CAMPOS_MASCARADOS, strict: true)) {
                $dados[$campo] = self::MASCARA;

                continue;
            }

            if (is_array($valor)) {
                $dados[$campo] = $this->mascarar($valor);
            }
        }

        return $dados;
    }
}

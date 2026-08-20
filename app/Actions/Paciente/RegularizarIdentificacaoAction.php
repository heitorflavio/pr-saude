<?php

declare(strict_types=1);

namespace App\Actions\Paciente;

use App\Events\IdentificacaoRegularizada;
use App\Exceptions\PacienteJaCadastradoException;
use App\Exceptions\RegularizacaoInvalidaException;
use App\Models\Paciente;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * RN-30: vincula o CPF real a um paciente cadastrado como não identificado,
 * **preservando todo o histórico**.
 *
 * O ponto da regra: o paciente que chegou inconsciente e foi cadastrado como
 * `NI-2026-0031` já tem atendimento, triagem, prontuário e talvez prescrição. Criar um
 * cadastro novo quando o documento aparece — e abandonar o provisório — produziria dois
 * prontuários da mesma pessoa, com o histórico do episódio grave no que ninguém mais
 * consulta. Por isso a regularização **atualiza o registro existente**: mesmo
 * `user_id`, mesmo `token_pulseira` (RN-03), mesmo prontuário.
 */
final class RegularizarIdentificacaoAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * @throws RegularizacaoInvalidaException Paciente não provisório, ou CPF inválido.
     * @throws PacienteJaCadastradoException Se o CPF já pertence a outro paciente.
     */
    public function execute(
        Paciente $paciente,
        string $cpf,
        ?User $autor = null,
        ?string $nomeCompleto = null,
        ?string $cns = null,
    ): Paciente {
        if (! $paciente->identificacao_provisoria) {
            throw RegularizacaoInvalidaException::pacienteJaIdentificado();
        }

        $cpf = Cpf::apenasDigitos($cpf);

        if (! Cpf::ehValido($cpf)) {
            throw RegularizacaoInvalidaException::cpfInvalido();
        }

        // O CPF pode já pertencer a um cadastro definitivo feito enquanto o paciente
        // estava inconsciente. Nesse caso a decisão é de fusão de prontuários, que é
        // ato assistencial -- não algo que esta Action deva resolver sozinha.
        $existente = Paciente::where('cpf', $cpf)
            ->where('user_id', '!=', $paciente->user_id)
            ->first();

        if ($existente !== null) {
            throw new PacienteJaCadastradoException($existente);
        }

        return DB::transaction(function () use ($paciente, $cpf, $autor, $nomeCompleto, $cns) {
            $antes = $paciente->getAttributes();
            $codigoAnterior = (string) $paciente->codigo_provisorio;

            $paciente->fill([
                'cpf' => $cpf,
                'cns' => $cns ?? $paciente->cns,
                'nome_completo' => $nomeCompleto ?? $paciente->nome_completo,
                'identificacao_provisoria' => false,
                // O código provisório é liberado: `ck_paciente_identificacao` passa a ser
                // satisfeito pelo CPF, e manter o código ocuparia o índice único à toa.
                'codigo_provisorio' => null,
            ])->save();

            // RN-04: o login acompanha a identificação. A senha NÃO é redefinida -- se o
            // paciente já trocou a provisória, a dele continua valendo.
            $paciente->user->forceFill([
                'login' => $cpf,
                'name' => $paciente->nome_completo,
            ])->save();

            $this->auditoria->registrar(
                acao: 'paciente.regularizar_identificacao',
                paciente: $paciente,
                entidade: 'Paciente',
                entidadeId: $paciente->user_id,
                antes: $antes,
                depois: $paciente->getAttributes(),
                justificativa: "Identificação provisória {$codigoAnterior} regularizada.",
                usuario: $autor,
            );

            IdentificacaoRegularizada::dispatch($paciente, $codigoAnterior);

            return $paciente->refresh();
        });
    }
}

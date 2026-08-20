<?php

declare(strict_types=1);

namespace App\Actions\Paciente;

use App\Contracts\GeradorTokenPulseira;
use App\Events\PacienteCadastrado;
use App\Exceptions\PacienteJaCadastradoException;
use App\Exceptions\TokenPulseiraIndisponivelException;
use App\Models\Paciente;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Paciente\GeradorCodigoProvisorioService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UC-01, passo 10: cadastro do paciente em **uma única transação**.
 *
 * Persiste o paciente, cria a credencial de acesso, gera o token permanente de pulseira
 * e registra a auditoria. Ou tudo, ou nada -- E1 do UC-01 exige rollback integral se a
 * geração do token falhar, porque um paciente sem token é um paciente sem pulseira, e um
 * usuário sem paciente é uma credencial órfã que ninguém consegue explicar depois.
 */
final class CadastrarPacienteAction
{
    /** E2 do UC-01: colisão é desprezível, mas o laço não pode ser infinito. */
    private const TENTATIVAS_TOKEN = 3;

    public function __construct(
        private readonly GeradorTokenPulseira $tokens,
        private readonly GeradorCodigoProvisorioService $codigosProvisorios,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  Já validado por CadastrarPacienteRequest.
     *
     * @throws PacienteJaCadastradoException A1: CPF já cadastrado.
     * @throws TokenPulseiraIndisponivelException E2.
     */
    public function execute(array $dados, ?User $autor = null): Paciente
    {
        $cpf = isset($dados['cpf']) && $dados['cpf'] !== null && $dados['cpf'] !== ''
            ? Cpf::apenasDigitos((string) $dados['cpf'])
            : null;

        $naoIdentificado = (bool) ($dados['identificacao_provisoria'] ?? false) || $cpf === null;

        // A1: antes de qualquer escrita. Duplicar cadastro num pronto-socorro significa
        // dois prontuários para a mesma pessoa -- e o alérgico cujo registro de alergia
        // ficou no outro cadastro.
        if ($cpf !== null) {
            $existente = Paciente::where('cpf', $cpf)->first();

            if ($existente !== null) {
                throw new PacienteJaCadastradoException($existente);
            }
        }

        $nascimento = Carbon::parse((string) $dados['data_nascimento']);

        return DB::transaction(function () use ($dados, $cpf, $naoIdentificado, $nascimento, $autor) {
            $codigoProvisorio = $naoIdentificado ? $this->codigosProvisorios->gerar() : null;

            // RN-04: o login é o CPF; sem CPF, é o código provisório (A2).
            $login = $cpf ?? $codigoProvisorio;

            $usuario = User::create([
                // D-03: `users.name` é rótulo de exibição; o nome oficial é
                // `paciente.nome_completo`. Esta Action é quem mantém os dois em sincronia.
                'name' => $dados['nome_completo'],
                'email' => $dados['email'] ?? null,
                // RN-05: senha inicial é a data de nascimento em DDMMAAAA.
                'password' => $nascimento->format('dmY'),
                'login' => $login,
                'tipo' => 'PACIENTE',
                // RN-06: provisória, força a troca no primeiro acesso.
                'senha_provisoria' => true,
                'ativo' => true,
            ]);

            $paciente = Paciente::create([
                'user_id' => $usuario->id,
                'uuid' => (string) Str::uuid(),
                'token_pulseira' => $this->tokenUnico(),
                'nome_completo' => $dados['nome_completo'],
                'nome_social' => $dados['nome_social'] ?? null,
                'cpf' => $cpf,
                'cns' => $dados['cns'] ?? null,
                'data_nascimento' => $nascimento->toDateString(),
                'sexo' => $dados['sexo'] ?? 'NAO_INFORMADO',
                'nome_mae' => $dados['nome_mae'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'contato_emergencia_nome' => $dados['contato_emergencia_nome'] ?? null,
                'contato_emergencia_telefone' => $dados['contato_emergencia_telefone'] ?? null,
                'logradouro' => $dados['logradouro'] ?? null,
                'numero' => $dados['numero'] ?? null,
                'complemento' => $dados['complemento'] ?? null,
                'bairro' => $dados['bairro'] ?? null,
                'municipio' => $dados['municipio'] ?? null,
                'uf' => $dados['uf'] ?? null,
                'cep' => $dados['cep'] ?? null,
                'identificacao_provisoria' => $naoIdentificado,
                'codigo_provisorio' => $codigoProvisorio,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $this->registrarAlergias($paciente, $dados['alergias'] ?? [], $autor);
            $this->registrarCondicoes($paciente, $dados['condicoes'] ?? [], $autor);

            $this->auditoria->registrar(
                acao: 'paciente.criar',
                paciente: $paciente,
                entidade: 'Paciente',
                entidadeId: $paciente->user_id,
                // O AuditoriaService mascara cpf, cns e token_pulseira antes de gravar.
                depois: $paciente->getAttributes(),
                usuario: $autor,
            );

            PacienteCadastrado::dispatch($paciente);

            return $paciente;
        });
    }

    /**
     * E2: até três tentativas. A checagem em PHP evita o erro visível no caso comum; o
     * índice único `uk_paciente_token` é a garantia que sobrevive a corrida.
     */
    private function tokenUnico(): string
    {
        for ($tentativa = 1; $tentativa <= self::TENTATIVAS_TOKEN; $tentativa++) {
            $token = $this->tokens->gerar();

            if (! Paciente::withTrashed()->where('token_pulseira', $token)->exists()) {
                return $token;
            }
        }

        throw TokenPulseiraIndisponivelException::aposTentativas(self::TENTATIVAS_TOKEN);
    }

    /**
     * RF-11: alergias ficam em destaque em toda tela do atendimento, e fazem parte do
     * "mínimo vital" liberado a qualquer profissional em plantão (doc §13.5).
     *
     * @param  array<int, array<string, mixed>>  $alergias
     */
    private function registrarAlergias(Paciente $paciente, array $alergias, ?User $autor): void
    {
        foreach ($alergias as $alergia) {
            if (($alergia['substancia'] ?? '') === '') {
                continue;
            }

            $paciente->alergias()->create([
                'substancia' => $alergia['substancia'],
                'medicamento_id' => $alergia['medicamento_id'] ?? null,
                'gravidade' => $alergia['gravidade'] ?? 'DESCONHECIDA',
                'reacao' => $alergia['reacao'] ?? null,
                'registrado_por' => $autor?->profissional?->user_id,
            ]);
        }
    }

    /** @param  array<int, array<string, mixed>>  $condicoes */
    private function registrarCondicoes(Paciente $paciente, array $condicoes, ?User $autor): void
    {
        foreach ($condicoes as $condicao) {
            if (($condicao['descricao'] ?? '') === '') {
                continue;
            }

            $paciente->condicoes()->create([
                'descricao' => $condicao['descricao'],
                'cid10_codigo' => $condicao['cid10_codigo'] ?? null,
                'desde' => $condicao['desde'] ?? null,
                'registrado_por' => $autor?->profissional?->user_id,
            ]);
        }
    }
}

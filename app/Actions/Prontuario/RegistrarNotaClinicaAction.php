<?php

declare(strict_types=1);

namespace App\Actions\Prontuario;

use App\Enums\TipoRegistroClinico;
use App\Events\RegistroClinicoCriado;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Exceptions\RegistroClinicoInvalidoException;
use App\Models\Atendimento;
use App\Models\RegistroClinico;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Prontuario\HashEncadeadoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UC-08 — RF-45, RF-47, RF-48: nota clínica em estrutura SOAP (doc §9.2).
 *
 * **Por que quatro colunas e não um `TEXT` único.** A estrutura é um guia cognitivo: um
 * campo em branco rotulado "Avaliação" cobra o raciocínio explícito, enquanto um texto
 * livre aceita "paciente bem, alta" — que é registro sem conteúdo. Além disso permite
 * leitura seletiva (o médico da reavaliação lê primeiro o *Plano* da nota anterior) e
 * extração de dados sem processamento de linguagem natural.
 *
 * Escrever aqui é definitivo: o model recusa qualquer `save()` posterior (RN-16) e o
 * banco recusa `UPDATE` e `DELETE`. Correção só por adendo — {@see RetificarRegistroAction}.
 */
final class RegistrarNotaClinicaAction
{
    public function __construct(
        private readonly HashEncadeadoService $hashes,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  array{subjetivo?: ?string, objetivo?: ?string, avaliacao?: ?string, plano?: ?string, conteudo_livre?: ?string}  $conteudo
     *
     * @throws RegistroClinicoInvalidoException
     */
    public function execute(
        Atendimento $atendimento,
        TipoRegistroClinico $tipo,
        User $autor,
        array $conteudo,
        bool $sigiloso = false,
    ): RegistroClinico {
        // RN-16: adendo nasce de uma retificação, com original e motivo. Criá-lo como
        // nota avulsa passaria pelo CHECK só por acaso e produziria uma correção que
        // não aponta o que corrigiu.
        if ($tipo === TipoRegistroClinico::Adendo) {
            throw RegistroClinicoInvalidoException::adendoForaDaRetificacao();
        }

        if ($atendimento->status->ehTerminal()) {
            throw RegistroClinicoInvalidoException::atendimentoEncerrado();
        }

        $campos = $this->normalizar($conteudo, $tipo);

        if ($campos === []) {
            throw RegistroClinicoInvalidoException::conteudoVazio();
        }

        $profissional = $autor->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('registrar no prontuário');
        }

        return DB::transaction(function () use ($atendimento, $tipo, $autor, $profissional, $campos, $sigiloso) {
            $dados = [
                'uuid' => (string) Str::uuid(),
                'atendimento_id' => $atendimento->id,
                'tipo' => $tipo->value,
                'subjetivo' => null,
                'objetivo' => null,
                'avaliacao' => null,
                'plano' => null,
                'conteudo_livre' => null,
                ...$campos,
                'sigiloso' => $sigiloso,
                'registro_retificado_id' => null,
                'autor_id' => $profissional->user_id,
                // Snapshots (doc §9.3): se o cadastro do profissional mudar — troca de
                // nome, mudança de UF do conselho — o documento assinado não muda junto.
                'autor_nome' => $profissional->nome_completo,
                'autor_conselho' => $profissional->conselhoFormatado(),
                // RN-29: hora do servidor, nunca do cliente.
                'criado_em' => now(),
            ];

            $dados['hash_anterior'] = $this->hashes->ultimoHashDoAtendimento($atendimento->id);
            $dados['hash_conteudo'] = $this->hashes->calcular($dados);

            $registro = RegistroClinico::create($dados);

            $this->auditoria->registrar(
                acao: 'prontuario.criar',
                paciente: $atendimento->paciente,
                atendimento: $atendimento,
                entidade: 'RegistroClinico',
                entidadeId: $registro->id,
                // doc §9.6, salvaguarda 1: marcar como sigiloso é ato auditado. O log
                // guarda a decisão, não o texto -- o conteúdo clínico fica no prontuário.
                depois: ['tipo' => $tipo->value, 'sigiloso' => $sigiloso],
                usuario: $autor,
            );

            RegistroClinicoCriado::dispatch($registro);

            return $registro;
        });
    }

    /**
     * Descarta campos vazios e recusa SOAP em tipo que não o usa.
     *
     * @param  array<string, mixed>  $conteudo
     * @return array<string, string>
     */
    private function normalizar(array $conteudo, TipoRegistroClinico $tipo): array
    {
        $soap = ['subjetivo', 'objetivo', 'avaliacao', 'plano'];
        $campos = [];

        foreach ([...$soap, 'conteudo_livre'] as $campo) {
            $valor = trim((string) ($conteudo[$campo] ?? ''));

            if ($valor === '') {
                continue;
            }

            if (! $tipo->usaSoap() && in_array($campo, $soap, strict: true)) {
                throw RegistroClinicoInvalidoException::tipoNaoUsaSoap();
            }

            $campos[$campo] = $valor;
        }

        return $campos;
    }
}

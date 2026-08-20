<?php

declare(strict_types=1);

namespace App\Actions\Prontuario;

use App\Enums\TipoRegistroClinico;
use App\Events\RegistroRetificado;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Exceptions\RegistroClinicoInvalidoException;
use App\Models\RegistroClinico;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Prontuario\HashEncadeadoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RF-50 / RN-16 / RN-17 — retificação por adendo (doc §9.3).
 *
 * O registro original **não é tocado**. Permanece legível, marcado como retificado por
 * consequência da existência deste adendo.
 *
 * Isso é intencional e importante: em sindicância, o que se avalia é se a conduta foi
 * razoável **diante da informação disponível naquele momento** — e essa informação só é
 * reconstituível se o registro original sobreviver. Apagar a hipótese errada apagaria
 * justamente a prova de que a conduta era defensável.
 */
final class RetificarRegistroAction
{
    public function __construct(
        private readonly HashEncadeadoService $hashes,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  array{subjetivo?: ?string, objetivo?: ?string, avaliacao?: ?string, plano?: ?string, conteudo_livre?: ?string}  $conteudoCorrigido
     *
     * @throws RegistroClinicoInvalidoException
     */
    public function execute(
        RegistroClinico $original,
        User $autor,
        string $motivo,
        array $conteudoCorrigido,
    ): RegistroClinico {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw RegistroClinicoInvalidoException::motivoObrigatorio();
        }

        // Retificar o adendo criaria uma corrente de correções em que a versão vigente
        // depende de percorrer a cadeia inteira — ambígua exatamente quando mais importa.
        if ($original->tipo === TipoRegistroClinico::Adendo) {
            throw RegistroClinicoInvalidoException::adendoDeAdendo();
        }

        $campos = $this->normalizar($conteudoCorrigido);

        if ($campos === []) {
            throw RegistroClinicoInvalidoException::conteudoVazio();
        }

        $profissional = $autor->profissional;

        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('retificar registro do prontuário');
        }

        return DB::transaction(function () use ($original, $autor, $profissional, $motivo, $campos) {
            $dados = [
                'uuid' => (string) Str::uuid(),
                // A cadeia de hash é por atendimento: o adendo nasce no mesmo.
                'atendimento_id' => $original->atendimento_id,
                'tipo' => TipoRegistroClinico::Adendo->value,
                'subjetivo' => null,
                'objetivo' => null,
                'avaliacao' => null,
                'plano' => null,
                'conteudo_livre' => null,
                ...$campos,
                // O sigilo do original acompanha: retificar não é o caminho para tornar
                // público um registro que o médico decidiu não exibir no portal (§9.6).
                'sigiloso' => $original->sigiloso,
                'registro_retificado_id' => $original->id,   // ck_registro_adendo exige
                'motivo_retificacao' => $motivo,             // ck_registro_adendo exige
                'autor_id' => $profissional->user_id,
                'autor_nome' => $profissional->nome_completo,
                'autor_conselho' => $profissional->conselhoFormatado(),
                'criado_em' => now(),
            ];

            $dados['hash_anterior'] = $this->hashes->ultimoHashDoAtendimento($original->atendimento_id);
            $dados['hash_conteudo'] = $this->hashes->calcular($dados);

            $adendo = RegistroClinico::create($dados);

            $this->auditoria->registrar(
                acao: 'prontuario.retificar',
                paciente: $original->atendimento->paciente,
                atendimento: $original->atendimento,
                entidade: 'RegistroClinico',
                entidadeId: $adendo->id,
                antes: ['registro_retificado_id' => $original->id],
                depois: ['adendo_id' => $adendo->id],
                justificativa: $motivo,
                usuario: $autor,
            );

            RegistroRetificado::dispatch($original, $adendo);

            return $adendo;
        });
    }

    /**
     * @param  array<string, mixed>  $conteudo
     * @return array<string, string>
     */
    private function normalizar(array $conteudo): array
    {
        $campos = [];

        foreach (['subjetivo', 'objetivo', 'avaliacao', 'plano', 'conteudo_livre'] as $campo) {
            $valor = trim((string) ($conteudo[$campo] ?? ''));

            if ($valor !== '') {
                $campos[$campo] = $valor;
            }
        }

        return $campos;
    }
}

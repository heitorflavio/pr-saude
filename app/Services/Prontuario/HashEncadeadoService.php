<?php

declare(strict_types=1);

namespace App\Services\Prontuario;

use App\Models\RegistroClinico;
use Illuminate\Support\Carbon;

/**
 * Encadeamento de hash para detecção de adulteração (doc §9.4).
 *
 * Revogar `UPDATE` no banco protege contra a aplicação. **Não protege contra quem tem
 * acesso administrativo ao SGBD** — e é para esse cenário que cada registro carrega o
 * hash do anterior.
 *
 * **Qual é exatamente a garantia, e qual não é** (doc §9.4). Ser honesto sobre isso é
 * parte do trabalho:
 *
 * | Garante | Não garante |
 * |---|---|
 * | Alteração de um registro é **detectável** | Que a alteração seja **impossível** |
 * | Exclusão do meio da cadeia é detectável — o elo se rompe | Proteção contra quem tem acesso ao banco **e** conhece o algoritmo: pode recalcular a cadeia inteira dali para a frente |
 * | Evidência técnica objetiva em auditoria | Valor jurídico de assinatura digital |
 *
 * Fechar a terceira linha exigiria assinatura ICP-Brasil (Lei 13.787/2018, art. 2º) e
 * publicação periódica do hash da última âncora em meio externo. Ambos estão fora do
 * escopo (doc §1.3.2), mas o modelo já os acomoda — é por isso que `hash_conteudo` e
 * `hash_anterior` existem desde a primeira versão do esquema.
 */
final class HashEncadeadoService
{
    /**
     * Formato fixo da data na forma canônica.
     *
     * Sem isto o hash seria irreprodutível: na criação `criado_em` é um Carbon, na
     * verificação vem do banco, e as duas conversões para string dariam resultados
     * diferentes — a cadeia acusaria adulteração em todo registro íntegro.
     */
    private const FORMATO_DATA = 'Y-m-d H:i:s.u';

    /**
     * Hash sobre a **forma canônica**: ordem de chaves fixa e conjunto de campos
     * explícito.
     *
     * Sem canonicalização o mesmo conteúdo produziria hashes diferentes conforme a ordem
     * em que os campos chegassem, e a verificação seria inútil — acusaria adulteração
     * onde não houve, e o alarme constante treinaria todo mundo a ignorá-lo.
     *
     * @param  array<string, mixed>  $dados
     */
    public function calcular(array $dados): string
    {
        $canonico = json_encode([
            'atendimento_id' => (int) $dados['atendimento_id'],
            'tipo' => $this->texto($dados['tipo'] ?? null),
            'subjetivo' => $dados['subjetivo'] ?? null,
            'objetivo' => $dados['objetivo'] ?? null,
            'avaliacao' => $dados['avaliacao'] ?? null,
            'plano' => $dados['plano'] ?? null,
            'conteudo_livre' => $dados['conteudo_livre'] ?? null,
            'autor_id' => (int) $dados['autor_id'],
            'autor_conselho' => $dados['autor_conselho'] ?? null,
            'registro_retificado_id' => isset($dados['registro_retificado_id'])
                ? (int) $dados['registro_retificado_id']
                : null,
            'criado_em' => $this->data($dados['criado_em'] ?? null),
            'hash_anterior' => $dados['hash_anterior'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $canonico);
    }

    /**
     * Recalcula a partir do model.
     *
     * A fonte e `getAttributes()`, nao `toArray()` nem `getRawOriginal()`. `toArray()`
     * aplica casts e devolveria `criado_em` em ISO-8601 e `tipo` ja convertido -- forma
     * diferente da usada na criacao, e a cadeia acusaria adulteracao em todo registro
     * integro. `getRawOriginal()` esta vazio em model ainda nao persistido, o que
     * impediria calcular o hash antes de gravar.
     */
    public function calcularDoRegistro(RegistroClinico $registro): string
    {
        $atributos = $registro->getAttributes();

        return $this->calcular([
            'atendimento_id' => $atributos['atendimento_id'] ?? 0,
            'tipo' => $atributos['tipo'] ?? null,
            'subjetivo' => $atributos['subjetivo'] ?? null,
            'objetivo' => $atributos['objetivo'] ?? null,
            'avaliacao' => $atributos['avaliacao'] ?? null,
            'plano' => $atributos['plano'] ?? null,
            'conteudo_livre' => $atributos['conteudo_livre'] ?? null,
            'autor_id' => $atributos['autor_id'] ?? 0,
            'autor_conselho' => $atributos['autor_conselho'] ?? null,
            'registro_retificado_id' => $atributos['registro_retificado_id'] ?? null,
            'criado_em' => $atributos['criado_em'] ?? null,
            'hash_anterior' => $atributos['hash_anterior'] ?? null,
        ]);
    }

    /** O elo ao qual o próximo registro do atendimento vai se prender. */
    public function ultimoHashDoAtendimento(int $atendimentoId): ?string
    {
        return RegistroClinico::where('atendimento_id', $atendimentoId)
            ->orderByDesc('id')
            ->value('hash_conteudo');
    }

    /**
     * Verificação de integridade da cadeia. Rodada por job periódico e sob demanda, em
     * auditoria.
     *
     * @return array{integra: bool, quebras: array<int, array{id: int, motivo: string}>}
     */
    public function verificarCadeia(int $atendimentoId): array
    {
        $registros = RegistroClinico::where('atendimento_id', $atendimentoId)
            ->orderBy('id')
            ->get();

        $quebras = [];
        $hashEsperado = null;

        foreach ($registros as $registro) {
            // ELO_ROMPIDO: alguém removeu um registro do meio, ou reordenou a cadeia.
            if ($registro->hash_anterior !== $hashEsperado) {
                $quebras[] = ['id' => $registro->id, 'motivo' => 'ELO_ROMPIDO'];
            }

            // CONTEUDO_ALTERADO: o texto mudou por fora da aplicação.
            if ($this->calcularDoRegistro($registro) !== $registro->hash_conteudo) {
                $quebras[] = ['id' => $registro->id, 'motivo' => 'CONTEUDO_ALTERADO'];
            }

            /*
             * O esperado do próximo é o hash GRAVADO neste, não o recalculado. Usar o
             * recalculado mascararia a adulteração: um registro alterado passaria a ser
             * o novo "esperado" e a cadeia pareceria íntegra dali em diante.
             */
            $hashEsperado = $registro->hash_conteudo;
        }

        return ['integra' => $quebras === [], 'quebras' => $quebras];
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }

    private function data(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return ($valor instanceof Carbon ? $valor : Carbon::parse((string) $valor))
            ->format(self::FORMATO_DATA);
    }
}

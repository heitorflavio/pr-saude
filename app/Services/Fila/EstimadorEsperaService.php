<?php

declare(strict_types=1);

namespace App\Services\Fila;

use Illuminate\Support\Facades\DB;

/**
 * Estimativa de espera pela **média móvel de 30 dias do próprio profissional, por cor**
 * (doc §7.4) — não uma constante.
 *
 * "Profissionais têm ritmos diferentes, e o paciente merece uma estimativa honesta." Uma
 * constante única faria a tela mentir para todo mundo do mesmo jeito; a média por
 * profissional erra menos e erra de forma verificável.
 *
 * A duração vem de `fila_item`: `chamado_em` → `saiu_em`. É o tempo que aquele
 * profissional efetivamente gastou com um paciente daquela cor — não o tempo de
 * permanência do atendimento inteiro, que inclui exame, medicação e observação.
 */
final class EstimadorEsperaService
{
    private const JANELA_DIAS = 30;

    /**
     * Fallback de última instância, em minutos.
     *
     * Usado só quando não há histórico nenhum — instalação nova, ou cor que aquela
     * unidade nunca atendeu. Um número redondo e visivelmente aproximado é preferível a
     * esconder a ausência de dado atrás de uma precisão falsa.
     */
    private const PADRAO_MINUTOS = 20;

    /**
     * Duração média de atendimento do profissional para uma cor.
     *
     * Cascata deliberada: o histórico do próprio profissional; na falta dele, o da
     * instituição para aquela cor; na falta dos dois, o padrão.
     */
    public function duracaoMedia(?int $profissionalId, int $classificacaoRiscoId): int
    {
        if ($profissionalId !== null) {
            $propria = $this->media($classificacaoRiscoId, $profissionalId);

            if ($propria !== null) {
                return $propria;
            }
        }

        return $this->media($classificacaoRiscoId, null) ?? self::PADRAO_MINUTOS;
    }

    /**
     * Espera estimada de quem entrar agora na fila deste profissional.
     *
     * Soma a duração média de cada paciente à frente — e "à frente" é definido pela
     * ordenação da RN-10, que a `vw_fila_ordenada` já resolve. Somar só o número de
     * pessoas daria a mesma estimativa para uma fila de quatro azuis e uma de quatro
     * laranjas.
     */
    public function esperaEstimada(?int $profissionalId): int
    {
        // A soma percorre `fila_item` e não `vw_fila_ordenada` porque a view não expõe o
        // id da classificação — só o nome e a cor, que são para exibição. A ordem não
        // importa aqui: a soma das durações é a mesma em qualquer sequência.
        $cores = DB::table('fila_item')
            ->when(
                $profissionalId === null,
                fn ($q) => $q->whereNull('profissional_id'),
                fn ($q) => $q->where('profissional_id', $profissionalId)
            )
            ->whereIn('situacao', ['AGUARDANDO', 'CHAMADO'])
            ->pluck('classificacao_risco_id');

        return (int) $cores->sum(fn ($cor) => $this->duracaoMedia($profissionalId, (int) $cor));
    }

    private function media(int $classificacaoRiscoId, ?int $profissionalId): ?int
    {
        $media = DB::table('fila_item')
            ->whereNotNull('chamado_em')
            ->whereNotNull('saiu_em')
            ->where('classificacao_risco_id', $classificacaoRiscoId)
            ->where('saiu_em', '>=', now()->subDays(self::JANELA_DIAS))
            ->when($profissionalId !== null, fn ($q) => $q->where('profissional_id', $profissionalId))
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, chamado_em, saiu_em)'));

        return $media === null ? null : max(1, (int) round((float) $media));
    }
}

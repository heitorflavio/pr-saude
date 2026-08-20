<?php

declare(strict_types=1);

namespace App\Services\Fila;

use App\Enums\SituacaoEspera;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leitura da fila e da carga, **direto das views** `vw_fila_ordenada` e
 * `vw_carga_profissional`.
 *
 * A ordenação da RN-10, a posição por `ROW_NUMBER()` e a carga ponderada já estão
 * resolvidas lá. Reimplementá-las em PHP criaria uma segunda fonte de verdade que
 * divergiria da primeira — e a divergência apareceria como dois profissionais vendo
 * ordens diferentes da mesma fila.
 */
final class PainelFilaService
{
    public function __construct(private readonly AvaliadorEsperaService $avaliadorEspera) {}

    /**
     * Fila de um profissional, ou a fila geral quando `$profissionalId` é nulo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fila(?int $profissionalId, ?int $unidadeId = null): Collection
    {
        return DB::table('vw_fila_ordenada')
            ->when(
                $profissionalId === null,
                fn ($q) => $q->whereNull('profissional_id'),
                fn ($q) => $q->where('profissional_id', $profissionalId)
            )
            ->when($unidadeId !== null, fn ($q) => $q->whereIn(
                'atendimento_id',
                fn ($sub) => $sub->select('id')->from('atendimento')->where('unidade_id', $unidadeId)
            ))
            ->orderBy('posicao')
            ->get()
            ->map(function (object $item): array {
                // doc §7.3.1: a criticidade da espera é calculada para EXIBIR, nunca
                // para reordenar. A posição vem da view e não é tocada aqui.
                $situacao = $this->avaliadorEspera->avaliar(
                    (int) $item->espera_minutos,
                    (int) $item->tempo_alvo_minutos,
                );

                return [
                    'fila_item_id' => $item->fila_item_id,
                    'posicao' => (int) $item->posicao,
                    'atendimento_id' => $item->atendimento_id,
                    'atendimento_numero' => $item->atendimento_numero,
                    'paciente_id' => $item->paciente_id,
                    'paciente_nome' => $item->paciente_nome,
                    'idade_anos' => $item->idade_anos,
                    // RNF-15: nome do nível junto da cor, sempre.
                    'prioridade' => $item->prioridade_nome,
                    'prioridade_cor' => $item->prioridade_cor,
                    'prioridade_hex' => $item->prioridade_hex,
                    'entrou_em' => $item->entrou_em,
                    'espera_minutos' => (int) $item->espera_minutos,
                    'tempo_alvo_minutos' => (int) $item->tempo_alvo_minutos,
                    // RF-33
                    'tempo_alvo_excedido' => (bool) $item->tempo_alvo_excedido,
                    'situacao_espera' => $situacao->value,
                    'situacao_rotulo' => $situacao->rotulo(),
                    'sugere_reavaliacao' => $situacao->sugereReavaliacao(),
                    'status' => $item->atendimento_status,
                ];
            });
    }

    /**
     * Carga por profissional para a tela de atribuição (UC-05, doc §7.4).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cargas(EstimadorEsperaService $estimador, ?int $unidadeId = null): Collection
    {
        return DB::table('vw_carga_profissional')
            ->when($unidadeId !== null, fn ($q) => $q->whereIn(
                'profissional_id',
                fn ($sub) => $sub->select('user_id')->from('profissional')->where('unidade_id', $unidadeId)
            ))
            ->orderBy('carga_ponderada')
            ->orderBy('nome_completo')
            ->get()
            ->map(fn (object $c): array => [
                'profissional_id' => $c->profissional_id,
                'nome' => $c->nome_completo,
                'categoria' => $c->categoria,
                'especialidade' => $c->especialidade,
                'capacidade_fila' => (int) $c->capacidade_fila,
                'situacao' => $c->situacao,
                // Só quem está em plantão recebe atribuição nova.
                'disponivel' => in_array($c->situacao, ['DISPONIVEL', 'EM_ATENDIMENTO'], true),
                'pacientes_aguardando' => (int) $c->pacientes_aguardando,
                // RF-27: contar cabeças é métrica ruim -- cinco azuis não equivalem a
                // cinco laranjas. Por isso a composição por cor vem junto.
                'composicao' => [
                    'VERMELHO' => (int) $c->qtd_vermelho,
                    'LARANJA' => (int) $c->qtd_laranja,
                    'AMARELO' => (int) $c->qtd_amarelo,
                    'VERDE' => (int) $c->qtd_verde,
                    'AZUL' => (int) $c->qtd_azul,
                ],
                'carga_ponderada' => (int) $c->carga_ponderada,
                'espera_estimada_minutos' => $estimador->esperaEstimada((int) $c->profissional_id),
            ]);
    }

    /**
     * RF-28: sugere o profissional de menor carga ponderada entre os disponíveis.
     *
     * @param  Collection<int, array<string, mixed>>  $cargas
     */
    public function sugerido(Collection $cargas, ?string $categoria = null): ?int
    {
        return $cargas
            ->filter(fn (array $c) => $c['disponivel'])
            ->when($categoria !== null, fn ($col) => $col->filter(fn (array $c) => $c['categoria'] === $categoria))
            ->sortBy('carga_ponderada')
            ->first()['profissional_id'] ?? null;
    }

    /**
     * Quantos pacientes já na fila serão ultrapassados por um de prioridade `$peso`.
     *
     * UC-05, passo 6: o ator precisa saber que está preterindo gente antes de confirmar.
     * Preterir é legítimo — é a RN-10 funcionando — mas fazer isso sem avisar transforma
     * uma decisão clínica correta numa surpresa para quem está na sala de espera.
     */
    public function preteridos(?int $profissionalId, int $pesoOrdenacao): int
    {
        return DB::table('fila_item')
            ->join('classificacao_risco', 'classificacao_risco.id', '=', 'fila_item.classificacao_risco_id')
            ->when(
                $profissionalId === null,
                fn ($q) => $q->whereNull('fila_item.profissional_id'),
                fn ($q) => $q->where('fila_item.profissional_id', $profissionalId)
            )
            ->whereIn('fila_item.situacao', ['AGUARDANDO', 'CHAMADO'])
            ->where('classificacao_risco.peso_ordenacao', '>', $pesoOrdenacao)
            ->count();
    }

    /** @return array<int, array<string, string>> Os rótulos de espera, para a legenda. */
    public function legendaEspera(): array
    {
        return array_map(
            fn (SituacaoEspera $s) => ['valor' => $s->value, 'rotulo' => $s->rotulo(), 'acao' => $s->acaoDoSistema()],
            SituacaoEspera::cases()
        );
    }
}

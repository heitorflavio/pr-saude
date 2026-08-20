<?php

declare(strict_types=1);

namespace App\Services\Indicadores;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Os nove indicadores operacionais definidos na doc §7.6. */
final class IndicadoresService
{
    /** @return array<string, mixed> */
    public function calcular(CarbonInterface $inicio, CarbonInterface $fim, ?int $unidadeId = null): array
    {
        $portaTriagem = DB::table('triagem as t')
            ->joinSub($this->atendimentos($inicio, $fim, $unidadeId), 'a', 'a.id', '=', 't.atendimento_id')
            ->where('t.reclassificacao', false)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, a.admitido_em, t.criado_em)) AS media, COUNT(*) AS amostra')
            ->first();

        $portaAtendimento = DB::query()->fromSub($this->atendimentos($inicio, $fim, $unidadeId), 'a')
            ->whereNotNull('a.primeiro_atendimento_em')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, a.admitido_em, a.primeiro_atendimento_em)) AS media, COUNT(*) AS amostra')
            ->first();

        $aderencia = DB::table('fila_item as f')
            ->joinSub($this->atendimentos($inicio, $fim, $unidadeId), 'a', 'a.id', '=', 'f.atendimento_id')
            ->join('classificacao_risco as c', 'c.id', '=', 'f.classificacao_risco_id')
            ->whereNotNull('f.chamado_em')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, f.entrou_em, f.chamado_em) <= c.tempo_alvo_minutos * 60) AS aderentes')
            ->first();

        $permanencia = DB::query()->fromSub($this->atendimentos($inicio, $fim, $unidadeId), 'a')
            ->whereNotNull('a.finalizado_em')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, a.admitido_em, a.finalizado_em)) AS media, COUNT(*) AS amostra')
            ->first();

        $distribuicao = $this->distribuicaoPorCor($inicio, $fim, $unidadeId);

        $reclassificacao = DB::table('triagem as t')
            ->joinSub($this->atendimentos($inicio, $fim, $unidadeId), 'a', 'a.id', '=', 't.atendimento_id')
            ->selectRaw('COUNT(DISTINCT t.atendimento_id) AS total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN t.reclassificacao = 1 THEN t.atendimento_id END) AS reclassificados')
            ->first();

        $evasao = DB::query()->fromSub($this->atendimentos($inicio, $fim, $unidadeId), 'a')
            ->whereNotNull('a.finalizado_em')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(a.desfecho = 'EVASAO') AS evasoes")
            ->first();

        return [
            'periodo' => ['inicio' => $inicio->toDateString(), 'fim' => $fim->toDateString(), 'unidade_id' => $unidadeId],
            'tempo_porta_triagem' => $this->tempo($portaTriagem),
            'tempo_porta_atendimento' => $this->tempo($portaAtendimento),
            'aderencia_tempo_alvo' => $this->taxa((int) ($aderencia?->aderentes ?? 0), (int) ($aderencia?->total ?? 0)),
            'tempo_permanencia' => $this->tempo($permanencia),
            'distribuicao_cor' => $distribuicao,
            'taxa_reclassificacao' => $this->taxa((int) ($reclassificacao?->reclassificados ?? 0), (int) ($reclassificacao?->total ?? 0)),
            'taxa_evasao' => $this->taxa((int) ($evasao?->evasoes ?? 0), (int) ($evasao?->total ?? 0)),
            'produtividade_profissional' => $this->produtividade($inicio, $fim, $unidadeId),
            'tempo_medio_status' => $this->tempoPorStatus($inicio, $fim, $unidadeId),
        ];
    }

    private function atendimentos(CarbonInterface $inicio, CarbonInterface $fim, ?int $unidadeId): Builder
    {
        return DB::table('atendimento')
            ->select(['id', 'admitido_em', 'primeiro_atendimento_em', 'finalizado_em', 'desfecho', 'classificacao_risco_id'])
            ->whereBetween('admitido_em', [$inicio, $fim])
            ->when($unidadeId, fn (Builder $q) => $q->where('unidade_id', $unidadeId));
    }

    /** @return array{minutos: float|null, amostra: int} */
    private function tempo(?object $linha): array
    {
        return [
            'minutos' => $linha?->media !== null ? round((float) $linha->media / 60, 1) : null,
            'amostra' => (int) ($linha?->amostra ?? 0),
        ];
    }

    /** @return array{percentual: float|null, quantidade: int, total: int} */
    private function taxa(int $quantidade, int $total): array
    {
        return [
            'percentual' => $total > 0 ? round($quantidade * 100 / $total, 1) : null,
            'quantidade' => $quantidade,
            'total' => $total,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function distribuicaoPorCor(CarbonInterface $inicio, CarbonInterface $fim, ?int $unidadeId): Collection
    {
        $contagens = DB::query()->fromSub($this->atendimentos($inicio, $fim, $unidadeId), 'a')
            ->selectRaw('a.classificacao_risco_id, COUNT(*) AS total')
            ->whereNotNull('a.classificacao_risco_id')
            ->groupBy('a.classificacao_risco_id')
            ->pluck('total', 'classificacao_risco_id');

        return DB::table('classificacao_risco')->orderBy('peso_ordenacao')->get()
            ->map(fn (object $c) => [
                'id' => $c->id, 'nome' => $c->nome, 'cor' => $c->cor_nome,
                'total' => (int) ($contagens[$c->id] ?? 0),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function produtividade(CarbonInterface $inicio, CarbonInterface $fim, ?int $unidadeId): Collection
    {
        $concluidos = DB::table('fila_item as f')
            ->join('atendimento as a', 'a.id', '=', 'f.atendimento_id')
            ->where('f.situacao', 'CONCLUIDO')->whereNotNull('f.profissional_id')
            ->whereBetween('f.saiu_em', [$inicio, $fim])
            ->when($unidadeId, fn (Builder $q) => $q->where('a.unidade_id', $unidadeId))
            ->selectRaw('f.profissional_id, COUNT(DISTINCT f.atendimento_id) AS atendimentos')
            ->groupBy('f.profissional_id')->get();

        if ($concluidos->isEmpty()) {
            return collect();
        }

        $ids = $concluidos->pluck('profissional_id');
        $nomes = DB::table('profissional')->whereIn('user_id', $ids)->pluck('nome_completo', 'user_id');
        $horas = DB::table('profissional_disponibilidade')
            ->whereIn('profissional_id', $ids)
            ->whereNotIn('situacao', ['AUSENTE', 'FORA_PLANTAO'])
            ->where('inicio_em', '<', $fim)->where(fn (Builder $q) => $q->whereNull('fim_em')->orWhere('fim_em', '>', $inicio))
            ->get()->groupBy('profissional_id')->map(function (Collection $periodos) use ($inicio, $fim): float {
                $segundos = $periodos->sum(function (object $p) use ($inicio, $fim): int {
                    $de = max($inicio->getTimestamp(), strtotime($p->inicio_em));
                    $ate = min($fim->getTimestamp(), $p->fim_em ? strtotime($p->fim_em) : $fim->getTimestamp());

                    return max(0, $ate - $de);
                });

                return $segundos / 3600;
            });

        return $concluidos->map(function (object $linha) use ($nomes, $horas): array {
            $horasPlantao = (float) ($horas[$linha->profissional_id] ?? 0);

            return [
                'profissional_id' => $linha->profissional_id,
                'nome' => $nomes[$linha->profissional_id] ?? 'Profissional não localizado',
                'atendimentos' => (int) $linha->atendimentos,
                'horas_plantao' => round($horasPlantao, 1),
                'por_hora' => $horasPlantao > 0 ? round((int) $linha->atendimentos / $horasPlantao, 2) : null,
            ];
        })->sortByDesc(fn (array $item) => $item['por_hora'] ?? -1)->values();
    }

    /** @return Collection<int, array{status: string, minutos: float, amostra: int}> */
    private function tempoPorStatus(CarbonInterface $inicio, CarbonInterface $fim, ?int $unidadeId): Collection
    {
        return DB::table('atendimento_status_historico as h')
            ->joinSub($this->atendimentos($inicio, $fim, $unidadeId), 'a', 'a.id', '=', 'h.atendimento_id')
            ->whereNotNull('h.status_anterior')->whereNotNull('h.permanencia_segundos')
            ->selectRaw('h.status_anterior AS status, AVG(h.permanencia_segundos) AS media, COUNT(*) AS amostra')
            ->groupBy('h.status_anterior')->orderBy('h.status_anterior')->get()
            ->map(fn (object $linha) => [
                'status' => (string) $linha->status,
                'minutos' => round((float) $linha->media / 60, 1),
                'amostra' => (int) $linha->amostra,
            ]);
    }
}

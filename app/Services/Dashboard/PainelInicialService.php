<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\StatusAtendimento;
use App\Services\Fila\AvaliadorEsperaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregações da tela inicial da equipe: o estado do plantão agora.
 *
 * É Service e não Action porque **nada aqui escreve** — a regra do CLAUDE.md §5 vale
 * para escrita de dado clínico, e este painel só lê.
 *
 * A fila e as doses vêm das views `vw_fila_ordenada` e `vw_doses_pendentes`. A ordenação
 * da RN-10, a posição por `ROW_NUMBER()` e o atraso de dose já estão resolvidos lá;
 * recontá-los em PHP criaria uma segunda fonte de verdade, e a divergência apareceria
 * como o painel e a tela da fila discordando sobre quem é o próximo.
 *
 * Todo corte de tempo usa `now()` do servidor (RN-29).
 */
final class PainelInicialService
{
    public function __construct(private readonly AvaliadorEsperaService $avaliadorEspera) {}

    /**
     * Situação da fila inteira + os próximos que ainda não têm profissional.
     *
     * `posicao` só é comparável dentro da partição de um profissional (a view particiona
     * por `profissional_id`), por isso a lista de "próximos" é a dos **não atribuídos**:
     * ali a ordem da view é exatamente a da RN-10. Um "próximos" global exigiria
     * reordenar em PHP — a segunda fonte de verdade que este serviço evita.
     *
     * @return array<string, mixed>
     */
    public function fila(): array
    {
        $totais = DB::table('vw_fila_ordenada')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(profissional_id IS NULL) AS sem_profissional')
            // RF-33: quem já passou do tempo-alvo da própria cor.
            ->selectRaw('SUM(tempo_alvo_excedido) AS alem_do_alvo')
            ->first();

        return [
            'total' => (int) ($totais?->total ?? 0),
            'sem_profissional' => (int) ($totais?->sem_profissional ?? 0),
            'alem_do_alvo' => (int) ($totais?->alem_do_alvo ?? 0),
            'distribuicao' => $this->distribuicaoPorCor(),
            'aguardando_atribuicao' => $this->itens(null, 5),
        ];
    }

    /**
     * A fila do próprio profissional, para quem tem uma.
     *
     * @return array<string, mixed>
     */
    public function minhaFila(int $profissionalId): array
    {
        return [
            'total' => DB::table('vw_fila_ordenada')->where('profissional_id', $profissionalId)->count(),
            'alem_do_alvo' => DB::table('vw_fila_ordenada')
                ->where('profissional_id', $profissionalId)
                ->where('tempo_alvo_excedido', true)
                ->count(),
            'proximos' => $this->itens($profissionalId, 3),
        ];
    }

    /**
     * Atendimentos abertos por status + o movimento do dia.
     *
     * @return array<string, mixed>
     */
    public function atendimentos(): array
    {
        $contagens = DB::table('atendimento')
            ->whereNull('deleted_at')
            ->whereNotIn('status', [StatusAtendimento::Finalizado->value, StatusAtendimento::Cancelado->value])
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // A ordem é a da máquina de estados (doc §6.1), não a alfabética: o painel deve
        // ler como a jornada do paciente, da porta à alta.
        $porStatus = collect(StatusAtendimento::cases())
            ->reject(fn (StatusAtendimento $s) => $s->ehTerminal())
            ->map(fn (StatusAtendimento $s) => [
                'status' => $s->value,
                'rotulo' => $s->rotulo(),
                'total' => (int) ($contagens[$s->value] ?? 0),
            ])
            ->values();

        return [
            'ativos' => (int) $contagens->sum(),
            'em_atendimento' => (int) ($contagens[StatusAtendimento::EmAtendimento->value] ?? 0),
            'por_status' => $porStatus,
            'admitidos_hoje' => DB::table('atendimento')->whereNull('deleted_at')
                ->where('admitido_em', '>=', now()->startOfDay())->count(),
            'finalizados_hoje' => DB::table('atendimento')->whereNull('deleted_at')
                ->where('finalizado_em', '>=', now()->startOfDay())->count(),
        ];
    }

    /**
     * Checklist de doses do turno, resumido (RF-60).
     *
     * @return array<string, mixed>
     */
    public function doses(): array
    {
        $totais = DB::table('vw_doses_pendentes')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(atrasada) AS atrasadas')
            ->first();

        return [
            'pendentes' => (int) ($totais?->total ?? 0),
            'atrasadas' => (int) ($totais?->atrasadas ?? 0),
            'proximas' => DB::table('vw_doses_pendentes')
                ->orderByDesc('atrasada')->orderBy('horario_previsto')->limit(5)->get()
                ->map(fn (object $dose): array => [
                    'aprazamento_id' => $dose->aprazamento_id,
                    'atendimento_id' => $dose->atendimento_id,
                    'paciente' => $dose->paciente_nome,
                    'medicamento' => $dose->nome_comercial,
                    'principio_ativo' => $dose->principio_ativo,
                    'dose' => $dose->dose.' '.$dose->unidade_dose,
                    // RN-22: quem olha o painel já sabe que essa dose vai exigir um
                    // segundo profissional antes de ir até o leito.
                    'alta_vigilancia' => (bool) $dose->alta_vigilancia,
                    'horario_previsto' => date('d/m/Y H:i', strtotime((string) $dose->horario_previsto)),
                    'atrasada' => (bool) $dose->atrasada,
                ])
                ->values(),
        ];
    }

    /**
     * Fila do laboratório em números.
     *
     * @return array<string, mixed>
     */
    public function exames(): array
    {
        $contagens = DB::table('exame_solicitacao as s')
            ->join('atendimento as a', 'a.id', '=', 's.atendimento_id')
            ->whereNull('a.deleted_at')
            ->selectRaw('s.situacao, COUNT(*) AS total')
            ->groupBy('s.situacao')
            ->pluck('total', 'situacao');

        return [
            'a_coletar' => (int) ($contagens['SOLICITADO'] ?? 0),
            'em_execucao' => (int) ($contagens['COLETADO'] ?? 0) + (int) ($contagens['EM_EXECUCAO'] ?? 0),
            // RN-24: resultado concluído não é visível ao paciente antes da liberação
            // explícita. A pendência é de alguém, e o painel diz de quantos.
            'aguardando_liberacao' => (int) ($contagens['CONCLUIDO'] ?? 0),
        ];
    }

    /**
     * Itens da fila de um profissional (ou os não atribuídos, quando nulo).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function itens(?int $profissionalId, int $limite): Collection
    {
        return DB::table('vw_fila_ordenada')
            ->when(
                $profissionalId === null,
                fn ($q) => $q->whereNull('profissional_id'),
                fn ($q) => $q->where('profissional_id', $profissionalId)
            )
            ->orderBy('posicao')
            ->limit($limite)
            ->get()
            ->map(function (object $item): array {
                // doc §7.3.1: a criticidade da espera é calculada para EXIBIR, nunca
                // para reordenar. Envelhecer prioridade seria risco assistencial.
                $situacao = $this->avaliadorEspera->avaliar(
                    (int) $item->espera_minutos,
                    (int) $item->tempo_alvo_minutos,
                );

                return [
                    'fila_item_id' => $item->fila_item_id,
                    'posicao' => (int) $item->posicao,
                    'atendimento_id' => $item->atendimento_id,
                    'atendimento_numero' => $item->atendimento_numero,
                    'paciente_nome' => $item->paciente_nome,
                    // RNF-15: a cor nunca vai sozinha; o rótulo do nível vai com ela.
                    'prioridade' => $item->prioridade_nome,
                    'prioridade_cor' => $item->prioridade_cor,
                    'espera_minutos' => (int) $item->espera_minutos,
                    'tempo_alvo_excedido' => (bool) $item->tempo_alvo_excedido,
                    'situacao_rotulo' => $situacao->rotulo(),
                    'sugere_reavaliacao' => $situacao->sugereReavaliacao(),
                ];
            });
    }

    /**
     * Composição da fila por cor, incluindo as cores com zero — a ausência de vermelho
     * é informação, e uma lista que some com ele faz o leitor duvidar do painel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function distribuicaoPorCor(): Collection
    {
        $contagens = DB::table('vw_fila_ordenada')
            ->selectRaw('prioridade_cor, COUNT(*) AS total')
            ->groupBy('prioridade_cor')
            ->pluck('total', 'prioridade_cor');

        return DB::table('classificacao_risco')->orderBy('peso_ordenacao')->get()
            ->map(fn (object $c): array => [
                'id' => $c->id,
                'nome' => $c->nome,
                'cor' => $c->cor_nome,
                'tempo_alvo_minutos' => (int) $c->tempo_alvo_minutos,
                'total' => (int) ($contagens[$c->cor_nome] ?? 0),
            ]);
    }
}

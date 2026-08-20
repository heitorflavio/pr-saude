<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Atendimento\AbrirAtendimentoAction;
use App\Actions\Atendimento\AlterarStatusAction;
use App\Actions\Atendimento\FinalizarAtendimentoAction;
use App\Enums\StatusAtendimento;
use App\Exceptions\AtendimentoAtivoExistenteException;
use App\Exceptions\DesfechoObrigatorioException;
use App\Exceptions\TransicaoInvalidaException;
use App\Http\Requests\Atendimento\AbrirAtendimentoRequest;
use App\Http\Requests\Atendimento\AlterarStatusRequest;
use App\Http\Requests\Atendimento\FinalizarAtendimentoRequest;
use App\Models\Atendimento;
use App\Models\Paciente;
use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AtendimentoController extends Controller
{
    /** Visão operacional que evita passar pela busca de pacientes para retomar um caso. */
    public function geral(): Response
    {
        $this->authorize('viewAny', Atendimento::class);

        $relacoes = ['paciente', 'unidade', 'classificacaoRisco', 'profissionalResponsavel'];
        $terminais = [StatusAtendimento::Finalizado->value, StatusAtendimento::Cancelado->value];

        $emAndamento = Atendimento::query()
            ->with($relacoes)
            ->whereNotIn('status', $terminais)
            ->orderBy('admitido_em')
            ->get()
            ->map(fn (Atendimento $atendimento) => $this->resumoGeral($atendimento));

        $recentes = Atendimento::query()
            ->with($relacoes)
            ->whereIn('status', $terminais)
            ->latest('finalizado_em')
            ->limit(20)
            ->get()
            ->map(fn (Atendimento $atendimento) => $this->resumoGeral($atendimento));

        return Inertia::render('Atendimentos/Geral', [
            'emAndamento' => $emAndamento,
            'recentes' => $recentes,
        ]);
    }

    /** RF-18: os atendimentos do paciente, separando em andamento de finalizados. */
    public function index(Paciente $paciente): Response
    {
        $this->authorize('verFichaCadastral', $paciente);

        $atendimentos = $paciente->atendimentos()
            ->with(['unidade', 'classificacaoRisco', 'profissionalResponsavel'])
            ->orderByDesc('admitido_em')
            ->get()
            ->map(fn (Atendimento $atendimento) => $this->resumo($atendimento));

        return Inertia::render('Atendimentos/Index', [
            'paciente' => [
                'user_id' => $paciente->user_id,
                'nome' => $paciente->nomeExibicao(),
            ],
            // A separação é o ponto da RF-18: o episódio em curso não pode ficar
            // perdido numa lista cronológica junto com os de dois anos atrás.
            'emAndamento' => $atendimentos->reject(fn (array $a) => $a['terminal'])->values(),
            'finalizados' => $atendimentos->filter(fn (array $a) => $a['terminal'])->values(),
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'podeAbrir' => request()->user()?->can('atendimento.abrir') ?? false,
        ]);
    }

    /** RF-22: linha do tempo consolidada do atendimento. */
    public function show(Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);

        $atendimento->load([
            'paciente.alergias.medicamento',
            'unidade',
            'classificacaoRisco',
            'profissionalResponsavel',
            'statusHistorico.autor',
            'sintomas.queixa',
        ]);

        return Inertia::render('Atendimentos/Show', [
            'atendimento' => $this->resumo($atendimento) + [
                'sintomas_entrada' => $atendimento->sintomas_entrada,
                'queixas' => $atendimento->sintomas
                    ->map(fn ($sintoma) => $sintoma->queixa?->descricao ?? $sintoma->descricao_livre)
                    ->filter()
                    ->values(),
                'desfecho' => $atendimento->desfecho,
                'desfecho_observacao' => $atendimento->desfecho_observacao,
            ],
            'paciente' => [
                'user_id' => $atendimento->paciente->user_id,
                'nome' => $atendimento->paciente->nomeExibicao(),
                'data_nascimento' => $atendimento->paciente->data_nascimento?->format('d/m/Y'),
                'idade' => $atendimento->paciente->idadeDescritiva(),
            ],
            // RF-11: alergias em destaque em toda tela do atendimento.
            'alergias' => $atendimento->paciente->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            /*
             * RN-15: o histórico é acrescentado, nunca sobrescrito — por isso a linha do
             * tempo pode ser lida direto dele, sem reconstrução. `permanencia_segundos`
             * responde "quanto tempo ele ficou parado em cada etapa?" (RF-39), que é a
             * pergunta que o indicador de qualidade da fila consome.
             */
            'linhaDoTempo' => $atendimento->statusHistorico
                ->sortBy('criado_em')
                ->values()
                ->map(fn ($evento) => [
                    'id' => $evento->id,
                    'de' => $evento->status_anterior !== null
                        ? StatusAtendimento::from($evento->status_anterior)->rotulo()
                        : null,
                    'para' => StatusAtendimento::from($evento->status_novo)->rotulo(),
                    'em' => $evento->criado_em?->format('d/m/Y H:i:s'),
                    'por' => $evento->autor?->name,
                    'observacao' => $evento->observacao,
                    'permanencia' => $this->duracao($evento->permanencia_segundos),
                ]),
            // Só as transições legais aparecem na tela: a máquina de estados é a fonte,
            // e o formulário não oferece o que a Action recusaria.
            'transicoesPermitidas' => collect($atendimento->status->transicoesPermitidas())
                ->map(fn (StatusAtendimento $status) => [
                    'valor' => $status->value,
                    'rotulo' => $status->rotulo(),
                    'terminal' => $status->ehTerminal(),
                ]),
            'desfechos' => FinalizarAtendimentoAction::DESFECHOS,
        ]);
    }

    public function store(
        AbrirAtendimentoRequest $request,
        Paciente $paciente,
        AbrirAtendimentoAction $abrir,
    ): RedirectResponse {
        $unidade = Unidade::findOrFail($request->validated('unidade_id'));

        try {
            $atendimento = $abrir->execute(
                paciente: $paciente,
                unidade: $unidade,
                autor: $request->user(),
                origem: $request->validated('origem') ?? 'ESPONTANEA',
                sintomasEntrada: $request->validated('sintomas_entrada'),
                queixaIds: $request->validated('queixas') ?? [],
            );
        } catch (AtendimentoAtivoExistenteException $e) {
            // RN-07: em vez de duplicar, leva ao atendimento que já existe.
            return $e->atendimento !== null
                ? redirect()->route('atendimentos.show', $e->atendimento->id)->with('alerta', $e->getMessage())
                : back()->with('alerta', $e->getMessage());
        }

        return redirect()->route('atendimentos.show', $atendimento->id)
            ->with('status', "Atendimento {$atendimento->numero} aberto.");
    }

    public function alterarStatus(
        AlterarStatusRequest $request,
        Atendimento $atendimento,
        AlterarStatusAction $alterar,
    ): RedirectResponse {
        $destino = StatusAtendimento::from($request->validated('status'));

        // RN-12: a Policy precisa do destino -- a restrição do laboratório depende do
        // par (origem, destino), não só do papel.
        $this->authorize('alterarStatus', [$atendimento, $destino]);

        try {
            $alterar->execute(
                atendimento: $atendimento,
                novoStatus: $destino,
                autor: $request->user(),
                observacao: $request->validated('observacao'),
                desfecho: $request->validated('desfecho'),
            );
        } catch (TransicaoInvalidaException|DesfechoObrigatorioException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Situação do atendimento atualizada.');
    }

    public function finalizar(
        FinalizarAtendimentoRequest $request,
        Atendimento $atendimento,
        FinalizarAtendimentoAction $finalizar,
    ): RedirectResponse {
        $this->authorize('finalizar', $atendimento);

        try {
            $finalizar->execute(
                atendimento: $atendimento,
                desfecho: $request->validated('desfecho'),
                autor: $request->user(),
                observacao: $request->validated('observacao'),
            );
        } catch (TransicaoInvalidaException|DesfechoObrigatorioException $e) {
            return back()->withErrors(['desfecho' => $e->getMessage()]);
        }

        return back()->with('status', 'Atendimento finalizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(Atendimento $atendimento): array
    {
        return [
            'id' => $atendimento->id,
            'numero' => $atendimento->numero,
            'status' => $atendimento->status->value,
            'status_rotulo' => $atendimento->status->rotulo(),
            'terminal' => $atendimento->status->ehTerminal(),
            'origem' => $atendimento->origem,
            'unidade' => $atendimento->unidade?->nome,
            'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
            'primeiro_atendimento_em' => $atendimento->primeiro_atendimento_em?->format('d/m/Y H:i'),
            'finalizado_em' => $atendimento->finalizado_em?->format('d/m/Y H:i'),
            'desfecho' => $atendimento->desfecho,
            'responsavel' => $atendimento->profissionalResponsavel?->nome_completo,
            // RNF-15: a cor vem sempre acompanhada do nome do nível.
            'prioridade' => $atendimento->classificacaoRisco?->nome,
            'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
            'prioridade_hex' => $atendimento->classificacaoRisco?->cor_hex,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumoGeral(Atendimento $atendimento): array
    {
        return $this->resumo($atendimento) + [
            'paciente_id' => $atendimento->paciente_id,
            'paciente_nome' => $atendimento->paciente->nomeExibicao(),
        ];
    }

    private function duracao(?int $segundos): ?string
    {
        if ($segundos === null) {
            return null;
        }

        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        if ($horas > 0) {
            return "{$horas} h {$minutos} min";
        }

        return $minutos > 0 ? "{$minutos} min" : "{$segundos} s";
    }
}

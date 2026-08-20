<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fila\AtribuirProfissionalAction;
use App\Actions\Fila\TransferirFilaAction;
use App\Exceptions\DominioException;
use App\Http\Requests\Fila\AtribuirProfissionalRequest;
use App\Http\Requests\Fila\TransferirFilaRequest;
use App\Models\Atendimento;
use App\Models\Profissional;
use App\Services\Fila\EstimadorEsperaService;
use App\Services\Fila\PainelFilaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FilaController extends Controller
{
    public function __construct(
        private readonly PainelFilaService $painel,
        private readonly EstimadorEsperaService $estimador,
    ) {}

    /**
     * RF-29: painel do profissional.
     *
     * A prop `fila` é a única que o `usePoll(10000, { only: ['fila'] })` recarrega
     * (RF-34, RNF-03) — o resto da página não é reenviado a cada dez segundos.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Atendimento::class);

        $profissional = $request->user()->profissional;

        // `?fila=geral` mostra quem ainda não tem dono; o padrão é a fila de quem olha.
        $verGeral = $request->string('fila')->toString() === 'geral' || $profissional === null;
        $profissionalId = $verGeral ? null : $profissional->user_id;

        return Inertia::render('Fila/Index', [
            'fila' => fn () => $this->painel->fila($profissionalId),
            'contexto' => [
                'geral' => $verGeral,
                'profissional' => $profissional?->nome_completo,
                'pode_atribuir' => $request->user()->can('fila.atribuir'),
            ],
            'legendaEspera' => $this->painel->legendaEspera(),
        ]);
    }

    /** Tela de atribuição (UC-05, mockup da doc §7.4). */
    public function atribuir(Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);

        $atendimento->load(['paciente', 'classificacaoRisco', 'unidade']);

        $cargas = $this->painel->cargas($this->estimador, $atendimento->unidade_id);
        $peso = $atendimento->classificacaoRisco?->peso_ordenacao;

        return Inertia::render('Fila/Atribuir', [
            'atendimento' => [
                'id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'paciente' => $atendimento->paciente->nomeExibicao(),
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
                'responsavel_atual' => $atendimento->profissional_responsavel_id,
            ],
            'cargas' => $cargas,
            // RF-28: o de menor carga ponderada entre os disponíveis.
            'sugerido' => $this->painel->sugerido($cargas),
            /*
             * UC-05, passo 6: quantos já na fila serão ultrapassados. Preterir é a RN-10
             * funcionando, mas fazer isso sem avisar transforma uma decisão clínica
             * correta numa surpresa para quem está na sala de espera.
             */
            'preteridos' => $peso === null
                ? []
                : $cargas->mapWithKeys(fn (array $c) => [
                    $c['profissional_id'] => $this->painel->preteridos((int) $c['profissional_id'], $peso),
                ]),
        ]);
    }

    public function store(
        AtribuirProfissionalRequest $request,
        Atendimento $atendimento,
        AtribuirProfissionalAction $atribuir,
    ): RedirectResponse {
        $profissional = Profissional::findOrFail($request->validated('profissional_id'));

        try {
            $atribuir->execute($atendimento, $profissional, $request->user());
        } catch (DominioException $e) {
            return back()->withErrors(['profissional_id' => $e->getMessage()]);
        }

        return redirect()->route('fila.index')
            ->with('status', "Paciente atribuído a {$profissional->nome_completo}.");
    }

    public function transferir(
        TransferirFilaRequest $request,
        Atendimento $atendimento,
        TransferirFilaAction $transferir,
    ): RedirectResponse {
        $destino = Profissional::findOrFail($request->validated('profissional_id'));

        try {
            $transferir->execute($atendimento, $destino, $request->user(), $request->validated('justificativa'));
        } catch (DominioException $e) {
            return back()->withErrors(['profissional_id' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            "Paciente transferido para {$destino->nome_completo}. A posição na fila foi preservada."
        );
    }
}

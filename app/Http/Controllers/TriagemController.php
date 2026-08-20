<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Triagem\RealizarTriagemAction;
use App\Actions\Triagem\ReclassificarRiscoAction;
use App\Exceptions\DominioException;
use App\Http\Requests\Triagem\RealizarTriagemRequest;
use App\Http\Requests\Triagem\ReclassificarRiscoRequest;
use App\Models\Atendimento;
use App\Models\ClassificacaoRisco;
use App\Models\FilaItem;
use App\Models\Queixa;
use App\Services\Fila\AvaliadorEsperaService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TriagemController extends Controller
{
    public function __construct(private readonly AvaliadorEsperaService $avaliadorEspera) {}

    /** Tela de triagem / reclassificação de um atendimento. */
    public function edit(Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);

        $atendimento->load([
            'paciente.alergias.medicamento',
            'classificacaoRisco',
            'triagens.classificacaoRisco',
            'triagens.executor',
            'triagens.sinalVital',
        ]);

        $vigente = $atendimento->triagemVigente();
        $filaItem = $atendimento->filaItemAtivo();

        return Inertia::render('Triagem/Edit', [
            'atendimento' => [
                'id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'status_rotulo' => $atendimento->status->rotulo(),
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
            ],
            'paciente' => [
                'user_id' => $atendimento->paciente->user_id,
                'nome' => $atendimento->paciente->nomeExibicao(),
                'data_nascimento' => $atendimento->paciente->data_nascimento?->format('d/m/Y'),
                'idade' => $atendimento->paciente->idadeDescritiva(),
            ],
            // RF-11: alergias em destaque também aqui.
            'alergias' => $atendimento->paciente->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            'classificacoes' => ClassificacaoRisco::orderBy('peso_ordenacao')->get()->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'cor' => $c->cor_nome->value,
                'cor_hex' => $c->cor_hex,
                'tempo_alvo_minutos' => $c->tempo_alvo_minutos,
                'exige_atendimento_imediato' => $c->exige_atendimento_imediato,
                'descricao' => $c->descricao,
            ]),
            'queixas' => Queixa::where('ativo', true)->orderBy('descricao')->get(['id', 'descricao', 'fluxograma_manchester']),
            'jaTriado' => $vigente !== null,
            /*
             * doc §7.5: a cadeia inteira de triagens, da mais recente para a mais antiga.
             * A anterior permanece legível -- é ela que permite reconstruir "entrou verde
             * e virou laranja às 14h20, com queda de saturação".
             */
            'triagens' => $atendimento->triagens->sortByDesc('criado_em')->values()->map(fn ($t) => [
                'id' => $t->id,
                'classificacao' => $t->classificacaoRisco?->nome,
                'cor' => $t->classificacaoRisco?->cor_nome->value,
                'queixa_principal' => $t->queixa_principal,
                'justificativa' => $t->justificativa_classificacao,
                'reclassificacao' => $t->reclassificacao,
                'triagem_anterior_id' => $t->triagem_anterior_id,
                'em' => $t->criado_em?->format('d/m/Y H:i'),
                'por' => $t->executor?->nome_completo,
                'sinais_vitais' => $t->sinalVital ? [
                    'pressao' => $t->sinalVital->pressao_sistolica.'/'.$t->sinalVital->pressao_diastolica,
                    'fc' => $t->sinalVital->frequencia_cardiaca,
                    'fr' => $t->sinalVital->frequencia_respiratoria,
                    'spo2' => $t->sinalVital->saturacao_o2,
                    'temperatura' => $t->sinalVital->temperatura,
                    'dor' => $t->sinalVital->escala_dor,
                ] : null,
            ]),
            'espera' => $this->espera($atendimento, $filaItem),
        ]);
    }

    public function store(
        RealizarTriagemRequest $request,
        Atendimento $atendimento,
        RealizarTriagemAction $triar,
    ): RedirectResponse {
        try {
            $triar->execute(
                atendimento: $atendimento,
                classificacaoRiscoId: (int) $request->validated('classificacao_risco_id'),
                autor: $request->user(),
                queixaPrincipal: $request->validated('queixa_principal'),
                justificativa: $request->validated('justificativa_classificacao'),
                sinaisVitais: $request->validated('sinais_vitais') ?? [],
            );
        } catch (DominioException $e) {
            return back()->withErrors(['classificacao_risco_id' => $e->getMessage()]);
        }

        return redirect()->route('atendimentos.show', $atendimento->id)
            ->with('status', 'Triagem registrada.');
    }

    public function reclassificar(
        ReclassificarRiscoRequest $request,
        Atendimento $atendimento,
        ReclassificarRiscoAction $reclassificar,
    ): RedirectResponse {
        try {
            $reclassificar->execute(
                atendimento: $atendimento,
                novaClassificacaoId: (int) $request->validated('classificacao_risco_id'),
                autor: $request->user(),
                justificativa: $request->validated('justificativa'),
                sinaisVitais: $request->validated('sinais_vitais') ?? [],
            );
        } catch (DominioException $e) {
            return back()->withErrors(['justificativa' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Risco reclassificado. A pulseira precisa ser reimpressa com a nova cor (RN-09).'
        );
    }

    /**
     * doc §7.3.1: a criticidade da espera, **sem reordenar nada**.
     *
     * @return array<string, mixed>|null
     */
    private function espera(Atendimento $atendimento, ?FilaItem $filaItem): ?array
    {
        if ($filaItem === null || $atendimento->classificacaoRisco === null) {
            return null;
        }

        $minutos = (int) $filaItem->entrou_em->diffInMinutes(now());
        $situacao = $this->avaliadorEspera->avaliar($minutos, $atendimento->classificacaoRisco->tempo_alvo_minutos);

        return [
            'minutos' => $minutos,
            'tempo_alvo' => $atendimento->classificacaoRisco->tempo_alvo_minutos,
            'situacao' => $situacao->value,
            'rotulo' => $situacao->rotulo(),
            'acao' => $situacao->acaoDoSistema(),
            // RF-33: o sistema SUGERE reavaliação; nunca promove sozinho.
            'sugere_reavaliacao' => $situacao->sugereReavaliacao(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Exame\AlterarSituacaoExameAction;
use App\Actions\Exame\LiberarResultadoAction;
use App\Actions\Exame\RegistrarResultadoAction;
use App\Actions\Exame\SolicitarExameAction;
use App\Enums\SituacaoExame;
use App\Exceptions\DominioException;
use App\Http\Requests\Exame\AlterarSituacaoExameRequest;
use App\Http\Requests\Exame\LiberarResultadoRequest;
use App\Http\Requests\Exame\RegistrarResultadoRequest;
use App\Http\Requests\Exame\SolicitarExameRequest;
use App\Models\Atendimento;
use App\Models\Exame;
use App\Models\ExameAnexo;
use App\Models\ExameResultado;
use App\Models\ExameSolicitacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExameController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->can('exame.ler_solicitacao'), 403);

        return Inertia::render('Exames/Index', [
            // RF-63: urgentes primeiro; o scope é a fonte única da fila.
            'fila' => ExameSolicitacao::query()->filaLaboratorio()->with('exame', 'atendimento.paciente')->get()->map(fn ($s) => [
                'id' => $s->id, 'carater' => $s->carater, 'situacao' => $s->situacao->value,
                'situacao_rotulo' => $s->situacao->rotulo(), 'solicitado_em' => $s->solicitado_em?->format('d/m/Y H:i'),
                'exame' => $s->exame->nome, 'preparo' => $s->exame->preparo,
                'atendimento' => $s->atendimento->numero, 'paciente' => $s->atendimento->paciente->nomeExibicao(),
            ]),
        ]);
    }

    public function create(Atendimento $atendimento): Response
    {
        abort_unless(request()->user()?->can('exame.solicitar'), 403);
        $atendimento->load('paciente');

        return Inertia::render('Exames/Create', [
            'atendimento' => ['id' => $atendimento->id, 'numero' => $atendimento->numero],
            'paciente' => ['nome' => $atendimento->paciente->nomeExibicao()],
            'catalogo' => Exame::query()->where('ativo', true)->orderBy('tipo')->orderBy('nome')->get()
                ->map(fn ($e) => [
                    'id' => $e->id, 'codigo' => $e->codigo, 'nome' => $e->nome,
                    'tipo' => $e->tipo, 'preparo' => $e->preparo, 'prazo' => $e->prazo_padrao_minutos,
                ]),
        ]);
    }

    public function show(ExameSolicitacao $solicitacao): Response
    {
        abort_unless(request()->user()?->can('exame.ler_solicitacao'), 403);
        $solicitacao->load('exame', 'solicitante', 'coletor', 'atendimento.paciente.alergias', 'resultado.itens', 'resultado.anexos', 'resultado.executor', 'resultado.liberador');

        return Inertia::render('Exames/Show', [
            'solicitacao' => [
                'id' => $solicitacao->id, 'carater' => $solicitacao->carater,
                'situacao' => $solicitacao->situacao->value, 'situacao_rotulo' => $solicitacao->situacao->rotulo(),
                'solicitado_em' => $solicitacao->solicitado_em?->format('d/m/Y H:i'),
                'indicacao' => $solicitacao->indicacao_clinica, 'solicitante' => $solicitacao->solicitante?->nome_completo,
            ],
            'atendimento' => ['id' => $solicitacao->atendimento->id, 'numero' => $solicitacao->atendimento->numero],
            'paciente' => ['id' => $solicitacao->atendimento->paciente_id, 'nome' => $solicitacao->atendimento->paciente->nomeExibicao()],
            'exame' => ['codigo' => $solicitacao->exame->codigo, 'nome' => $solicitacao->exame->nome, 'tipo' => $solicitacao->exame->tipo, 'preparo' => $solicitacao->exame->preparo],
            'resultado' => $solicitacao->resultado ? [
                'id' => $solicitacao->resultado->id, 'laudo' => $solicitacao->resultado->laudo,
                'conclusao' => $solicitacao->resultado->conclusao, 'critico' => $solicitacao->resultado->possui_valor_critico,
                'visivel' => $solicitacao->resultado->visivel_ao_paciente,
                'executado_em' => $solicitacao->resultado->executado_em?->format('d/m/Y H:i'),
                'executor' => $solicitacao->resultado->executor?->nome_completo,
                'itens' => $solicitacao->resultado->itens,
                'anexos' => $solicitacao->resultado->anexos->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome_original, 'mime' => $a->mime, 'tamanho' => $a->tamanho_bytes]),
            ] : null,
            'permissoes' => [
                'executar' => request()->user()?->can('exame.executar') ?? false,
                'liberar' => $solicitacao->resultado ? (request()->user()?->can('liberar', $solicitacao->resultado) ?? false) : false,
            ],
        ]);
    }

    public function solicitar(SolicitarExameRequest $request, Atendimento $atendimento, SolicitarExameAction $acao): RedirectResponse
    {
        try {
            $solicitacao = $acao->execute(
                $atendimento, Exame::findOrFail($request->integer('exame_id')), $request->user(),
                $request->validated('carater'), $request->validated('indicacao_clinica'),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['exame_id' => $e->getMessage()]);
        }

        return redirect()->route('exames.show', $solicitacao)->with('status', 'Exame solicitado.');
    }

    public function alterar(AlterarSituacaoExameRequest $request, ExameSolicitacao $solicitacao, AlterarSituacaoExameAction $acao): RedirectResponse
    {
        try {
            $acao->execute($solicitacao, SituacaoExame::from($request->validated('situacao')), $request->user(), $request->validated('motivo'));
        } catch (DominioException $e) {
            return back()->withErrors(['situacao' => $e->getMessage()]);
        }

        return back()->with('status', 'Situação do exame atualizada.');
    }

    public function resultado(RegistrarResultadoRequest $request, ExameSolicitacao $solicitacao, RegistrarResultadoAction $acao): RedirectResponse
    {
        try {
            $acao->execute(
                $solicitacao, $request->user(), $request->validated('itens') ?? [],
                $request->validated('laudo'), $request->validated('conclusao'), $request->file('anexos', []),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['resultado' => $e->getMessage()]);
        }

        return back()->with('status', 'Resultado registrado; aguardando liberação explícita.');
    }

    public function liberar(LiberarResultadoRequest $request, ExameResultado $resultado, LiberarResultadoAction $acao): RedirectResponse
    {
        try {
            $acao->execute($resultado, $request->user());
        } catch (DominioException $e) {
            return back()->withErrors(['liberacao' => $e->getMessage()]);
        }

        return back()->with('status', 'Resultado liberado ao paciente.');
    }

    public function anexo(ExameAnexo $anexo): BinaryFileResponse
    {
        $anexo->load('resultado.solicitacao');
        $this->authorize('view', $anexo->resultado);
        $disco = Storage::disk('local');
        abort_unless($disco->exists($anexo->caminho), 404);
        abort_unless(hash_equals($anexo->hash_sha256, hash_file('sha256', $disco->path($anexo->caminho))), 409, 'A integridade do anexo não pôde ser confirmada.');

        return response()->download($disco->path($anexo->caminho), $anexo->nome_original, ['Content-Type' => $anexo->mime]);
    }
}

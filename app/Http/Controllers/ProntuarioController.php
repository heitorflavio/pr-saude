<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Prontuario\RegistrarDiagnosticoAction;
use App\Actions\Prontuario\RegistrarNotaClinicaAction;
use App\Actions\Prontuario\RetificarRegistroAction;
use App\Exceptions\DominioException;
use App\Http\Requests\Prontuario\RegistrarDiagnosticoRequest;
use App\Http\Requests\Prontuario\RegistrarNotaClinicaRequest;
use App\Http\Requests\Prontuario\RetificarRegistroRequest;
use App\Models\Atendimento;
use App\Models\Cid10;
use App\Models\Paciente;
use App\Models\RegistroClinico;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Prontuario\ProntuarioConsolidadoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-08 — RF-45 a RF-52.
 *
 * Nenhum método aqui grava `registro_clinico`: toda escrita passa por Action. É o que
 * mantém RN-16 e o encadeamento de hash válidos em todos os caminhos, e não só nos que
 * alguém lembrou de cobrir.
 */
final class ProntuarioController extends Controller
{
    public function __construct(
        private readonly ProntuarioConsolidadoService $prontuario,
        private readonly AuditoriaService $auditoria,
    ) {}

    /** RF-47: a linha do tempo do atendimento. */
    public function show(Request $request, Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);

        $atendimento->load(['paciente.alergias.medicamento', 'unidade', 'classificacaoRisco']);
        $usuario = $request->user();

        return Inertia::render('Prontuario/Show', [
            'atendimento' => [
                'id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'status' => $atendimento->status->value,
                'status_rotulo' => $atendimento->status->rotulo(),
                'terminal' => $atendimento->status->ehTerminal(),
                'unidade' => $atendimento->unidade?->nome,
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
            ],
            'paciente' => [
                'user_id' => $atendimento->paciente->user_id,
                'nome' => $atendimento->paciente->nomeExibicao(),
                'idade' => $atendimento->paciente->idadeDescritiva(),
                'data_nascimento' => $atendimento->paciente->data_nascimento?->format('d/m/Y'),
            ],
            // RF-11: alergia acompanha o prontuário. Ela é a informação que muda conduta.
            'alergias' => $atendimento->paciente->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            'registros' => $this->prontuario->linhaDoTempo($atendimento, $usuario),
            'diagnosticos' => $this->prontuario->diagnosticos($atendimento),
            'tipos' => $this->prontuario->tiposDisponiveis($usuario),
            'permissoes' => [
                'registrar' => $this->prontuario->tiposDisponiveis($usuario) !== [],
                'retificar' => $usuario->can('prontuario.retificar'),
                'diagnosticar' => $usuario->can('prontuario.criar_nota_medica'),
                'marcar_sigiloso' => $usuario->can('prontuario.criar_nota_medica'),
            ],
            // doc §9.4: a integridade é informação de leitura, não relatório escondido.
            'integridade' => $this->prontuario->integridade($atendimento),
        ]);
    }

    /**
     * RF-51 — o prontuário consolidado, atravessando todos os atendimentos.
     *
     * Leitura de dado clínico de vários episódios: auditada explicitamente, porque é
     * exatamente o acesso que uma investigação de bisbilhotagem procura (doc §14.3).
     */
    public function consolidado(Request $request, Paciente $paciente): Response
    {
        $this->authorize('verContexto', $paciente);

        $this->auditoria->registrarLeitura(
            acao: 'prontuario.ler_consolidado',
            paciente: $paciente,
            entidade: 'Paciente',
            entidadeId: $paciente->user_id,
        );

        return Inertia::render('Prontuario/Consolidado', [
            'paciente' => [
                'user_id' => $paciente->user_id,
                'nome' => $paciente->nomeExibicao(),
                'idade' => $paciente->idadeDescritiva(),
                'data_nascimento' => $paciente->data_nascimento?->format('d/m/Y'),
            ],
            'alergias' => $paciente->alergias()->with('medicamento')->get()->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            'episodios' => $this->prontuario->episodios($paciente, $request->user()),
        ]);
    }

    /** RF-45, RF-47, RF-48. */
    public function store(
        RegistrarNotaClinicaRequest $request,
        Atendimento $atendimento,
        RegistrarNotaClinicaAction $registrar,
    ): RedirectResponse {
        $this->authorize('view', $atendimento);

        try {
            $registrar->execute(
                atendimento: $atendimento,
                tipo: $request->tipo(),
                autor: $request->user(),
                conteudo: $request->conteudo(),
                sigiloso: (bool) $request->boolean('sigiloso'),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['conteudo_livre' => $e->getMessage()]);
        }

        return back()->with('status', 'Registro adicionado ao prontuário.');
    }

    /** RF-50 / RN-16: retificação por adendo. O original permanece intacto. */
    public function retificar(
        RetificarRegistroRequest $request,
        RegistroClinico $registro,
        RetificarRegistroAction $retificar,
    ): RedirectResponse {
        try {
            $retificar->execute(
                original: $registro,
                autor: $request->user(),
                motivo: (string) $request->validated('motivo'),
                conteudoCorrigido: $request->conteudo(),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Adendo registrado. O registro original permanece visível, marcado como retificado.'
        );
    }

    /** RF-46. */
    public function diagnosticar(
        RegistrarDiagnosticoRequest $request,
        Atendimento $atendimento,
        RegistrarDiagnosticoAction $registrar,
    ): RedirectResponse {
        $this->authorize('view', $atendimento);

        try {
            $registrar->execute(
                atendimento: $atendimento,
                cid10Codigo: (string) $request->validated('cid10_codigo'),
                autor: $request->user(),
                natureza: (string) $request->validated('natureza'),
                principal: (bool) $request->boolean('principal'),
                observacao: $request->validated('observacao'),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['cid10_codigo' => $e->getMessage()]);
        }

        return back()->with('status', 'Diagnóstico registrado.');
    }

    /** Autocompletar do CID-10 no formulário de diagnóstico. */
    public function cid10(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q'));

        $resultados = Cid10::query()
            ->when($termo !== '', fn ($q) => $q->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', mb_strtoupper($termo).'%')
                    ->orWhere('descricao', 'like', '%'.$termo.'%');
            }))
            ->orderBy('codigo')
            ->limit(20)
            ->get(['codigo', 'descricao']);

        return response()->json($resultados);
    }

    /**
     * RF-52 — exportação do prontuário do atendimento em PDF.
     *
     * O PDF é documento, não tela: sai com a marcação de retificação, com o autor tal
     * como assinou na época e com o resultado da verificação de integridade. Um export
     * que omitisse o adendo produziria um documento que contradiz o banco.
     */
    public function exportar(Request $request, Atendimento $atendimento): HttpResponse
    {
        $this->authorize('view', $atendimento);

        $atendimento->load(['paciente.alergias.medicamento', 'unidade', 'classificacaoRisco', 'profissionalResponsavel']);
        $usuario = $request->user();

        $this->auditoria->registrarLeitura(
            acao: 'prontuario.exportar_pdf',
            paciente: $atendimento->paciente,
            atendimento: $atendimento,
            entidade: 'Atendimento',
            entidadeId: $atendimento->id,
        );

        $pdf = Pdf::loadView('prontuario.pdf', [
            'atendimento' => $atendimento,
            'paciente' => $atendimento->paciente,
            'registros' => $this->prontuario->linhaDoTempo($atendimento, $usuario),
            'diagnosticos' => $this->prontuario->diagnosticos($atendimento),
            'integridade' => $this->prontuario->integridade($atendimento),
            // Quem gerou e quando: um PDF de prontuário circula fora do sistema, e sem
            // isso não há como saber a que momento ele corresponde.
            'emitidoPor' => $usuario->profissional?->nome_completo ?? $usuario->name,
            'emitidoEm' => now(),
        ])->setPaper('a4')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="prontuario-'.$atendimento->numero.'.pdf"',
        ]);
    }
}

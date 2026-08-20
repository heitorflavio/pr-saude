<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Medicamento\PrescreverAction;
use App\Actions\Medicamento\RegistrarAdministracaoAction;
use App\Actions\Medicamento\SuspenderPrescricaoAction;
use App\Enums\ViaAdministracao;
use App\Exceptions\DominioException;
use App\Http\Requests\Medicamento\PrescreverRequest;
use App\Http\Requests\Medicamento\RegistrarAdministracaoRequest;
use App\Http\Requests\Medicamento\SuspenderPrescricaoRequest;
use App\Models\AdministracaoMedicamento;
use App\Models\Aprazamento;
use App\Models\Atendimento;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class MedicamentoController extends Controller
{
    /** RF-60: a view é a fonte da ordem e dos atrasos do checklist. */
    public function index(): Response
    {
        $this->authorize('viewAny', AdministracaoMedicamento::class);

        return Inertia::render('Medicamentos/Index', [
            'doses' => DB::table('vw_doses_pendentes')
                ->orderByDesc('atrasada')->orderBy('horario_previsto')->get()
                ->map(fn ($dose) => [
                    ...((array) $dose),
                    'horario_previsto' => date('d/m/Y H:i', strtotime($dose->horario_previsto)),
                ]),
        ]);
    }

    public function show(Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);
        $atendimento->load([
            'paciente.alergias.medicamento',
            'prescricoes.prescritor', 'prescricoes.itens.medicamento',
            'prescricoes.itens.aprazamentos.administracao.executor',
        ]);

        return Inertia::render('Medicamentos/Show', [
            'atendimento' => ['id' => $atendimento->id, 'numero' => $atendimento->numero, 'terminal' => $atendimento->status->ehTerminal()],
            'paciente' => ['id' => $atendimento->paciente_id, 'nome' => $atendimento->paciente->nomeExibicao(), 'idade' => $atendimento->paciente->idadeDescritiva()],
            'alergias' => $atendimento->paciente->alergias->map(fn ($a) => ['id' => $a->id, 'substancia' => $a->substancia, 'principio_ativo' => $a->principioAtivo(), 'gravidade' => $a->gravidade, 'reacao' => $a->reacao]),
            'medicamentos' => Medicamento::query()->where('ativo', true)->orderBy('nome_comercial')->get()->map(fn ($m) => [
                'id' => $m->id, 'nome' => $m->nome_comercial, 'principio_ativo' => $m->principio_ativo,
                'concentracao' => $m->concentracao, 'forma' => $m->forma_farmaceutica,
                'via' => $m->classe_via->value, 'unidade' => $m->unidade_dose_padrao,
                'alta_vigilancia' => $m->alta_vigilancia,
            ]),
            'vias' => array_map(fn (ViaAdministracao $v) => ['valor' => $v->value, 'rotulo' => $v->rotulo()], ViaAdministracao::cases()),
            'prescricoes' => $atendimento->prescricoes->sortByDesc('criado_em')->values()->map(fn ($p) => [
                'id' => $p->id, 'status' => $p->status, 'criado_em' => $p->criado_em?->format('d/m/Y H:i'),
                'prescritor' => $p->prescritor?->nome_completo, 'observacao' => $p->observacao,
                'itens' => $p->itens->map(fn ($i) => [
                    'id' => $i->id, 'medicamento' => $i->medicamento->nome_comercial,
                    'principio_ativo' => $i->medicamento->principio_ativo, 'dose' => $i->dose,
                    'unidade' => $i->unidade_dose, 'via' => $i->via->rotulo(),
                    'frequencia' => $i->se_necessario ? 'Se necessário' : "A cada {$i->frequencia_horas} h",
                    'status' => $i->status, 'aprazamentos' => $i->aprazamentos->map(fn ($a) => [
                        'id' => $a->id, 'previsto' => $a->horario_previsto?->format('d/m/Y H:i'),
                        'situacao' => $a->situacao, 'executor' => $a->administracao?->executor?->nome_completo,
                    ]),
                ]),
            ]),
            'permissoes' => [
                'prescrever' => request()->user()?->can('create', Prescricao::class) ?? false,
                'administrar' => request()->user()?->can('create', AdministracaoMedicamento::class) ?? false,
            ],
        ]);
    }

    public function store(PrescreverRequest $request, Atendimento $atendimento, PrescreverAction $acao): RedirectResponse
    {
        try {
            $acao->execute($atendimento, $request->user(), $request->validated('itens'), $request->validated('observacao'));
        } catch (DominioException $e) {
            return back()->withErrors(['itens' => $e->getMessage()]);
        }

        return back()->with('status', 'Prescrição registrada e aprazada.');
    }

    public function suspender(SuspenderPrescricaoRequest $request, Prescricao $prescricao, SuspenderPrescricaoAction $acao): RedirectResponse
    {
        try {
            $acao->execute($prescricao, $request->user(), $request->validated('motivo'));
        } catch (DominioException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return back()->with('status', 'Prescrição suspensa; doses pendentes foram canceladas.');
    }

    public function conferir(Aprazamento $aprazamento): Response
    {
        $this->authorize('create', AdministracaoMedicamento::class);
        $aprazamento->load('prescricaoItem.medicamento', 'prescricaoItem.prescricao.atendimento.paciente.alergias.medicamento');
        $item = $aprazamento->prescricaoItem;
        $atendimento = $item->prescricao->atendimento;

        return Inertia::render('Medicamentos/Administrar', [
            'aprazamento' => ['id' => $aprazamento->id, 'previsto' => $aprazamento->horario_previsto?->format('d/m/Y H:i'), 'situacao' => $aprazamento->situacao],
            'atendimento' => ['id' => $atendimento->id, 'numero' => $atendimento->numero],
            'paciente' => ['nome' => $atendimento->paciente->nomeExibicao(), 'nascimento' => $atendimento->paciente->data_nascimento?->format('d/m/Y'), 'idade' => $atendimento->paciente->idadeDescritiva()],
            'alergias' => $atendimento->paciente->alergias->map(fn ($a) => ['id' => $a->id, 'substancia' => $a->substancia, 'principio_ativo' => $a->principioAtivo(), 'gravidade' => $a->gravidade, 'reacao' => $a->reacao]),
            'medicamento' => [
                'nome' => $item->medicamento->nome_comercial, 'principio_ativo' => $item->medicamento->principio_ativo,
                'concentracao' => $item->medicamento->concentracao, 'forma' => $item->medicamento->forma_farmaceutica,
                'alta_vigilancia' => $item->medicamento->alta_vigilancia,
                'dose' => $item->dose, 'unidade' => $item->unidade_dose,
                'via' => $item->via->value, 'via_rotulo' => $item->via->rotulo(),
            ],
            'profissionais' => Profissional::query()->where('ativo', true)
                ->whereKeyNot(request()->user()?->profissional?->user_id)->orderBy('nome_completo')
                ->get(['user_id', 'nome_completo', 'conselho_tipo', 'conselho_numero']),
        ]);
    }

    public function administrar(RegistrarAdministracaoRequest $request, Aprazamento $aprazamento, RegistrarAdministracaoAction $acao): RedirectResponse
    {
        $executor = $request->user()->profissional;
        abort_if($executor === null, 403);
        $conferente = $request->filled('conferente_id') ? Profissional::find($request->integer('conferente_id')) : null;

        try {
            $acao->execute(
                dose: $aprazamento,
                executor: $executor,
                doseAdministrada: $request->filled('dose_administrada') ? $request->float('dose_administrada') : null,
                via: $request->validated('via'),
                resultado: $request->validated('resultado'),
                motivoNaoAdministracao: $request->validated('motivo_nao_administracao'),
                conferente: $conferente,
                justificativaAlergia: $request->validated('justificativa_alergia'),
                observacao: $request->observacaoRegistrada(),
            );
        } catch (DominioException $e) {
            return back()->withErrors(['administracao' => $e->getMessage()]);
        }

        return redirect()->route('medicamentos.show', $aprazamento->prescricaoItem->prescricao->atendimento_id)
            ->with('status', 'Administração registrada.');
    }
}

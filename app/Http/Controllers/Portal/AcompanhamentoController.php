<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\SituacaoExame;
use App\Http\Controllers\Controller;
use App\Models\AdministracaoMedicamento;
use App\Models\Atendimento;
use App\Models\ExameSolicitacao;
use App\Models\RegistroClinico;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AcompanhamentoController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $paciente = $request->user('paciente')->paciente;
        $atendimento = Atendimento::query()->whereNotIn('status', ['FINALIZADO', 'CANCELADO'])
            ->with('classificacaoRisco')->latest('admitido_em')->first();
        $fila = $atendimento ? DB::table('vw_fila_ordenada')
            ->where('atendimento_id', $atendimento->id)
            ->where('paciente_id', $paciente->user_id)->first() : null;

        $this->auditoria->registrarLeitura('portal.acompanhamento', $paciente, $atendimento);

        return Inertia::render('Portal/Acompanhamento', [
            'paciente' => ['nome' => $paciente->nomeExibicao()],
            'atendimento' => $atendimento ? [
                'uuid' => $atendimento->uuid,
                'numero' => $atendimento->numero,
                'status' => $atendimento->status->rotuloPaciente(),
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                // Doc §12.3: tempo decorrido, nunca previsão.
                'tempo_decorrido_minutos' => (int) $atendimento->admitido_em?->diffInMinutes(now()),
                'posicao_fila' => $fila?->posicao,
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
            ] : null,
            'resumo' => [
                'medicamentos' => AdministracaoMedicamento::query()->where('resultado', 'ADMINISTRADA')->count(),
                'exames' => ExameSolicitacao::query()->count(),
                'atendimentos' => Atendimento::query()->count(),
            ],
        ]);
    }

    public function atendimento(Request $request, string $uuid): Response
    {
        // RN-26: o global scope transforma UUID de outro paciente em 404.
        $atendimento = Atendimento::query()->where('uuid', $uuid)->firstOrFail();
        $registros = RegistroClinico::query()->where('atendimento_id', $atendimento->id)
            ->where('sigiloso', false)->orderBy('criado_em')->get();

        $this->auditoria->registrarLeitura('portal.atendimento', $request->user('paciente')->paciente, $atendimento);

        return Inertia::render('Portal/Atendimento', [
            'atendimento' => [
                'numero' => $atendimento->numero, 'status' => $atendimento->status->rotuloPaciente(),
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                'finalizado_em' => $atendimento->finalizado_em?->format('d/m/Y H:i'),
                'desfecho' => $this->rotuloDesfecho($atendimento->desfecho),
            ],
            // RF-73/RF-77: sigilosos são omitidos sem contador ou placeholder.
            'evolucao' => $registros->map(fn ($r) => [
                'id' => $r->id, 'quando' => $r->criado_em?->format('d/m/Y H:i'),
                'tipo' => $r->tipo->rotulo(),
                'texto' => $r->conteudo_livre ?: collect([$r->subjetivo, $r->objetivo, $r->avaliacao, $r->plano])->filter()->implode("\n\n"),
            ]),
        ]);
    }

    public function medicamentos(Request $request): Response
    {
        $paciente = $request->user('paciente')->paciente;
        $registros = AdministracaoMedicamento::query()
            ->where('resultado', 'ADMINISTRADA')
            ->with('prescricaoItem.medicamento')->latest('administrado_em')->get();
        $this->auditoria->registrarLeitura('portal.medicamentos', $paciente);

        return Inertia::render('Portal/Medicamentos', [
            'medicamentos' => $registros->map(fn ($a) => [
                'id' => $a->id,
                'nome' => $a->prescricaoItem->medicamento->nome_comercial,
                'principio_ativo' => $a->prescricaoItem->medicamento->principio_ativo,
                'dose' => $a->dose_administrada,
                'unidade' => $a->unidade_dose,
                'via' => $a->via?->rotuloPaciente(),
                'quando' => $a->administrado_em?->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function exames(Request $request): Response
    {
        $paciente = $request->user('paciente')->paciente;
        $solicitacoes = ExameSolicitacao::query()->with('exame', 'resultado.itens')->latest('solicitado_em')->get();
        $this->auditoria->registrarLeitura('portal.exames', $paciente);

        return Inertia::render('Portal/Exames', [
            'exames' => $solicitacoes->map(function ($s) {
                $visivel = $s->situacao === SituacaoExame::Liberado
                    && ($s->resultado?->visivel_ao_paciente ?? false);

                return [
                    'id' => $s->id, 'nome' => $s->exame->nome,
                    'solicitado_em' => $s->solicitado_em?->format('d/m/Y H:i'),
                    'situacao' => $s->situacao->rotuloPaciente(),
                    'resultado' => $visivel ? [
                        'laudo' => $s->resultado->laudo,
                        'conclusao' => $s->resultado->conclusao,
                        'itens' => $s->resultado->itens->map(fn ($i) => [
                            'analito' => $i->analito, 'valor' => $i->valor,
                            'unidade' => $i->unidade, 'referencia' => $i->referencia_texto
                                ?: trim("{$i->referencia_min} – {$i->referencia_max}", ' –'),
                        ]),
                    ] : null,
                ];
            }),
        ]);
    }

    public function historico(Request $request): Response
    {
        $paciente = $request->user('paciente')->paciente;
        $atendimentos = Atendimento::query()->latest('admitido_em')->get();
        $this->auditoria->registrarLeitura('portal.historico', $paciente);

        return Inertia::render('Portal/Historico', [
            'atendimentos' => $atendimentos->map(fn ($a) => [
                'uuid' => $a->uuid, 'numero' => $a->numero,
                'status' => $a->status->rotuloPaciente(),
                'admitido_em' => $a->admitido_em?->format('d/m/Y H:i'),
                'finalizado_em' => $a->finalizado_em?->format('d/m/Y H:i'),
                'desfecho' => $this->rotuloDesfecho($a->desfecho),
            ]),
        ]);
    }

    private function rotuloDesfecho(?string $desfecho): ?string
    {
        return match ($desfecho) {
            'ALTA' => 'Alta para casa', 'INTERNACAO' => 'Internação',
            'TRANSFERENCIA' => 'Transferência', 'EVASAO' => 'Saída antes da alta',
            'OBITO' => 'Óbito', default => $desfecho,
        };
    }
}

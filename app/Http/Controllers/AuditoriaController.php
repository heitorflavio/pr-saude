<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditoriaController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('auditoria.ler'), 403);

        $filtros = $request->validate([
            'paciente_id' => ['nullable', 'integer'],
            'acao' => ['nullable', 'string', 'max:80'],
        ]);
        $desde = now()->subDays(90);

        $logs = AuditoriaLog::query()
            ->with(['usuario:id,name', 'paciente:user_id,nome_completo,nome_social'])
            ->where('criado_em', '>=', $desde)
            ->when($filtros['paciente_id'] ?? null, fn ($q, $id) => $q->where('paciente_id', $id))
            ->when($filtros['acao'] ?? null, fn ($q, $acao) => $q->where('acao', 'like', "%{$acao}%"))
            ->latest('criado_em')->paginate(30)->withQueryString();

        $paciente = isset($filtros['paciente_id'])
            ? Paciente::query()->find($filtros['paciente_id'])
            : null;

        return Inertia::render('Auditoria/Index', [
            'logs' => $logs->through(fn (AuditoriaLog $log) => [
                'id' => $log->id,
                'quando' => $log->criado_em?->format('d/m/Y H:i:s'),
                'usuario' => $log->usuario?->name ?? 'Sistema',
                'perfis' => $log->perfis_no_momento,
                'acao' => $log->acao,
                'paciente' => $log->paciente?->nomeExibicao(),
                'paciente_id' => $log->paciente_id,
                'justificativa' => $log->justificativa,
                'ip' => $log->ip,
            ]),
            'filtros' => $filtros,
            'pacienteSelecionado' => $paciente ? ['id' => $paciente->user_id, 'nome' => $paciente->nomeExibicao()] : null,
            'desde' => $desde->format('d/m/Y'),
        ]);
    }
}

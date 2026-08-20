<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Unidade;
use App\Services\Indicadores\IndicadoresService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndicadoresController extends Controller
{
    public function __construct(private readonly IndicadoresService $indicadores) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('auditoria.ler'), 403);

        $dados = $request->validate([
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'unidade_id' => ['nullable', 'integer', 'exists:unidade,id'],
        ]);
        $inicio = CarbonImmutable::parse($dados['inicio'] ?? now()->subDays(30)->toDateString())->startOfDay();
        $fim = CarbonImmutable::parse($dados['fim'] ?? now()->toDateString())->endOfDay();
        abort_if($inicio->diffInDays($fim) > 366, 422, 'O período máximo é de 366 dias.');

        return Inertia::render('Indicadores/Index', [
            'indicadores' => $this->indicadores->calcular($inicio, $fim, isset($dados['unidade_id']) ? (int) $dados['unidade_id'] : null),
            'unidades' => Unidade::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
}

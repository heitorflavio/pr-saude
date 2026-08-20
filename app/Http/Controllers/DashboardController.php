<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Dashboard\PainelInicialService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela inicial da equipe: o estado do plantão em uma olhada.
 *
 * Cada bloco é montado **só se o usuário tem a permission correspondente** — não é o
 * componente Vue que esconde o cartão de doses da recepcionista, é o servidor que não
 * envia o dado. Filtrar no cliente deixaria a contagem de doses no payload de quem não
 * pode vê-la, e um payload é tão visível quanto a tela (doc §14.2).
 *
 * Nenhuma decisão clínica acontece aqui: o painel só lê e agrega. Os números vêm das
 * views, e cada link leva à tela que tem a autorização contextual de verdade.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly PainelInicialService $painel) {}

    /**
     * A prop `painel` é a única que o `usePoll(15000, { only: ['painel'] })` recarrega
     * (mesmo padrão da fila, RF-34 / RNF-03): o resto da página não trafega de novo a
     * cada ciclo.
     */
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $profissional = $usuario->profissional;

        $pode = [
            'fila' => $usuario->can('fila.ler'),
            'atendimentos' => $usuario->can('atendimento.ler_status'),
            'doses' => $usuario->can('medicamento.ler_administracao') || $usuario->can('prescricao.ler'),
            'exames' => $usuario->can('exame.ler_solicitacao'),
            'pacientes' => $usuario->can('paciente.ler'),
            'criar_paciente' => $usuario->can('paciente.criar'),
            'indicadores' => $usuario->can('auditoria.ler'),
        ];

        return Inertia::render('Dashboard', [
            'painel' => fn (): array => array_filter([
                'fila' => $pode['fila'] ? $this->painel->fila() : null,
                // Só quem tem fila própria vê "minha fila" — o auditor e a recepção não
                // recebem pacientes, e um cartão vazio para eles é ruído.
                'minha_fila' => $pode['fila'] && $profissional !== null
                    ? $this->painel->minhaFila($profissional->user_id)
                    : null,
                'atendimentos' => $pode['atendimentos'] ? $this->painel->atendimentos() : null,
                'doses' => $pode['doses'] ? $this->painel->doses() : null,
                'exames' => $pode['exames'] ? $this->painel->exames() : null,
            ], fn (?array $bloco) => $bloco !== null),
            'contexto' => [
                'nome' => $profissional?->nome_completo ?? $usuario->name,
                'categoria' => $profissional?->categoria,
                'unidade' => $profissional?->unidade?->nome,
                'pode' => $pode,
            ],
        ]);
    }
}

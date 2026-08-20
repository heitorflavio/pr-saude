<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Paciente\CadastrarPacienteAction;
use App\Actions\Paciente\RegularizarIdentificacaoAction;
use App\Exceptions\PacienteJaCadastradoException;
use App\Exceptions\RegularizacaoInvalidaException;
use App\Http\Requests\Paciente\CadastrarPacienteRequest;
use App\Http\Requests\Paciente\RegularizarIdentificacaoRequest;
use App\Models\Paciente;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nenhuma escrita de dado clínico acontece aqui: o controller valida, autoriza e delega
 * às Actions. É a regra que sustenta a camada de domínio.
 */
final class PacienteController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** RF-09: busca por nome, CPF, CNS, data de nascimento e token de pulseira. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Paciente::class);

        $termo = $request->string('busca')->toString();

        $pacientes = Paciente::query()
            ->busca($termo)
            ->withCount('alergias')
            ->orderBy('nome_completo')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Paciente $paciente) => [
                'user_id' => $paciente->user_id,
                'nome' => $paciente->nomeExibicao(),
                'nome_completo' => $paciente->nome_completo,
                // D-01: derivada, nunca armazenada.
                'idade' => $paciente->idadeDescritiva(),
                'data_nascimento' => $paciente->data_nascimento?->format('d/m/Y'),
                'cpf' => $paciente->cpf,
                'identificacao_provisoria' => $paciente->identificacao_provisoria,
                'codigo_provisorio' => $paciente->codigo_provisorio,
                'alergias_count' => $paciente->alergias_count,
            ]);

        // doc §14.3: consulta a lista de pacientes é leitura de dado pessoal e é
        // auditada como tal -- mesmo sem abrir nenhuma ficha.
        if ($termo !== '') {
            $this->auditoria->registrarLeitura(acao: 'paciente.buscar');
        }

        return Inertia::render('Pacientes/Index', [
            'pacientes' => $pacientes,
            'filtros' => ['busca' => $termo],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Paciente::class);

        return Inertia::render('Pacientes/Create');
    }

    public function store(CadastrarPacienteRequest $request, CadastrarPacienteAction $cadastrar): RedirectResponse
    {
        try {
            $paciente = $cadastrar->execute($request->validated(), $request->user());
        } catch (PacienteJaCadastradoException $e) {
            // A1: em vez de duplicar, leva ao cadastro existente. Duplicar prontuário
            // num pronto-socorro esconde a alergia registrada no outro cadastro.
            return redirect()
                ->route('pacientes.show', $e->paciente->user_id)
                ->with('alerta', $e->getMessage().' Abra um novo atendimento a partir desta ficha.');
        }

        return redirect()
            ->route('pacientes.show', $paciente->user_id)
            ->with('status', 'Paciente cadastrado. A pulseira já pode ser impressa.');
    }

    public function show(Request $request, Paciente $paciente): Response
    {
        // Ficha cadastral, nao prontuario: RN-28 protege o prontuario, e a recepcionista
        // precisa desta tela logo apos cadastrar (UC-01, passo 11). Ver PacientePolicy.
        $this->authorize('verFichaCadastral', $paciente);

        $paciente->load(['alergias.medicamento', 'condicoes.cid10', 'user']);

        // A auditoria de leitura desta rota é feita pelo middleware `auditar:paciente.ler`
        // (routes/pacientes.php). Registrar aqui também produziria dois eventos para o
        // mesmo acesso e inflaria a resposta de "quem acessou os dados deste paciente?".

        return Inertia::render('Pacientes/Show', [
            'paciente' => [
                'user_id' => $paciente->user_id,
                'nome' => $paciente->nomeExibicao(),
                'nome_completo' => $paciente->nome_completo,
                'nome_social' => $paciente->nome_social,
                'cpf' => $paciente->cpf,
                'cns' => $paciente->cns,
                'data_nascimento' => $paciente->data_nascimento?->format('d/m/Y'),
                'idade' => $paciente->idadeDescritiva(),
                'sexo' => $paciente->sexo,
                'nome_mae' => $paciente->nome_mae,
                'telefone' => $paciente->telefone,
                'contato_emergencia_nome' => $paciente->contato_emergencia_nome,
                'contato_emergencia_telefone' => $paciente->contato_emergencia_telefone,
                'municipio' => $paciente->municipio,
                'uf' => $paciente->uf,
                'identificacao_provisoria' => $paciente->identificacao_provisoria,
                'codigo_provisorio' => $paciente->codigo_provisorio,
                'login' => $paciente->identificacao_provisoria ? $paciente->codigo_provisorio : $paciente->cpf,
                'senha_provisoria' => $paciente->user?->senha_provisoria,
            ],
            // RF-11: alergias em destaque. Fazem parte do mínimo vital (doc §13.5).
            'alergias' => $paciente->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            'condicoes' => $paciente->condicoes->map(fn ($condicao) => [
                'id' => $condicao->id,
                'descricao' => $condicao->descricao,
                'cid10_codigo' => $condicao->cid10_codigo,
                'desde' => $condicao->desde?->format('d/m/Y'),
            ]),
            'podeRegularizar' => $paciente->identificacao_provisoria
                && ($request->user()?->can('paciente.atualizar') ?? false),
        ]);
    }

    /** RN-30: vincula o CPF real preservando todo o histórico. */
    public function regularizar(
        RegularizarIdentificacaoRequest $request,
        Paciente $paciente,
        RegularizarIdentificacaoAction $regularizar,
    ): RedirectResponse {
        try {
            $regularizar->execute(
                paciente: $paciente,
                cpf: $request->validated('cpf'),
                autor: $request->user(),
                nomeCompleto: $request->validated('nome_completo'),
                cns: $request->validated('cns'),
            );
        } catch (PacienteJaCadastradoException $e) {
            return back()->with(
                'alerta',
                $e->getMessage().' A fusão de prontuários é decisão assistencial e não é feita automaticamente.'
            );
        } catch (RegularizacaoInvalidaException $e) {
            return back()->withErrors(['cpf' => $e->getMessage()]);
        }

        return redirect()
            ->route('pacientes.show', $paciente->user_id)
            ->with('status', 'Identificação regularizada. O histórico do paciente foi preservado.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pulseira\ImprimirPulseiraAction;
use App\Contracts\GeradorTokenPulseira;
use App\Http\Requests\Pulseira\ImprimirPulseiraRequest;
use App\Models\Paciente;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PulseiraController extends Controller
{
    public function __construct(
        private readonly GeradorTokenPulseira $tokens,
        private readonly AuditoriaService $auditoria,
    ) {}

    /** RF-15, RF-16: imprime ou reimprime — sempre com o mesmo token (RN-03). */
    public function imprimir(
        ImprimirPulseiraRequest $request,
        Paciente $paciente,
        ImprimirPulseiraAction $imprimir,
    ): Response {
        $resultado = $imprimir->execute(
            paciente: $paciente,
            operador: $request->user(),
            atendimento: $paciente->atendimentoAtivo(),
            motivo: $request->validated('motivo', 'PRIMEIRA'),
            observacao: $request->validated('observacao'),
        );

        return response($resultado['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pulseira-'.$paciente->user_id.'.pdf"',
        ]);
    }

    /**
     * `GET /p/{token}` — o fluxograma da doc §8.3, na ordem exata.
     *
     * A ordem dos passos **é** o controle de segurança. Trocar 2 por 3 transformaria a
     * rota num oráculo de enumeração: quem quisesse descobrir quais tokens existem
     * compararia "redireciona" contra "404" e teria a resposta sem nunca autenticar.
     */
    public function resolver(Request $request, string $token): InertiaResponse|\Symfony\Component\HttpFoundation\Response
    {
        // 1. Validação barata, antes de tocar o banco. Uma varredura de 100.000
        //    tentativas não vira 100.000 SELECTs -- é mitigação de DoS por custo.
        if (! $this->tokens->valido($token)) {
            // Formato correto com checksum inválido é evidência de manipulação
            // deliberada, não de erro de digitação: merece registro distinto.
            $this->auditoria->registrar(
                acao: 'pulseira.token_invalido',
                justificativa: 'Tentativa de leitura com checksum inválido.',
            );

            abort(404, 'Pulseira não reconhecida.');
        }

        $usuario = $request->user() ?? $request->user('paciente');

        // 2. Sem sessão, ninguém recebe dado algum -- nem a confirmação de que o token
        //    existe. Redirecionar ANTES da consulta é o que impede o uso como oráculo:
        //    token existente e token inexistente produzem a mesma resposta.
        if ($usuario === null) {
            // D-61: a leitura não guarda mais nada na sessão -- o primeiro acesso não
            // exige a posse da pulseira. O redirecionamento continua sendo o que impede
            // o uso da rota como oráculo de enumeração.
            return redirect()->route('portal.login')
                ->with('status', 'Pulseira reconhecida. Informe seu CPF e sua senha.');
        }

        $paciente = Paciente::where('token_pulseira', $token)->firstOrFail();

        // 3. RN-26: o paciente acessa exclusivamente o próprio dado.
        if ($usuario->ehPaciente()) {
            abort_unless($usuario->id === $paciente->user_id, 403, 'Acesso negado.');

            return redirect()->route('portal.acompanhamento');
        }

        // 4. Profissional. A Policy decide entre contexto completo e mínimo vital --
        //    e o caminho do mínimo vital NÃO é 403.
        $temVinculo = Gate::forUser($usuario)->allows('verContexto', $paciente);
        $temMinimoVital = Gate::forUser($usuario)->allows('verMinimoVital', $paciente);

        abort_unless($temVinculo || $temMinimoVital, 403, 'Acesso negado.');

        $this->auditoria->registrarLeitura(
            acao: $temVinculo ? 'pulseira.leitura_qr' : 'pulseira.leitura_qr.minimo_vital',
            paciente: $paciente,
            atendimento: $paciente->atendimentoAtivo(),
            entidade: 'Paciente',
            entidadeId: $paciente->user_id,
        );

        $paciente->load(['alergias.medicamento']);
        $atendimento = $paciente->atendimentoAtivo();

        return Inertia::render('Pulseira/Contexto', [
            /*
             * Mesmo SEM vínculo assistencial o profissional recebe nome e ALERGIAS
             * (doc §13.5). Se um paciente entra em parada no corredor e o médico que
             * passa não é o responsável, negar a lista de alergias em nome do sigilo
             * seria uma decisão de projeto com potencial letal. O acesso mínimo é
             * amplo; o resto exige justificativa registrada.
             */
            'paciente' => [
                'user_id' => $paciente->user_id,
                'nome' => $paciente->nomeExibicao(),
                'data_nascimento' => $paciente->data_nascimento?->format('d/m/Y'),
                'idade' => $paciente->idadeDescritiva(),
                'sexo' => $paciente->sexo,
            ],
            'alergias' => $paciente->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'substancia' => $alergia->substancia,
                'principio_ativo' => $alergia->principioAtivo(),
                'gravidade' => $alergia->gravidade,
                'reacao' => $alergia->reacao,
            ]),
            'temVinculo' => $temVinculo,
            // Só o contexto completo revela o episódio.
            'atendimento' => $temVinculo && $atendimento ? [
                'id' => $atendimento->id,
                'numero' => $atendimento->numero,
                'status' => $atendimento->status->rotulo(),
                'admitido_em' => $atendimento->admitido_em?->format('d/m/Y H:i'),
                'prioridade' => $atendimento->classificacaoRisco?->nome,
                'prioridade_cor' => $atendimento->classificacaoRisco?->cor_nome->value,
            ] : null,
        ]);
    }
}

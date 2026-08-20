<?php

declare(strict_types=1);

namespace App\Actions\Exame;

use App\Enums\SituacaoExame;
use App\Events\ResultadoExameRegistrado;
use App\Events\ValorCriticoDetectado;
use App\Exceptions\ExameInvalidoException;
use App\Exceptions\OperadorSemRegistroProfissionalException;
use App\Models\ExameResultado;
use App\Models\ExameSolicitacao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Exame\AvaliadorResultadoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class RegistrarResultadoAction
{
    public function __construct(
        private readonly AvaliadorResultadoService $avaliador,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itens
     * @param  list<UploadedFile>  $anexos
     */
    public function execute(
        ExameSolicitacao $solicitacao,
        User $autor,
        array $itens = [],
        ?string $laudo = null,
        ?string $conclusao = null,
        array $anexos = [],
    ): ExameResultado {
        if ($solicitacao->situacao !== SituacaoExame::EmExecucao) {
            throw ExameInvalidoException::transicao($solicitacao->situacao, SituacaoExame::Concluido);
        }
        if ($solicitacao->resultado()->exists()) {
            throw ExameInvalidoException::resultadoExistente();
        }
        if ($itens === [] && blank($laudo) && blank($conclusao) && $anexos === []) {
            throw new ExameInvalidoException('Informe analitos, laudo, conclusão ou anexo.');
        }

        $profissional = $autor->profissional;
        if ($profissional === null) {
            throw OperadorSemRegistroProfissionalException::paraAcao('registrar resultado de exame');
        }

        $arquivosGravados = [];

        try {
            return DB::transaction(function () use ($solicitacao, $autor, $profissional, $itens, $laudo, $conclusao, $anexos, &$arquivosGravados) {
                $classificados = collect($itens)->map(function (array $item) {
                    $min = isset($item['referencia_min']) && $item['referencia_min'] !== '' ? (float) $item['referencia_min'] : null;
                    $max = isset($item['referencia_max']) && $item['referencia_max'] !== '' ? (float) $item['referencia_max'] : null;

                    return [
                        'analito' => trim((string) $item['analito']),
                        'valor' => (string) $item['valor'],
                        'unidade' => $item['unidade'] ?? null,
                        // Doc §11.3: snapshot da referência vigente no resultado.
                        'referencia_min' => $min,
                        'referencia_max' => $max,
                        'referencia_texto' => $item['referencia_texto'] ?? null,
                        'sinalizacao' => $this->avaliador->sinalizar(
                            (string) $item['analito'], $item['valor'], $min, $max, $item['unidade'] ?? null
                        ),
                    ];
                });
                $critico = $classificados->contains('sinalizacao', 'CRITICO');

                $resultado = ExameResultado::create([
                    'exame_solicitacao_id' => $solicitacao->id,
                    'laudo' => filled($laudo) ? trim((string) $laudo) : null,
                    'conclusao' => filled($conclusao) ? trim((string) $conclusao) : null,
                    'possui_valor_critico' => $critico,
                    'executado_por' => $profissional->user_id,
                    'executado_em' => now(),
                    'visivel_ao_paciente' => false,
                    'criado_em' => now(),
                ]);
                $resultado->itens()->createMany($classificados->all());

                foreach ($anexos as $anexo) {
                    $nome = Str::uuid().'.'.($anexo->guessExtension() ?: 'bin');
                    $caminho = "exames/{$resultado->id}/{$nome}";
                    $conteudo = $anexo->getContent();
                    Storage::disk('local')->put($caminho, $conteudo);
                    $arquivosGravados[] = $caminho;

                    $resultado->anexos()->create([
                        'nome_original' => $anexo->getClientOriginalName(),
                        'caminho' => $caminho,
                        'mime' => $anexo->getMimeType() ?: 'application/octet-stream',
                        'tamanho_bytes' => strlen($conteudo),
                        'hash_sha256' => hash('sha256', $conteudo),
                        'enviado_por' => $profissional->user_id,
                        'criado_em' => now(),
                    ]);
                }

                $solicitacao->update(['situacao' => SituacaoExame::Concluido]);
                $this->auditoria->registrar(
                    acao: 'exame.registrar_resultado', atendimento: $solicitacao->atendimento,
                    entidade: 'ExameResultado', entidadeId: $resultado->id,
                    depois: ['itens' => $classificados->count(), 'possui_valor_critico' => $critico, 'anexos' => count($anexos)],
                    usuario: $autor,
                );
                ResultadoExameRegistrado::dispatch($resultado);
                if ($critico) {
                    ValorCriticoDetectado::dispatch($resultado);
                }

                return $resultado->load('itens', 'anexos');
            });
        } catch (Throwable $e) {
            foreach ($arquivosGravados as $caminho) {
                Storage::disk('local')->delete($caminho);
            }
            throw $e;
        }
    }
}

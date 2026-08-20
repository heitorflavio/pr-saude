<?php

declare(strict_types=1);

namespace App\Actions\Medicamento;

use App\Events\PrescricaoSuspensa;
use App\Exceptions\PrescricaoNaoVigenteException;
use App\Models\Prescricao;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class SuspenderPrescricaoAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(Prescricao $prescricao, User $autor, string $motivo): Prescricao
    {
        if ($prescricao->status !== 'VIGENTE') {
            throw PrescricaoNaoVigenteException::ordemInvalida();
        }

        if (blank($motivo)) {
            throw new PrescricaoNaoVigenteException('A suspensão exige justificativa.');
        }

        $profissional = $autor->profissional;
        if ($profissional === null) {
            throw new PrescricaoNaoVigenteException('A suspensão exige profissional identificado.');
        }

        return DB::transaction(function () use ($prescricao, $autor, $profissional, $motivo) {
            $prescricao->update([
                'status' => 'SUSPENSA',
                'suspensa_por' => $profissional->user_id,
                'suspensa_em' => now(),
                'motivo_suspensao' => trim($motivo),
            ]);
            $prescricao->itens()->where('status', 'VIGENTE')->update(['status' => 'SUSPENSO']);
            $prescricao->itens()->each(fn ($item) => $item->aprazamentos()
                ->where('situacao', 'PENDENTE')->update(['situacao' => 'SUSPENSA']));

            $this->auditoria->registrar(
                acao: 'prescricao.suspender',
                atendimento: $prescricao->atendimento,
                entidade: 'Prescricao',
                entidadeId: $prescricao->id,
                antes: ['status' => 'VIGENTE'],
                depois: ['status' => 'SUSPENSA'],
                justificativa: trim($motivo),
                usuario: $autor,
            );
            PrescricaoSuspensa::dispatch($prescricao);

            return $prescricao->fresh();
        });
    }
}

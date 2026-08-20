<?php

declare(strict_types=1);

namespace App\Services\Medicamento;

use App\Models\PrescricaoItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** RF-56 — horários operacionais redondos, não o minuto em que o médico clicou. */
final class AprazamentoService
{
    public function gerar(PrescricaoItem $item, ?CarbonInterface $inicio = null): int
    {
        // doc §10.5: SOS/PRN acontece por necessidade clínica e não tem horário previsto.
        if ($item->se_necessario) {
            return 0;
        }

        $frequencia = (int) $item->frequencia_horas;
        $duracao = (int) ($item->duracao_horas ?? 24);

        if ($frequencia <= 0 || $duracao <= 0) {
            return 0;
        }

        $referencia = CarbonImmutable::instance($inicio ?? now());
        $quantidade = max(1, (int) floor($duracao / $frequencia));
        $ancora = $this->proximoHorarioRedondo($referencia, $frequencia);
        $registros = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $registros[] = [
                'prescricao_item_id' => $item->id,
                'sequencia' => $i + 1,
                'horario_previsto' => $ancora->addHours($i * $frequencia),
                'situacao' => 'PENDENTE',
            ];
        }

        $item->aprazamentos()->insert($registros);

        return count($registros);
    }

    public function proximoHorarioRedondo(CarbonImmutable $referencia, int $frequenciaHoras): CarbonImmutable
    {
        if ($frequenciaHoras < 6) {
            return $referencia->startOfMinute();
        }

        $grade = match (true) {
            $frequenciaHoras >= 24 => [8],
            $frequenciaHoras >= 12 => [8, 20],
            $frequenciaHoras >= 8 => [6, 14, 22],
            default => [0, 6, 12, 18],
        };

        foreach ($grade as $hora) {
            $candidato = $referencia->setTime($hora, 0, 0);
            if ($candidato->greaterThan($referencia)) {
                return $candidato;
            }
        }

        return $referencia->addDay()->setTime($grade[0], 0, 0);
    }
}

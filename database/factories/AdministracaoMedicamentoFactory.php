<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdministracaoMedicamento;
use App\Models\Atendimento;
use App\Models\PrescricaoItem;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministracaoMedicamento>
 */
class AdministracaoMedicamentoFactory extends Factory
{
    protected $model = AdministracaoMedicamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'aprazamento_id' => null,
            'prescricao_item_id' => PrescricaoItem::factory(),
            'atendimento_id' => Atendimento::factory(),
            // ck_adm_dose: resultado ADMINISTRADA exige dose informada.
            'dose_administrada' => fake()->randomFloat(3, 0.5, 1000),
            'unidade_dose' => 'mg',
            'via' => 'IV',
            // RN-29: horário de servidor, nunca do cliente.
            'administrado_em' => now(),
            'administrado_por' => Profissional::factory()->tecnicoEnfermagem(),
            'resultado' => 'ADMINISTRADA',
            'alerta_alergia_sobreposto' => false,
        ];
    }

    /** RF-58: não administrar é evento clínico e exige motivo (ck_adm_motivo). */
    public function naoAdministrada(string $motivo = 'RECUSA_PACIENTE'): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado' => 'NAO_ADMINISTRADA',
            'motivo_nao_administracao' => $motivo,
            'dose_administrada' => null,
        ]);
    }

    /** RN-21: sobrepor alerta de alergia exige justificativa (ck_adm_justificativa). */
    public function comAlertaAlergiaSobreposto(string $justificativa = 'Risco/benefício avaliado pelo prescritor.'): static
    {
        return $this->state(fn (array $attributes) => [
            'alerta_alergia_sobreposto' => true,
            'justificativa' => $justificativa,
        ]);
    }

    /** RN-22: dupla checagem de alta vigilância, por profissional distinto. */
    public function comConferente(Profissional $conferente): static
    {
        return $this->state(fn (array $attributes) => ['checado_por' => $conferente->user_id]);
    }
}

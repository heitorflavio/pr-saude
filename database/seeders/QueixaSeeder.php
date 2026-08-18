<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Queixa;
use Illuminate\Database\Seeder;

/**
 * Queixas de entrada mais frequentes em pronto-socorro, cada uma associada ao
 * fluxograma correspondente do Protocolo de Manchester. O fluxograma é o que orienta o
 * enfermeiro de triagem a chegar na cor certa -- a queixa sozinha não classifica.
 */
class QueixaSeeder extends Seeder
{
    public function run(): void
    {
        $queixas = [
            ['descricao' => 'Dor torácica', 'fluxograma_manchester' => 'Dor torácica'],
            ['descricao' => 'Falta de ar', 'fluxograma_manchester' => 'Dispneia em adulto'],
            ['descricao' => 'Dor abdominal', 'fluxograma_manchester' => 'Dor abdominal em adulto'],
            ['descricao' => 'Febre', 'fluxograma_manchester' => 'Indisposição em adulto'],
            ['descricao' => 'Cefaleia', 'fluxograma_manchester' => 'Cefaleia'],
            ['descricao' => 'Vômitos', 'fluxograma_manchester' => 'Vômitos'],
            ['descricao' => 'Diarreia', 'fluxograma_manchester' => 'Diarreia e vômitos'],
            ['descricao' => 'Tontura ou vertigem', 'fluxograma_manchester' => 'Tontura'],
            ['descricao' => 'Desmaio', 'fluxograma_manchester' => 'Colapso em adulto'],
            ['descricao' => 'Convulsão', 'fluxograma_manchester' => 'Convulsões'],
            ['descricao' => 'Trauma de crânio', 'fluxograma_manchester' => 'Traumatismo craniano'],
            ['descricao' => 'Trauma de extremidade', 'fluxograma_manchester' => 'Problemas em extremidades'],
            ['descricao' => 'Queda', 'fluxograma_manchester' => 'Quedas'],
            ['descricao' => 'Ferimento cortocontuso', 'fluxograma_manchester' => 'Feridas'],
            ['descricao' => 'Queimadura', 'fluxograma_manchester' => 'Queimaduras'],
            ['descricao' => 'Dor lombar', 'fluxograma_manchester' => 'Dor lombar'],
            ['descricao' => 'Dor de garganta', 'fluxograma_manchester' => 'Dor de garganta'],
            ['descricao' => 'Dor de ouvido', 'fluxograma_manchester' => 'Problemas em ouvidos'],
            ['descricao' => 'Dor ao urinar', 'fluxograma_manchester' => 'Problemas urinários'],
            ['descricao' => 'Reação alérgica', 'fluxograma_manchester' => 'Alergia'],
            ['descricao' => 'Palpitações', 'fluxograma_manchester' => 'Palpitações'],
            ['descricao' => 'Hipertensão arterial', 'fluxograma_manchester' => 'Indisposição em adulto'],
            ['descricao' => 'Hiperglicemia ou hipoglicemia', 'fluxograma_manchester' => 'Diabetes'],
            ['descricao' => 'Sangramento nasal', 'fluxograma_manchester' => 'Epistaxe'],
            ['descricao' => 'Sangramento digestivo', 'fluxograma_manchester' => 'Hemorragia gastrointestinal'],
            ['descricao' => 'Sangramento vaginal', 'fluxograma_manchester' => 'Sangramento vaginal'],
            ['descricao' => 'Corpo estranho', 'fluxograma_manchester' => 'Corpo estranho'],
            ['descricao' => 'Mordedura ou picada de animal', 'fluxograma_manchester' => 'Mordeduras e picadas'],
            ['descricao' => 'Intoxicação exógena', 'fluxograma_manchester' => 'Overdose e envenenamento'],
            ['descricao' => 'Agitação psicomotora', 'fluxograma_manchester' => 'Comportamento estranho'],
            ['descricao' => 'Fraqueza em um lado do corpo', 'fluxograma_manchester' => 'AVC'],
            ['descricao' => 'Problema ocular', 'fluxograma_manchester' => 'Problemas oculares'],
        ];

        foreach ($queixas as $queixa) {
            Queixa::updateOrCreate(
                ['descricao' => $queixa['descricao']],
                $queixa + ['ativo' => true]
            );
        }
    }
}

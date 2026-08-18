<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Medicamento;
use Illuminate\Database\Seeder;

/**
 * Catálogo de medicamentos de pronto-socorro.
 *
 * `alta_vigilancia` segue a lista de medicamentos potencialmente perigosos do ISMP
 * Brasil: insulina, heparina, opioides e eletrólitos concentrados. RN-22 exige dupla
 * checagem por um segundo profissional para todos eles -- são os fármacos em que o erro
 * de dose mata, e não apenas incomoda.
 *
 * `principio_ativo` é a coluna que importa para RN-21: a verificação de alergia é feita
 * por princípio ativo, nunca por nome comercial.
 */
class MedicamentoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogo() as $medicamento) {
            Medicamento::updateOrCreate(
                [
                    'nome_comercial' => $medicamento['nome_comercial'],
                    'concentracao' => $medicamento['concentracao'],
                ],
                $medicamento
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogo(): array
    {
        return [
            // --- Alta vigilância (RN-22: exigem dupla checagem) ---
            [
                'nome_comercial' => 'Humulin R', 'principio_ativo' => 'Insulina humana regular',
                'concentracao' => '100 UI/mL', 'forma_farmaceutica' => 'frasco-ampola',
                'classe_via' => 'SC', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => false, 'unidade_dose_padrao' => 'UI', 'dose_maxima_diaria' => null,
                'observacao' => 'ISMP: erro de dose com insulina é causa clássica de hipoglicemia grave.',
            ],
            [
                'nome_comercial' => 'Liquemine', 'principio_ativo' => 'Heparina sódica',
                'concentracao' => '5.000 UI/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => false, 'unidade_dose_padrao' => 'UI', 'dose_maxima_diaria' => null,
                'observacao' => 'Confundir apresentação de 5.000 UI/mL com 25.000 UI/mL causa hemorragia.',
            ],
            [
                'nome_comercial' => 'Dimorf', 'principio_ativo' => 'Sulfato de morfina',
                'concentracao' => '10 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => true, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 60.000,
                'observacao' => 'Opioide. Portaria SVS/MS 344/1998, lista A1.',
            ],
            [
                'nome_comercial' => 'Cloreto de Potássio 19,1%', 'principio_ativo' => 'Cloreto de potássio',
                'concentracao' => '191 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => false, 'unidade_dose_padrao' => 'mEq', 'dose_maxima_diaria' => null,
                'observacao' => 'NUNCA administrar em bolus: parada cardíaca. Diluir obrigatoriamente.',
            ],
            [
                'nome_comercial' => 'Fentanest', 'principio_ativo' => 'Citrato de fentanila',
                'concentracao' => '50 mcg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => true, 'unidade_dose_padrao' => 'mcg', 'dose_maxima_diaria' => null,
                'observacao' => 'Opioide de alta potência. Risco de depressão respiratória.',
            ],
            [
                'nome_comercial' => 'Dormonid', 'principio_ativo' => 'Midazolam',
                'concentracao' => '5 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => true, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => null,
                'observacao' => 'Benzodiazepínico. Sedação com risco de apneia.',
            ],
            [
                'nome_comercial' => 'Noradrenalina Hipolabor', 'principio_ativo' => 'Hemitartarato de noradrenalina',
                'concentracao' => '2 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => false, 'unidade_dose_padrao' => 'mcg/kg/min', 'dose_maxima_diaria' => null,
                'observacao' => 'Vasopressor. Extravasamento causa necrose tecidual.',
            ],

            // --- Analgésicos e antitérmicos ---
            [
                'nome_comercial' => 'Novalgina', 'principio_ativo' => 'Dipirona sódica',
                'concentracao' => '500 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 4000.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Tylenol', 'principio_ativo' => 'Paracetamol',
                'concentracao' => '500 mg', 'forma_farmaceutica' => 'comprimido',
                'classe_via' => 'ORAL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 4000.000,
                'observacao' => 'Hepatotoxicidade acima de 4 g/dia.',
            ],
            [
                'nome_comercial' => 'Toragesic', 'principio_ativo' => 'Trometamol cetorolaco',
                'concentracao' => '30 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 90.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Tramal', 'principio_ativo' => 'Cloridrato de tramadol',
                'concentracao' => '50 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => true, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 400.000,
                'observacao' => 'Opioide fraco. Portaria 344/1998, lista A2.',
            ],
            [
                'nome_comercial' => 'Advil', 'principio_ativo' => 'Ibuprofeno',
                'concentracao' => '400 mg', 'forma_farmaceutica' => 'comprimido',
                'classe_via' => 'ORAL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 2400.000,
                'observacao' => null,
            ],

            // --- Antieméticos, antiespasmódicos e gastroprotetores ---
            [
                'nome_comercial' => 'Plasil', 'principio_ativo' => 'Cloridrato de metoclopramida',
                'concentracao' => '5 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 30.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Vonau', 'principio_ativo' => 'Cloridrato de ondansetrona',
                'concentracao' => '2 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 24.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Buscopan Composto', 'principio_ativo' => 'Butilbrometo de escopolamina',
                'concentracao' => '20 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 100.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Pantozol', 'principio_ativo' => 'Pantoprazol sódico',
                'concentracao' => '40 mg', 'forma_farmaceutica' => 'frasco-ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 80.000,
                'observacao' => null,
            ],

            // --- Antibióticos ---
            [
                'nome_comercial' => 'Keflex', 'principio_ativo' => 'Cefalexina',
                'concentracao' => '500 mg', 'forma_farmaceutica' => 'cápsula',
                'classe_via' => 'ORAL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 4000.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Rocefin', 'principio_ativo' => 'Ceftriaxona sódica',
                'concentracao' => '1 g', 'forma_farmaceutica' => 'frasco-ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'g', 'dose_maxima_diaria' => 4.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Amoxil', 'principio_ativo' => 'Amoxicilina',
                'concentracao' => '500 mg', 'forma_farmaceutica' => 'cápsula',
                'classe_via' => 'ORAL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 3000.000,
                'observacao' => 'Penicilina: alergia cruzada frequente. Ver RN-21.',
            ],
            [
                'nome_comercial' => 'Flagyl', 'principio_ativo' => 'Metronidazol',
                'concentracao' => '5 mg/mL', 'forma_farmaceutica' => 'bolsa',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 4000.000,
                'observacao' => null,
            ],

            // --- Cardiovasculares e respiratórios ---
            [
                'nome_comercial' => 'Aerolin', 'principio_ativo' => 'Sulfato de salbutamol',
                'concentracao' => '5 mg/mL', 'forma_farmaceutica' => 'solução para nebulização',
                'classe_via' => 'INALATORIO', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'gotas', 'dose_maxima_diaria' => null,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Atrovent', 'principio_ativo' => 'Brometo de ipratrópio',
                'concentracao' => '0,25 mg/mL', 'forma_farmaceutica' => 'solução para nebulização',
                'classe_via' => 'INALATORIO', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'gotas', 'dose_maxima_diaria' => null,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Isordil', 'principio_ativo' => 'Dinitrato de isossorbida',
                'concentracao' => '5 mg', 'forma_farmaceutica' => 'comprimido sublingual',
                'classe_via' => 'SL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 15.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Lasix', 'principio_ativo' => 'Furosemida',
                'concentracao' => '10 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 600.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'AAS', 'principio_ativo' => 'Ácido acetilsalicílico',
                'concentracao' => '100 mg', 'forma_farmaceutica' => 'comprimido',
                'classe_via' => 'ORAL', 'injetavel' => false, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 300.000,
                'observacao' => null,
            ],

            // --- Corticoides, anti-histamínicos e antídotos ---
            [
                'nome_comercial' => 'Decadron', 'principio_ativo' => 'Fosfato dissódico de dexametasona',
                'concentracao' => '4 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 20.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Solu-Medrol', 'principio_ativo' => 'Succinato sódico de metilprednisolona',
                'concentracao' => '500 mg', 'forma_farmaceutica' => 'frasco-ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 1000.000,
                'observacao' => null,
            ],
            [
                'nome_comercial' => 'Fenergan', 'principio_ativo' => 'Cloridrato de prometazina',
                'concentracao' => '25 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IM', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => 100.000,
                'observacao' => 'IM profunda: aplicação IV causa lesão tecidual grave.',
            ],
            [
                'nome_comercial' => 'Adrenalina Hipolabor', 'principio_ativo' => 'Epinefrina',
                'concentracao' => '1 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IM', 'injetavel' => true, 'alta_vigilancia' => true,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => null,
                'observacao' => 'Anafilaxia e PCR. Confundir via IM com IV é erro grave.',
            ],
            [
                'nome_comercial' => 'Narcan', 'principio_ativo' => 'Cloridrato de naloxona',
                'concentracao' => '0,4 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mg', 'dose_maxima_diaria' => null,
                'observacao' => 'Antídoto de opioide.',
            ],
            [
                'nome_comercial' => 'Glicose 50%', 'principio_ativo' => 'Glicose',
                'concentracao' => '500 mg/mL', 'forma_farmaceutica' => 'ampola',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mL', 'dose_maxima_diaria' => null,
                'observacao' => 'Hipoglicemia sintomática.',
            ],
            [
                'nome_comercial' => 'Soro Fisiológico 0,9%', 'principio_ativo' => 'Cloreto de sódio',
                'concentracao' => '9 mg/mL', 'forma_farmaceutica' => 'bolsa 500 mL',
                'classe_via' => 'IV', 'injetavel' => true, 'alta_vigilancia' => false,
                'controlado' => false, 'unidade_dose_padrao' => 'mL', 'dose_maxima_diaria' => null,
                'observacao' => null,
            ],
        ];
    }
}

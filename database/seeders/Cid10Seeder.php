<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cid10;
use Illuminate\Database\Seeder;

/**
 * Subconjunto da CID-10 com os códigos mais frequentes em urgência e emergência.
 *
 * Não é a CID-10 completa (são mais de 14 mil códigos): é a carga operacional que
 * permite o sistema funcionar de ponta a ponta. A tabela é de domínio -- a carga
 * completa entra por importação, sem tocar em código.
 */
class Cid10Seeder extends Seeder
{
    public function run(): void
    {
        $codigos = [
            // Infecciosas
            'A09' => 'Diarreia e gastroenterite de origem infecciosa presumível',
            'A41.9' => 'Septicemia não especificada',
            'B34.9' => 'Infecção viral não especificada',
            // Endócrinas e metabólicas
            'E10.1' => 'Diabetes mellitus insulino-dependente com cetoacidose',
            'E11.9' => 'Diabetes mellitus não-insulino-dependente sem complicações',
            'E16.2' => 'Hipoglicemia não especificada',
            'E86' => 'Depleção de volume',
            'E87.6' => 'Hipopotassemia',
            // Mentais e comportamentais
            'F10.0' => 'Intoxicação aguda pelo uso de álcool',
            'F41.0' => 'Transtorno de pânico',
            // Sistema nervoso
            'G40.9' => 'Epilepsia não especificada',
            'G43.9' => 'Enxaqueca não especificada',
            'G45.9' => 'Isquemia cerebral transitória não especificada',
            // Olho
            'H10.9' => 'Conjuntivite não especificada',
            // Circulatórias
            'I10' => 'Hipertensão essencial (primária)',
            'I20.0' => 'Angina instável',
            'I21.9' => 'Infarto agudo do miocárdio não especificado',
            'I26.9' => 'Embolia pulmonar sem menção de cor pulmonale agudo',
            'I44.2' => 'Bloqueio atrioventricular total',
            'I48' => 'Flutter e fibrilação atrial',
            'I50.9' => 'Insuficiência cardíaca não especificada',
            'I61.9' => 'Hemorragia intracerebral não especificada',
            'I63.9' => 'Infarto cerebral não especificado',
            // Respiratórias
            'J06.9' => 'Infecção aguda das vias aéreas superiores não especificada',
            'J18.9' => 'Pneumonia não especificada',
            'J44.1' => 'Doença pulmonar obstrutiva crônica com exacerbação aguda',
            'J45.9' => 'Asma não especificada',
            'J96.0' => 'Insuficiência respiratória aguda',
            // Digestivas
            'K29.7' => 'Gastrite não especificada',
            'K35.8' => 'Apendicite aguda não especificada',
            'K52.9' => 'Gastroenterite e colite não-infecciosas não especificadas',
            'K80.2' => 'Calculose da vesícula biliar sem colecistite',
            'K92.2' => 'Hemorragia gastrointestinal não especificada',
            // Pele
            'L03.9' => 'Celulite não especificada',
            'L50.9' => 'Urticária não especificada',
            // Osteomusculares
            'M54.5' => 'Dor lombar baixa',
            'M79.1' => 'Mialgia',
            // Geniturinárias
            'N20.0' => 'Calculose do rim',
            'N39.0' => 'Infecção do trato urinário de localização não especificada',
            // Gravidez
            'O20.0' => 'Ameaça de aborto',
            // Sintomas e sinais
            'R07.4' => 'Dor torácica não especificada',
            'R10.4' => 'Outras dores abdominais e as não especificadas',
            'R11' => 'Náusea e vômitos',
            'R42' => 'Tontura e instabilidade',
            'R50.9' => 'Febre não especificada',
            'R51' => 'Cefaleia',
            'R55' => 'Síncope e colapso',
            'R56.8' => 'Outras convulsões e as não especificadas',
            'R57.0' => 'Choque cardiogênico',
            'R57.1' => 'Choque hipovolêmico',
            // Traumatismos
            'S00.9' => 'Traumatismo superficial da cabeça, parte não especificada',
            'S06.0' => 'Concussão cerebral',
            'S52.5' => 'Fratura da extremidade distal do rádio',
            'S61.9' => 'Ferimento do punho e da mão, parte não especificada',
            'S93.4' => 'Entorse e distensão do tornozelo',
            'T14.1' => 'Ferimento de região não especificada do corpo',
            'T78.2' => 'Choque anafilático não especificado',
            'T78.4' => 'Alergia não especificada',
            'T88.7' => 'Efeito adverso não especificado de droga ou medicamento',
        ];

        foreach ($codigos as $codigo => $descricao) {
            Cid10::updateOrCreate(['codigo' => $codigo], ['descricao' => $descricao]);
        }
    }
}

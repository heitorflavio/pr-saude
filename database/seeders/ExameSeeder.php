<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Exame;
use Illuminate\Database\Seeder;

/**
 * Catálogo de exames de pronto-socorro. `prazo_padrao_minutos` alimenta a estimativa
 * de liberação e a fila do laboratório (urgentes primeiro).
 */
class ExameSeeder extends Seeder
{
    public function run(): void
    {
        $exames = [
            ['codigo' => 'HEMOG', 'nome' => 'Hemograma completo', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => null],
            ['codigo' => 'PCR', 'nome' => 'Proteína C reativa', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => null],
            ['codigo' => 'GLIC', 'nome' => 'Glicemia', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 30, 'preparo' => 'Jejum de 8 horas quando solicitada em rotina.'],
            ['codigo' => 'UREIA', 'nome' => 'Ureia', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => null],
            ['codigo' => 'CREAT', 'nome' => 'Creatinina', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => null],
            ['codigo' => 'ELETR', 'nome' => 'Eletrólitos (sódio, potássio, cloro)', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => null],
            ['codigo' => 'TROPO', 'nome' => 'Troponina I ultrassensível', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 45, 'preparo' => 'Coleta seriada conforme protocolo de dor torácica.'],
            ['codigo' => 'DDIM', 'nome' => 'D-dímero', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 90, 'preparo' => null],
            ['codigo' => 'GASO', 'nome' => 'Gasometria arterial', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 20, 'preparo' => 'Coleta arterial. Enviar em gelo, sem bolhas na seringa.'],
            ['codigo' => 'COAGU', 'nome' => 'Coagulograma (TP, TTPA, INR)', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 90, 'preparo' => null],
            ['codigo' => 'TGOTGP', 'nome' => 'Transaminases (TGO e TGP)', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 90, 'preparo' => null],
            ['codigo' => 'AMILAS', 'nome' => 'Amilase e lipase', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 90, 'preparo' => null],
            ['codigo' => 'EAS', 'nome' => 'Urina tipo I (EAS)', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 60, 'preparo' => 'Jato médio, após higiene íntima.'],
            ['codigo' => 'UROCUL', 'nome' => 'Urocultura com antibiograma', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 4320, 'preparo' => 'Coletar antes do início do antibiótico.'],
            ['codigo' => 'HEMOCUL', 'nome' => 'Hemocultura (2 amostras)', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 7200, 'preparo' => 'Dois sítios distintos, antes do antibiótico.'],
            ['codigo' => 'BHCG', 'nome' => 'Beta-HCG quantitativo', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 90, 'preparo' => null],
            ['codigo' => 'LACTA', 'nome' => 'Lactato sérico', 'tipo' => 'LABORATORIAL', 'prazo_padrao_minutos' => 30, 'preparo' => 'Protocolo de sepse.'],
            ['codigo' => 'ECG', 'nome' => 'Eletrocardiograma de 12 derivações', 'tipo' => 'GRAFICO', 'prazo_padrao_minutos' => 10, 'preparo' => 'Realizar em até 10 minutos na dor torácica.'],
            ['codigo' => 'RXTOR', 'nome' => 'Radiografia de tórax', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 45, 'preparo' => 'Remover adornos metálicos.'],
            ['codigo' => 'RXABD', 'nome' => 'Radiografia de abdome agudo', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 45, 'preparo' => null],
            ['codigo' => 'RXEXT', 'nome' => 'Radiografia de extremidade', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 45, 'preparo' => null],
            ['codigo' => 'TCCRA', 'nome' => 'Tomografia de crânio sem contraste', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 90, 'preparo' => 'Confirmar ausência de gestação.'],
            ['codigo' => 'TCABD', 'nome' => 'Tomografia de abdome com contraste', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 120, 'preparo' => 'Verificar função renal e alergia a contraste iodado.'],
            ['codigo' => 'USGABD', 'nome' => 'Ultrassonografia de abdome total', 'tipo' => 'IMAGEM', 'prazo_padrao_minutos' => 90, 'preparo' => 'Jejum de 6 horas quando possível.'],
        ];

        foreach ($exames as $exame) {
            Exame::updateOrCreate(['codigo' => $exame['codigo']], $exame + ['ativo' => true]);
        }
    }
}

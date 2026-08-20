<?php

declare(strict_types=1);

namespace App\Services\Pulseira;

use App\Models\Atendimento;
use App\Models\ClassificacaoRisco;
use App\Models\Paciente;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;

/**
 * Renderização da pulseira térmica (doc §8.4 e §8.5).
 *
 * Serviço **puro**: não escreve nada. O registro em `pulseira_impressao` é da
 * `ImprimirPulseiraAction`, porque escrita passa por Action.
 *
 * ⚠️ A API do `endroid/qr-code` **6.x** difere do exemplo da doc §8.5, escrito para a
 * v4/v5: `Builder::create()->writer(...)->data(...)` não existe mais. A v6 usa
 * construtor com argumentos nomeados. Ver docs/DECISOES.md D-29.
 */
final class GerarPulseiraService
{
    /**
     * Lado de 22 mm a 300 dpi ≈ 260 px. 600 px dá margem de reamostragem sem custo
     * relevante -- o PDF reduz para 22 mm na impressão (doc §8.5).
     */
    private const TAMANHO_QR_PX = 600;

    /** Pulseira térmica: 25 mm de altura × 280 mm de comprimento. */
    private const LARGURA_MM = 280;

    private const ALTURA_MM = 25;

    private const MM_EM_PONTOS = 2.834645669;

    public function pdf(
        Paciente $paciente,
        ?Atendimento $atendimento = null,
        ?ClassificacaoRisco $classificacao = null,
        ?Carbon $impressaEm = null,
    ): string {
        $impressaEm ??= now();

        return Pdf::loadView('pulseira.termica', [
            'paciente' => $paciente,
            'atendimento' => $atendimento,
            'classificacao' => $classificacao,
            'qrBase64' => $this->qrCodeDataUri($paciente),
            // RF-11: a faixa de alergia é a última linha e usa marcação redundante.
            // Se houver um único elemento que precise sobreviver a uma impressão ruim,
            // é esse.
            'alergias' => $paciente->alergias->pluck('substancia')->all(),
            /*
             * Idade CONGELADA no momento da impressão -- a única exceção consciente a
             * D-01, e ela tem motivo: papel não recalcula. Por isso a data de nascimento
             * é impressa ao lado; ela é a fonte de verdade, a idade é conveniência para
             * cálculo de dose pediátrica.
             */
            'idadeCongelada' => $paciente->idadeDescritiva($impressaEm),
            'impressaEm' => $impressaEm,
        ])->setPaper([
            0,
            0,
            self::LARGURA_MM * self::MM_EM_PONTOS,
            self::ALTURA_MM * self::MM_EM_PONTOS,
        ])->output();
    }

    /**
     * QR Code versão 5, correção Q, para impressão em 22 mm (doc §8.5).
     *
     * Nível **Q (25%)** e não M: a pulseira fica 12 horas no punho e enfrenta água,
     * sabão, sangue e álcool 70%. Baixar para M ganharia uma versão de QR, mas trocaria
     * tolerância a sujeira por tamanho -- exatamente o recurso que o uso mais consome.
     *
     * A versão não é fixada por parâmetro: o `endroid` escolhe a menor que acomode o
     * conteúdo. Com a URL de 48 caracteres e ECC Q, isso dá a versão 5 (37 × 37 módulos),
     * como o cálculo da doc previu -- e o teste verifica esse número.
     */
    public function qrCodeDataUri(Paciente $paciente): string
    {
        return $this->construirQr($paciente)->build()->getDataUri();
    }

    /** Exposto para o teste conferir a contagem de módulos. */
    public function construirQr(Paciente $paciente): Builder
    {
        return new Builder(
            writer: new PngWriter,
            // RF-16 / RN-03: SEMPRE o mesmo token, em toda reimpressão.
            data: route('pulseira.resolver', $paciente->token_pulseira),
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: self::TAMANHO_QR_PX,
            // Zona de silêncio controlada no template, não aqui.
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
    }
}

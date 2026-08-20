<?php

declare(strict_types=1);

namespace App\Services\Pulseira;

use App\Contracts\GeradorTokenPulseira;
use RuntimeException;

/**
 * Token de pulseira (doc §8.2.1).
 *
 * Estrutura: corpo aleatório de 22 caracteres base62 + sufixo HMAC de 4 caracteres.
 * 22 × log2(62) ≈ 131 bits de entropia.
 *
 * RN-03 / D-09: o token é **opaco**. O QR Code não codifica id nem CPF -- codificar o id
 * sequencial tornaria a enumeração de todos os pacientes uma questão de contar de 1 a N.
 *
 * O token é **permanente**: gerado uma vez, nunca alterado, nunca reaproveitado. A
 * reimpressão da pulseira usa o mesmo token (RF-16).
 *
 * **Por que um checksum HMAC, se 131 bits já tornam a adivinhação impossível?** Por três
 * razões operacionais, não criptográficas (doc §8.2.1):
 *
 * 1. Rejeição sem consulta ao banco -- 100.000 tentativas não viram 100.000 SELECTs.
 * 2. Detecção de leitura corrompida -- pulseira suja ou rasgada vira "não reconhecida"
 *    em vez de, na pior hipótese, resolver para outro paciente.
 * 3. Sinal de auditoria -- formato correto com checksum inválido é evidência de
 *    manipulação deliberada, não de erro de digitação.
 */
final class TokenPulseiraService implements GeradorTokenPulseira
{
    private const ALFABETO = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const TAM_CORPO = 22;

    private const TAM_CHECK = 4;

    public const TAM_TOTAL = self::TAM_CORPO + self::TAM_CHECK;

    public function gerar(): string
    {
        $corpo = '';
        $ultimoIndice = strlen(self::ALFABETO) - 1;

        for ($i = 0; $i < self::TAM_CORPO; $i++) {
            // random_int usa o CSPRNG do sistema operacional. rand()/mt_rand() seriam
            // inaceitáveis: previsíveis a partir da semente, o que reduziria 131 bits de
            // entropia a praticamente nada.
            $corpo .= self::ALFABETO[random_int(0, $ultimoIndice)];
        }

        return $corpo.$this->checksum($corpo);
    }

    public function valido(string $token): bool
    {
        if (strlen($token) !== self::TAM_TOTAL) {
            return false;
        }

        $corpo = substr($token, 0, self::TAM_CORPO);
        $recebido = substr($token, self::TAM_CORPO);

        // hash_equals: comparação em tempo constante, imune a timing attack. `==` aqui
        // vazaria, byte a byte, quantos caracteres do checksum já estão corretos.
        return hash_equals($this->checksum($corpo), $recebido);
    }

    private function checksum(string $corpo): string
    {
        $hmac = hash_hmac('sha256', $corpo, $this->chave(), binary: true);

        return substr(
            rtrim(strtr(base64_encode($hmac), '+/', 'AB'), '='),
            0,
            self::TAM_CHECK
        );
    }

    /**
     * Falha alto e cedo quando a chave não está configurada.
     *
     * Sem isto, o HMAC seria calculado sobre string vazia e o sistema geraria tokens
     * "válidos" que qualquer pessoa com o código-fonte conseguiria forjar -- uma falha
     * silenciosa é a pior forma de falhar aqui.
     */
    private function chave(): string
    {
        $chave = config('app.pulseira_key');

        if (! is_string($chave) || $chave === '') {
            throw new RuntimeException(
                'PULSEIRA_KEY não configurada. Gere com: '
                .'php -r "echo \'base64:\'.base64_encode(random_bytes(32));"'
            );
        }

        return $chave;
    }
}

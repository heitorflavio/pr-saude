<?php

declare(strict_types=1);

namespace App\Services\Atendimento;

use App\Models\Atendimento;
use App\Models\Unidade;

/**
 * RF-21: numeração do atendimento — `2026-000148`.
 *
 * O número é o identificador que a equipe usa em voz alta e que a pulseira imprime como
 * alternativa ao QR (doc §8.4). Precisa ser curto, legível e previsível — por isso
 * sequencial, e não UUID.
 *
 * **Sequencial por ANO, global entre unidades.** O prompt fala em "sequencial por ano e
 * unidade", mas isso é incompatível com o `schema.sql`, que declara
 * `UNIQUE KEY uk_atendimento_numero (numero)` — global, não por unidade — e com o
 * formato documentado, que não tem componente de unidade. Contar por unidade faria a
 * segunda UPA colidir em `2026-000001`.
 *
 * A escolha também é a mais segura para o uso real: um paciente transferido entre
 * unidades teria dois episódios com o mesmo número, e o número é justamente o
 * identificador de recuperação quando o QR falha. Ver docs/DECISOES.md D-34.
 *
 * **Concorrência.** O `lockForUpdate()` serializa a leitura do máximo dentro da
 * transação da Action; o índice único é a garantia final, e a Action repete a tentativa
 * quando ele dispara. Nenhum dos dois sozinho basta: o lock evita o erro no caso comum,
 * o índice cobre o caso em que o lock não alcança.
 */
final class GeradorNumeroAtendimentoService
{
    public function proximo(?Unidade $unidade = null, ?int $ano = null): string
    {
        $ano ??= (int) now()->year;

        $ultimo = Atendimento::withTrashed()
            ->where('numero', 'like', "{$ano}-%")
            ->lockForUpdate()
            ->orderByDesc('numero')
            ->value('numero');

        $sequencial = $ultimo === null ? 1 : ((int) substr($ultimo, -6)) + 1;

        return sprintf('%d-%06d', $ano, $sequencial);
    }
}

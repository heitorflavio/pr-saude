{{--
    Pulseira térmica 25 mm × 280 mm (doc §8.4).

    Quatro decisões de layout que NÃO são estéticas:

    1. A faixa de cor ocupa a borda superior inteira, para ser identificável com a
       pulseira parcialmente coberta pelo lençol ou pela manga.
    2. O nome usa a maior fonte da pulseira -- é o campo lido em voz alta na conferência
       de identidade.
    3. A faixa de alergia é a última linha e usa marcação redundante (símbolo + caixa
       alta + repetição). Se um único elemento precisar sobreviver a uma impressão ruim,
       é esse.
    4. A idade é valor congelado no momento da impressão. Papel não recalcula; a data de
       nascimento ao lado é a fonte de verdade.

    NÃO se imprime CPF, CNS, CID nem endereço (doc §8.4). O CPF não melhora a
    identificação assistencial -- nome + data de nascimento já dão dois identificadores
    independentes -- e piora significativamente a exposição. Custo alto, benefício nulo.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pulseira {{ $paciente->nomeExibicao() }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #000;
        }

        /* Decisão 1: a faixa ocupa a borda superior inteira. */
        .faixa-prioridade {
            width: 100%;
            height: 14pt;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 1pt;
            line-height: 14pt;
        }

        .corpo { padding: 3pt 6pt 0 6pt; }

        /* Decisão 2: o nome é o maior elemento da pulseira. */
        .nome {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.3pt;
        }

        .identificadores { font-size: 7.5pt; margin-top: 2pt; }
        .atendimento { font-size: 7pt; margin-top: 1pt; }
        .unidade { font-size: 6.5pt; margin-top: 1pt; }

        .qr {
            /* 22 mm ≈ 62,4 pt. Zona de silêncio de 2 módulos aplicada pelo padding. */
            width: 62pt;
            height: 62pt;
        }

        /* Decisão 3: marcação redundante -- símbolo, caixa alta e repetição. */
        .faixa-alergia {
            width: 100%;
            background: #000;
            color: #fff;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 1pt;
            padding: 2pt 0;
        }
    </style>
</head>
<body>

@php
    // RNF-15: cor + rótulo textual, nunca só a cor. Quem não distingue as cores precisa
    // conseguir ler o nível.
    $corHex = $classificacao?->cor_hex ?? '#FFFFFF';
    $rotuloCor = $classificacao
        ? mb_strtoupper($classificacao->nome.' — '.$classificacao->cor_nome->value)
        : 'AGUARDANDO CLASSIFICAÇÃO DE RISCO';
    // Fundo escuro exige texto claro; amarelo e verde-claro exigem texto preto.
    $corTexto = in_array($classificacao?->cor_nome->value, ['AMARELO', null], true) ? '#000' : '#FFF';
@endphp

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="faixa-prioridade" style="background: {{ $corHex }}; color: {{ $corTexto }};">
            {{ $rotuloCor }}
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" class="corpo">
    <tr>
        <td valign="top">
            <div class="nome">{{ mb_strtoupper($paciente->nomeExibicao()) }}</div>

            <div class="identificadores">
                {{-- Segundo identificador: protocolos de segurança exigem DOIS
                     conferidos antes de qualquer procedimento. --}}
                Nasc. {{ $paciente->data_nascimento?->format('d/m/Y') }}
                &nbsp;&nbsp; {{ $idadeCongelada }}
                &nbsp;&nbsp; {{ mb_substr($paciente->sexo ?? '', 0, 1) }}
            </div>

            @if ($atendimento)
                <div class="atendimento">
                    Atend. {{ $atendimento->numero }}
                    &nbsp;&nbsp; Adm. {{ $atendimento->admitido_em?->format('d/m/Y H:i') }}
                </div>
                <div class="unidade">{{ mb_strtoupper($atendimento->unidade?->nome ?? '') }}</div>
            @else
                <div class="atendimento">Sem atendimento aberto</div>
            @endif
        </td>

        <td valign="top" align="right" width="70pt">
            {{-- RF-14. O QR codifica apenas a URL com o token opaco: nunca id nem CPF. --}}
            <img src="{{ $qrBase64 }}" class="qr" alt="">
        </td>
    </tr>
</table>

@if (count($alergias) > 0)
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="faixa-alergia">
                &#9888;&#9888;&#9888;
                ALERGIA: {{ mb_strtoupper(implode(' / ', $alergias)) }}
                &#9888;&#9888;&#9888;
            </td>
        </tr>
    </table>
@endif

</body>
</html>

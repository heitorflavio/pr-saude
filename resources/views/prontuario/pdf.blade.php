{{--
  RF-52 — exportação do prontuário do atendimento em PDF.

  O documento sai com a tarja de retificação, com o autor tal como assinou na época
  (snapshot de `autor_nome` / `autor_conselho`) e com o resultado da verificação de
  integridade. Um export que omitisse o adendo, ou que mostrasse o cadastro atual do
  profissional em vez do da época, produziria um papel que contradiz o banco — e o papel
  é o que circula fora do sistema.

  Sem CSS externo: o dompdf renderiza offline, e um `<link>` que não resolve deixaria o
  documento sem formatação alguma.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Prontuário {{ $atendimento->numero }}</title>
    <style>
        @page { margin: 18mm 15mm 20mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; line-height: 1.45; }
        h1 { font-size: 14pt; margin: 0 0 2mm; }
        h2 { font-size: 10.5pt; margin: 6mm 0 2mm; border-bottom: 1px solid #999; padding-bottom: 1mm; }
        .cabecalho { border-bottom: 2px solid #111; padding-bottom: 3mm; margin-bottom: 4mm; }
        .meta { font-size: 8.5pt; color: #444; }
        table.ficha { width: 100%; border-collapse: collapse; }
        table.ficha td { padding: 0.8mm 0; vertical-align: top; }
        table.ficha td.rotulo { width: 32mm; color: #444; }
        .alerta { border: 1.2pt solid #a10000; background: #fdeaea; padding: 2.5mm; margin: 3mm 0; }
        .alerta strong { color: #a10000; }
        .registro { border: 0.8pt solid #bbb; padding: 3mm; margin-bottom: 3.5mm; page-break-inside: avoid; }
        .registro .titulo { font-weight: bold; font-size: 9.5pt; }
        .registro .assinatura { font-size: 8.5pt; color: #444; margin-top: 2mm; border-top: 0.5pt dotted #bbb; padding-top: 1.5mm; }
        .tarja { background: #fff4d6; border-left: 3pt solid #b07d00; padding: 1.8mm 2.5mm; margin-bottom: 2mm; font-size: 8.5pt; }
        .adendo { border-left: 3pt solid #444; }
        .soap { margin: 1.5mm 0 0; }
        .soap dt { float: left; width: 22mm; font-weight: bold; color: #333; }
        .soap dd { margin: 0 0 1.2mm 22mm; }
        table.diag { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.diag th, table.diag td { border: 0.5pt solid #bbb; padding: 1.5mm; text-align: left; }
        table.diag th { background: #eee; }
        .integridade { margin-top: 5mm; padding: 2.5mm; border: 0.8pt solid #bbb; font-size: 8.5pt; }
        .integridade.quebrada { border-color: #a10000; background: #fdeaea; }
        .rodape { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #555; border-top: 0.5pt solid #bbb; padding-top: 1.5mm; }
    </style>
</head>
<body>

<div class="rodape">
    Documento gerado pelo SGH em {{ $emitidoEm->format('d/m/Y H:i') }} por {{ $emitidoPor }}.
    Prontuário {{ $atendimento->numero }} — {{ $paciente->nomeExibicao() }}.
    Documento sem assinatura digital ICP-Brasil (doc §9.5).
</div>

<div class="cabecalho">
    <h1>Prontuário do atendimento {{ $atendimento->numero }}</h1>
    <div class="meta">
        {{ $atendimento->unidade?->nome }} ·
        Admissão {{ $atendimento->admitido_em?->format('d/m/Y H:i') }} ·
        Situação: {{ $atendimento->status->rotulo() }}
        @if ($atendimento->classificacaoRisco)
            · Classificação de risco: {{ $atendimento->classificacaoRisco->nome }}
        @endif
    </div>
</div>

<h2>Identificação</h2>
<table class="ficha">
    <tr><td class="rotulo">Nome</td><td><strong>{{ $paciente->nomeExibicao() }}</strong></td></tr>
    <tr><td class="rotulo">Nascimento</td><td>{{ $paciente->data_nascimento?->format('d/m/Y') }} ({{ $paciente->idadeDescritiva() }})</td></tr>
    <tr><td class="rotulo">Nome social</td><td>{{ $paciente->nome_social ?: '—' }}</td></tr>
    @if ($atendimento->profissionalResponsavel)
        <tr><td class="rotulo">Responsável</td><td>{{ $atendimento->profissionalResponsavel->nome_completo }}</td></tr>
    @endif
    @if ($atendimento->desfecho)
        <tr><td class="rotulo">Desfecho</td><td>{{ $atendimento->desfecho }} — {{ $atendimento->finalizado_em?->format('d/m/Y H:i') }}</td></tr>
    @endif
</table>

{{-- RN-21: a alergia é por princípio ativo, e vem em destaque no topo do documento. --}}
@if ($paciente->alergias->isNotEmpty())
    <div class="alerta">
        <strong>ALERGIAS</strong><br>
        @foreach ($paciente->alergias as $alergia)
            {{ $alergia->substancia }}@if ($alergia->principioAtivo() && $alergia->principioAtivo() !== $alergia->substancia) ({{ $alergia->principioAtivo() }})@endif
            — {{ $alergia->gravidade }}@if (! $loop->last); @endif
        @endforeach
    </div>
@else
    <div class="meta" style="margin: 3mm 0;">Sem alergias registradas.</div>
@endif

@if ($diagnosticos !== [])
    <h2>Diagnósticos (CID-10)</h2>
    <table class="diag">
        <thead>
            <tr><th style="width: 18mm;">Código</th><th>Descrição</th><th style="width: 26mm;">Natureza</th><th style="width: 22mm;">Registrado</th></tr>
        </thead>
        <tbody>
            @foreach ($diagnosticos as $diagnostico)
                <tr>
                    <td>{{ $diagnostico['codigo'] }}</td>
                    <td>{{ $diagnostico['descricao'] }}@if ($diagnostico['principal']) <strong>(principal)</strong>@endif</td>
                    <td>{{ $diagnostico['natureza'] }}</td>
                    <td>{{ $diagnostico['criado_em'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Evolução</h2>

@forelse ($registros as $registro)
    <div class="registro @if ($registro['retifica']) adendo @endif">
        {{-- RF-50: a tarja. O original permanece legível e ligado ao adendo. --}}
        @if ($registro['retificado'])
            <div class="tarja">
                REGISTRO RETIFICADO — consulte
                @foreach ($registro['retificado_por'] as $adendo)
                    o adendo de {{ $adendo['criado_em'] }}@if (! $loop->last),@endif
                @endforeach
            </div>
        @endif

        <div class="titulo">
            {{ $registro['tipo_rotulo'] }} · {{ $registro['criado_em'] }}
        </div>

        @if ($registro['motivo_retificacao'])
            <div class="meta">Motivo da retificação: {{ $registro['motivo_retificacao'] }}</div>
        @endif

        @if ($registro['usa_soap'] || $registro['subjetivo'] || $registro['objetivo'] || $registro['avaliacao'] || $registro['plano'])
            <dl class="soap">
                @if ($registro['subjetivo'])<dt>Subjetivo</dt><dd>{{ $registro['subjetivo'] }}</dd>@endif
                @if ($registro['objetivo'])<dt>Objetivo</dt><dd>{{ $registro['objetivo'] }}</dd>@endif
                @if ($registro['avaliacao'])<dt>Avaliação</dt><dd>{{ $registro['avaliacao'] }}</dd>@endif
                @if ($registro['plano'])<dt>Plano</dt><dd>{{ $registro['plano'] }}</dd>@endif
            </dl>
        @endif

        @if ($registro['conteudo_livre'])
            <div style="margin-top: 1.5mm;">{!! nl2br(e($registro['conteudo_livre'])) !!}</div>
        @endif

        {{-- Snapshot: quem assinou naquele momento, não quem é hoje (doc §9.3). --}}
        <div class="assinatura">
            {{ $registro['autor_nome'] }}@if ($registro['autor_conselho']) — {{ $registro['autor_conselho'] }}@endif
        </div>
    </div>
@empty
    <p class="meta">Nenhum registro clínico neste atendimento.</p>
@endforelse

{{--
  doc §9.4: o resultado da verificação vai impresso. Um prontuário exportado sem essa
  linha obriga quem o recebe a confiar no emissor — que é exatamente o que a cadeia de
  hash existe para dispensar.
--}}
<div class="integridade @if (! $integridade['integra']) quebrada @endif">
    <strong>Integridade da cadeia de registros:</strong>
    @if ($integridade['integra'])
        íntegra na emissão deste documento ({{ count($registros) }} registro(s) verificado(s)).
    @else
        <span style="color:#a10000;">QUEBRA DETECTADA</span> —
        @foreach ($integridade['quebras'] as $quebra)
            registro #{{ $quebra['id'] }}: {{ $quebra['motivo'] }}@if (! $loop->last);@endif
        @endforeach
    @endif
    <br>
    A verificação detecta alteração e remoção de registro; ela não substitui assinatura
    digital com certificado ICP-Brasil (doc §9.4 e §9.5).
</div>

</body>
</html>

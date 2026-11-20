<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Auto de Notícia {{ $auto->codigo }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0; font-size: 11px; }
        .section { margin-bottom: 15px; }
        .section h2 { font-size: 14px; margin: 0 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .row { display: flex; margin-bottom: 6px; }
        .label { width: 140px; font-weight: bold; }
        .text { border: 1px solid #ccc; padding: 10px; min-height: 100px; margin-top: 10px; }
        .footer { margin-top: 40px; text-align: center; font-size: 11px; }
        .assinatura { margin-top: 60px; display: flex; justify-content: space-between; }
        .assinatura div { width: 45%; text-align: center; border-top: 1px solid #000; padding-top: 6px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>SICAM — Polícia da República de Moçambique</h1>
        <p>Auto de Notícia n.º {{ $auto->codigo }}</p>
        <p>Data de Emissão: {{ $auto->emitido_em?->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section">
        <h2>Dados do Veículo</h2>
        <div class="row"><span class="label">Matrícula:</span><span>{{ $auto->matricula }}</span></div>
    </div>

    <div class="section">
        <h2>Dados da Infracção</h2>
        <div class="row"><span class="label">Infracção:</span><span>{{ $auto->infracao_label }} ({{ $auto->infracao_codigo }})</span></div>
        <div class="row"><span class="label">Valor:</span><span>{{ number_format($auto->valor, 2, ',', '.') }} MT</span></div>
        <div class="row"><span class="label">Local:</span><span>{{ $auto->local ?? '—' }}</span></div>
    </div>

    <div class="section">
        <h2>Descrição dos Factos</h2>
        <div class="text">{{ $auto->observacoes }}</div>
    </div>

    <div class="section">
        <h2>Agente Autuante</h2>
        <div class="row"><span class="label">Posto:</span><span>{{ $auto->posto }}</span></div>
        <div class="row"><span class="label">Data:</span><span>{{ $auto->emitido_em?->format('d/m/Y') }}</span></div>
    </div>

    <div class="assinatura">
        <div>Assinatura do Agente</div>
        <div>Assinatura do Condutor</div>
    </div>

</body>
</html>
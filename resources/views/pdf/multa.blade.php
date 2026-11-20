<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #065f46; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #065f46; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 11px; color: #555; }
        .box { border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px dashed #eee; padding-bottom: 4px; }
        .label { font-weight: bold; color: #555; }
        .valor { font-weight: bold; color: #000; }
        .total { text-align: center; font-size: 24px; color: #dc2626; font-weight: bold; margin: 20px 0; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SICAM — Auto de Multa</h1>
        <p>Polícia da República de Moçambique</p>
        <p>Código: {{ $multa->codigo }}</p>
    </div>

    <div class="box">
        <div class="row"><span class="label">Matrícula:</span> <span class="valor">{{ $multa->matricula ?? '—' }}</span></div>
        <div class="row"><span class="label">Nº do Chassi (VIN):</span> <span class="valor">{{ $multa->chapa }}</span></div>
        <div class="row"><span class="label">Data:</span> <span class="valor">{{ $multa->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span class="label">Posto:</span> <span class="valor">{{ $multa->posto }}</span></div>
    </div>

    <div class="box">
        <div class="row"><span class="label">Infracção:</span> <span class="valor">{{ $multa->infracao }}</span></div>
        <div class="row"><span class="label">Observações:</span> <span class="valor">{{ $multa->observacoes ?? '—' }}</span></div>
    </div>

    <div class="total">
        Valor a pagar: {{ number_format($multa->valor, 2, ',', '.') }} MT
    </div>

    <div class="footer">
        Documento gerado por {{ $agente->name }} via SICAM.
    </div>
</body>
</html>
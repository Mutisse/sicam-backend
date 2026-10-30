<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $recibo['numero'] }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 2px solid #065f46;
            margin-bottom: 12px;
        }
        .brand { display: flex; align-items: center; gap: 10px; }
        .logo {
            width: 46px; height: 46px;
            background: #065f46;
            color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 0.05em;
        }
        .brand-title { font-size: 13px; font-weight: 800; color: #065f46; }
        .brand-sub   { font-size: 9px; color: #6b7280; margin-top: 1px; }
        .doc-num { text-align: right; }
        .doc-num-label { font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .doc-num-value { font-size: 13px; font-weight: 800; color: #1f2937; }
        .titulo {
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 8px 0 12px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 20px;
            margin-bottom: 12px;
        }
        .campo { padding: 4px 0; }
        .campo-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .campo-valor {
            font-size: 11px;
            font-weight: 600;
            color: #1f2937;
        }
        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 10px 14px;
            margin: 14px 0 10px;
        }
        .total-label {
            font-size: 10px;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
        }
        .total-valor {
            font-size: 20px;
            font-weight: 800;
            color: #065f46;
        }
        .foot {
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            line-height: 1.6;
            padding-top: 8px;
            border-top: 1px dashed #d1d5db;
        }
        .gerado {
            font-size: 8px;
            color: #9ca3af;
            text-align: right;
            margin-top: 4px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand">
            <div class="logo">SICAM</div>
            <div>
                <div class="brand-title">GESTMUL</div>
                <div class="brand-sub">Sistema de Gestão de Multas</div>
            </div>
        </div>
        <div class="doc-num">
            <div class="doc-num-label">Recibo Nº</div>
            <div class="doc-num-value">{{ $recibo['numero'] }}</div>
        </div>
    </div>

    <div class="titulo">Recibo de Pagamento de Multa</div>

    <div class="grid">
        <div class="campo">
            <div class="campo-label">Data</div>
            <div class="campo-valor">{{ $recibo['data'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Hora</div>
            <div class="campo-valor">{{ $recibo['hora'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Código da multa</div>
            <div class="campo-valor">{{ $recibo['multaCodigo'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Matrícula</div>
            <div class="campo-valor">{{ $recibo['matricula'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Infração</div>
            <div class="campo-valor">{{ $recibo['infracao'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Província</div>
            <div class="campo-valor">{{ $multa['provincia'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Método de pagamento</div>
            <div class="campo-valor">{{ $recibo['metodo'] }}</div>
        </div>
        <div class="campo">
            <div class="campo-label">Pagador</div>
            <div class="campo-valor">{{ $recibo['pagador'] }}</div>
        </div>
        @if (!empty($recibo['referencia']))
            <div class="campo">
                <div class="campo-label">Referência</div>
                <div class="campo-valor">{{ $recibo['referencia'] }}</div>
            </div>
        @endif
    </div>

    <div class="total">
        <div class="total-label">Total pago</div>
        <div class="total-valor">{{ number_format($recibo['valor'], 0, ',', ' ') }} MT</div>
    </div>

    <div class="foot">
        Este documento serve como comprovativo oficial de pagamento.<br>
        Guarde-o por pelo menos 1 ano.
        <div class="gerado">Gerado em {{ $geradoEm }}</div>
    </div>

</body>
</html>
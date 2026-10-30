<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; padding: 20px; }

        .header {
            display: flex; align-items: center; gap: 16px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 14px; margin-bottom: 18px;
        }
        .header__logo {
            width: 48px; height: 48px; border-radius: 10px;
            background: #ecfdf5; color: #065f46;
            display: inline-block; text-align: center;
            line-height: 48px; font-weight: 700; font-size: 18px;
        }
        .header__text h1 { font-size: 16px; margin: 0 0 4px; }
        .header__text p  { font-size: 12px; color: #64748b; margin: 0; }
        .header__codigo {
            margin-left: auto; text-align: right;
            font-size: 11px; color: #64748b;
        }
        .header__codigo strong { color: #0f172a; }

        .subtitulo {
            font-size: 12px; color: #64748b;
            margin-bottom: 16px;
        }

        .resumo {
            background: #f8fafc; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 18px;
        }
        .resumo__linha {
            display: flex; justify-content: space-between;
            padding: 4px 0; font-size: 11px;
        }
        .resumo__linha b { font-weight: 700; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th {
            background: #10b981; color: #fff;
            padding: 8px 10px; text-align: left;
            font-size: 10px; text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #e5ebe8;
            font-size: 10px;
        }
        tbody tr:nth-child(even) { background: #fafbfa; }

        .footer {
            margin-top: 30px; padding-top: 14px;
            border-top: 1px solid #e5ebe8;
            display: flex; justify-content: space-between;
            font-size: 10px; color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header__logo">
            {{ $sigla ?? 'IN' }}
        </div>
        <div class="header__text">
            <h1>{{ $instituicao ?? 'SICAM — Sistema Integrado de Controlo e Monitorização' }}</h1>
            <p>{{ $titulo }}</p>
        </div>
        <div class="header__codigo">
            <div>Gerado em</div>
            <strong>{{ $geradoEm }}</strong>
        </div>
    </div>

    @if(!empty($subtitulo))
        <div class="subtitulo">{{ $subtitulo }}</div>
    @endif

    @if(!empty($resumo))
        <div class="resumo">
            @foreach($resumo as $k => $v)
                <div class="resumo__linha">
                    <span>{{ $k }}</span>
                    <b>{{ $v }}</b>
                </div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($cabecalhos as $c)
                    <th>{{ $c }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($linhas as $l)
                <tr>
                    @foreach($l as $c)
                        <td>{{ $c }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <span>{{ $rodape ?? 'SICAM — Olho de Águia' }}</span>
        <span>{{ count($linhas) }} registos</span>
    </div>
</body>
</html>
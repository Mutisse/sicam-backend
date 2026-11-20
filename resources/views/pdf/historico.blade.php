<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Consultas</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            padding: 24px 28px;
        }

        /* Cabeçalho */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #065f46;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header__left h1 {
            margin: 0;
            font-size: 20px;
            color: #065f46;
            letter-spacing: -0.02em;
        }
        .header__left p {
            margin: 2px 0 0;
            font-size: 11px;
            color: #475569;
        }
        .header__right {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        .header__right b {
            color: #0f172a;
            font-size: 11px;
        }

        /* KPIs */
        .kpis {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .kpi {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
        }
        .kpi__value {
            font-size: 20px;
            font-weight: 700;
            color: #065f46;
            margin: 0;
            line-height: 1;
        }
        .kpi__label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 4px;
        }
        .kpi--critico .kpi__value { color: #dc2626; }
        .kpi--atencao .kpi__value { color: #d97706; }

        /* Análise IA */
        .ia {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .ia__title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #065f46;
            margin: 0 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .ia__resumo {
            font-size: 11.5px;
            line-height: 1.5;
            color: #14532d;
            margin: 0 0 10px;
        }
        .ia__seccao {
            margin-top: 10px;
        }
        .ia__seccao h4 {
            margin: 0 0 6px;
            font-size: 10.5px;
            color: #065f46;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ia__seccao ul {
            margin: 0;
            padding-left: 16px;
        }
        .ia__seccao li {
            font-size: 10.5px;
            line-height: 1.5;
            color: #134e4a;
            margin-bottom: 2px;
        }
        .ia__nivel {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }
        .ia__nivel--alto { background: #dcfce7; color: #14532d; }
        .ia__nivel--medio { background: #fef3c7; color: #92400e; }
        .ia__nivel--baixo { background: #e0e7ff; color: #3730a3; }

        /* Tabela */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        thead th {
            background: #065f46;
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px;
            text-align: left;
        }
        tbody td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #0f172a;
        }
        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge--critico { background: #fee2e2; color: #991b1b; }
        .badge--atencao { background: #fef3c7; color: #92400e; }
        .badge--regular { background: #d1fae5; color: #065f46; }
        .badge--sem-dados { background: #e2e8f0; color: #475569; }

        /* Rodapé */
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        .footer p { margin: 0; }
    </style>
</head>
<body>

    <!-- CABEÇALHO -->
    <div class="header">
        <div class="header__left">
            <h1>Histórico de Consultas</h1>
            <p>Agente: <b>{{ $agente->name }}</b> · Posto: {{ $agente->posto ?? '—' }}</p>
            <p>Período: {{ ucfirst($periodo) }}
                @if($estado !== 'todos') · Estado: {{ ucfirst($estado) }} @endif
                @if($busca) · Busca: "{{ $busca }}" @endif
            </p>
        </div>
        <div class="header__right">
            <b>SICAM</b><br>
            Sistema Integrado de Inteligência<br>
            Tático-Policial Veicular<br>
            <br>
            Gerado em {{ $dataGeracao }}
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpis">
        <div class="kpi">
            <p class="kpi__value">{{ $kpis['total'] }}</p>
            <p class="kpi__label">Total</p>
        </div>
        <div class="kpi">
            <p class="kpi__value">{{ $kpis['hoje'] }}</p>
            <p class="kpi__label">Hoje</p>
        </div>
        <div class="kpi kpi--critico">
            <p class="kpi__value">{{ $kpis['criticos'] }}</p>
            <p class="kpi__label">Críticos</p>
        </div>
        <div class="kpi kpi--atencao">
            <p class="kpi__value">{{ $kpis['atencao'] }}</p>
            <p class="kpi__label">Atenção</p>
        </div>
        <div class="kpi">
            <p class="kpi__value">{{ $kpis['regulares'] }}</p>
            <p class="kpi__label">Regulares</p>
        </div>
    </div>

    <!-- ANÁLISE IA -->
    @if($analiseIA)
        <div class="ia">
            <p class="ia__title">🤖 Resumo Inteligente (IA)</p>

            @if(!empty($analiseIA['nivel_atividade']))
                <span class="ia__nivel ia__nivel--{{ $analiseIA['nivel_atividade'] }}">
                    Atividade {{ $analiseIA['nivel_atividade'] }}
                </span>
            @endif

            @if(!empty($analiseIA['resumo']))
                <p class="ia__resumo">{{ $analiseIA['resumo'] }}</p>
            @endif

            @if(!empty($analiseIA['padrao_dominante']))
                <p class="ia__resumo"><b>Padrão:</b> {{ $analiseIA['padrao_dominante'] }}</p>
            @endif

            @if(!empty($analiseIA['alertas']))
                <div class="ia__seccao">
                    <h4>⚠ Alertas</h4>
                    <ul>
                        @foreach($analiseIA['alertas'] as $alerta)
                            <li>{{ $alerta }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($analiseIA['recomendacoes']))
                <div class="ia__seccao">
                    <h4>💡 Recomendações</h4>
                    <ul>
                        @foreach($analiseIA['recomendacoes'] as $rec)
                            <li>{{ $rec }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <!-- TABELA DE CONSULTAS -->
    <table>
        <thead>
            <tr>
                <th>Matrícula</th>
                <th>Posto</th>
                <th>Score</th>
                <th>Estado</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $c)
                <tr>
                    <td><b>{{ $c->matricula }}</b></td>
                    <td>{{ $c->posto ?? '—' }}</td>
                    <td>{{ $c->score ?? '—' }}</td>
                    <td>
                        <span class="badge badge--{{ $c->estado ?? 'sem-dados' }}">
                            {{ ucfirst($c->estado ?? 'sem-dados') }}
                        </span>
                    </td>
                    <td>{{ $c->consultado_em?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">
                        Nenhuma consulta encontrada para os filtros selecionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- RODAPÉ -->
    <div class="footer">
        <p>SICAM · Documento gerado automaticamente · {{ $dataGeracao }}</p>
    </div>

</body>
</html>
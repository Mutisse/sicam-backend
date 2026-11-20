<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abordagem;
use App\Models\Hotlist;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\SicamIAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PrmAbordagemController extends Controller
{
    protected SicamIAService $ia;

    public function __construct(SicamIAService $ia)
    {
        $this->ia = $ia;
    }

    public function index(Request $request)
    {
        $query = Abordagem::with([
            'veiculo:id,matricula,marca,modelo,score',
            'posto:id,nome,latitude,longitude',
            'agente:id,name',
        ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';

                $q->where(function ($sub) use ($termo) {
                    $sub->where('codigo', 'like', $termo)
                        ->orWhere('local', 'like', $termo)
                        ->orWhereHas('veiculo', fn($v) =>
                            $v->where('matricula', 'like', $termo)
                        );
                });
            })
            ->when(
                $request->filled('resultado'),
                fn($q) => $q->where('resultado', $request->resultado)
            )
            ->when(
                $request->filled('data_inicio'),
                fn($q) => $q->whereDate('data', '>=', $request->data_inicio)
            )
            ->when(
                $request->filled('data_fim'),
                fn($q) => $q->whereDate('data', '<=', $request->data_fim)
            )
            ->orderByDesc('data')
            ->orderByDesc('id');

        $items = $query->get()->map(function (Abordagem $a) {
            $lat = null;
            $lng = null;

            if (
                $a->posto &&
                $a->posto->latitude !== null &&
                $a->posto->longitude !== null &&
                (float) $a->posto->latitude !== 0 &&
                (float) $a->posto->longitude !== 0
            ) {
                $lat = (float) $a->posto->latitude;
                $lng = (float) $a->posto->longitude;
            }

            $emHotlist = Hotlist::where('veiculo_id', $a->veiculo_id)
                ->where('estado', 'ativo')
                ->exists();

            return [
                'id'          => (string) $a->id,
                'codigo'      => $a->codigo ?? '—',

                // Mantém a hora para permitir análise de horário de pico.
                'dataHora'    => $a->data
                    ? $a->data->format('d/m/Y H:i')
                    : '—',

                'matricula'   => $a->veiculo->matricula ?? '—',
                'agente'      => $a->agente->name ?? '—',
                'posto'       => $a->posto->nome ?? '—',
                'local'       => $a->local ?? '—',
                'resultado'   => $a->resultado,
                'emHotlist'   => $emHotlist,
                'observacoes' => $a->observacoes,
                'diasAtras'   => $a->data
                    ? (int) $a->data->diffInDays(now())
                    : 0,
                'lat'         => $lat,
                'lng'         => $lng,
            ];
        })->values();

        $totalHotlist = $items
            ->where('emHotlist', true)
            ->count();

        return response()->json([
            'items' => $items,
            'kpis'  => [
                'total'         => $items->count(),
                'semIncidentes' => $items->where(
                    'resultado',
                    'Sem incidentes'
                )->count(),
                'comDetencao'   => $items->where(
                    'resultado',
                    'Com detenção'
                )->count(),
                'emCurso'       => $items->where(
                    'resultado',
                    'Em curso'
                )->count(),
                'hotlist'       => $totalHotlist,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'matricula'   => [
                'required',
                'string',
                'max:20',
                'exists:veiculos,matricula',
            ],
            'agente'      => ['nullable', 'string', 'max:255'],
            'posto'       => ['nullable', 'string', 'max:255'],
            'local'       => ['nullable', 'string', 'max:255'],
            'resultado'   => [
                'required',
                Rule::in([
                    'Sem incidentes',
                    'Com detenção',
                    'Em curso',
                ]),
            ],
            'observacoes' => ['nullable', 'string'],
        ]);

        try {
            $abordagem = DB::transaction(function () use ($dados) {
                $veiculo = Veiculo::where(
                    'matricula',
                    $dados['matricula']
                )->firstOrFail();

                $agenteId = null;

                if (!empty($dados['agente'])) {
                    $agenteId = User::where(
                        'name',
                        $dados['agente']
                    )->value('id');
                }

                $postoId = null;

                if (!empty($dados['posto'])) {
                    $postoId = Posto::where(
                        'nome',
                        $dados['posto']
                    )->value('id');
                }

                $ultimo = Abordagem::lockForUpdate()->max('id') ?? 0;

                $codigo = 'AB-' . str_pad(
                    $ultimo + 1,
                    3,
                    '0',
                    STR_PAD_LEFT
                );

                $novaAbordagem = Abordagem::create([
                    'codigo'      => $codigo,
                    'veiculo_id'  => $veiculo->id,
                    'agente_id'   => $agenteId,
                    'posto_id'    => $postoId,
                    'local'       => $dados['local'] ?? null,
                    'data'        => now()->toDateString(),
                    'resultado'   => $dados['resultado'],
                    'observacoes' => $dados['observacoes'] ?? null,
                ]);

                $scoreAtual = $veiculo->score ?? 100;
                $novoScore = $scoreAtual;
                $novoEstado = $veiculo->estado;

                if ($dados['resultado'] === 'Sem incidentes') {
                    $novoScore = min(100, $scoreAtual + 5);
                    $novoEstado = 'regular';
                } elseif ($dados['resultado'] === 'Com detenção') {
                    $novoScore = max(0, $scoreAtual - 20);
                    $novoEstado = 'critico';
                }

                $veiculo->update([
                    'score'  => $novoScore,
                    'estado' => $novoEstado,
                ]);

                return $novaAbordagem;
            });

            return response()->json([
                'id'   => (string) $abordagem->id,
                'kpis' => [],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('[PRM-ABORDAGENS] Falha ao registar', [
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Falha ao registar a abordagem.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $abordagem = Abordagem::find($id);

        if (!$abordagem) {
            return response()->json([
                'message' => 'Abordagem não encontrada.',
            ], 404);
        }

        $abordagem->delete();

        return response()->json([
            'message' => 'Abordagem eliminada.',
        ]);
    }

    // ============================================================
    // ⭐ IA ITERATIVA — ANÁLISE DE RELATÓRIO
    // ============================================================
    public function analisarIA(Request $request)
    {
        $request->validate([
            'pergunta' => 'nullable|string|max:500',
            'contexto' => 'nullable|array',
        ]);

        try {
            $query = Abordagem::with([
                'veiculo:id,matricula,marca,modelo,score',
                'posto:id,nome',
                'agente:id,name',
            ])
                ->when(
                    isset($request->contexto['resultado'])
                        && $request->contexto['resultado'],
                    fn($q) => $q->where(
                        'resultado',
                        $request->contexto['resultado']
                    )
                )
                ->when(
                    isset($request->contexto['posto'])
                        && $request->contexto['posto'],
                    fn($q) => $q->whereHas(
                        'posto',
                        fn($p) => $p->where(
                            'nome',
                            $request->contexto['posto']
                        )
                    )
                )
                ->orderByDesc('data')
                ->limit(50);

            $dadosReais = $query->get()->map(function ($a) {
                return [
                    'matricula' => $a->veiculo->matricula ?? '—',
                    'marca'     => $a->veiculo->marca ?? '—',
                    'modelo'    => $a->veiculo->modelo ?? '—',
                    'resultado' => $a->resultado,
                    'posto'     => $a->posto->nome ?? '—',
                    'agente'    => $a->agente->name ?? '—',
                    'data'      => $a->data
                        ? $a->data->format('Y-m-d')
                        : null,
                    'local'     => $a->local ?? '—',
                ];
            })->toArray();

            if (!empty($request->pergunta)) {
                $resposta = $this->ia->conversar([
                    'perfil'      => 'agente_prm',
                    'pergunta'    => $request->pergunta,
                    'contexto'    => $request->contexto,
                    'dados_reais' => $dadosReais,
                ]);
            } else {
                $resposta = $this->ia->analisarRelatorio([
                    'perfil'      => 'agente_prm',
                    'dados_reais' => $dadosReais,
                    'contexto'    => $request->contexto,
                ]);
            }

            return response()->json([
                'resposta'  => $resposta['resposta']
                    ?? $resposta['resumo']
                    ?? 'Sem resposta da IA.',
                'sugestoes' => $resposta['sugestoes'] ?? [
                    'Quais postos têm mais detenções?',
                    'Analisar veículos em hotlist',
                    'Resumo das últimas 24h',
                ],
                'total_analisado' => count($dadosReais),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PRM-IA] Falha na análise', [
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'resposta'  => 'Não foi possível analisar os dados agora.',
                'sugestoes' => [],
            ], 500);
        }
    }

    // ============================================================
    // ⭐ EXPORTAR RELATÓRIO DE ABORDAGENS EM EXCEL
    // ============================================================
    public function xlsx(Request $request)
    {
        $query = Abordagem::with([
            'veiculo:id,matricula,marca,modelo,score',
            'posto:id,nome',
            'agente:id,name',
        ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $termo = '%' . $request->q . '%';

                $q->where(function ($sub) use ($termo) {
                    $sub->where('codigo', 'like', $termo)
                        ->orWhere('local', 'like', $termo)
                        ->orWhereHas('veiculo', fn($v) =>
                            $v->where('matricula', 'like', $termo)
                        );
                });
            })
            ->when(
                $request->filled('resultado'),
                fn($q) => $q->where('resultado', $request->resultado)
            )
            ->when(
                $request->filled('data_inicio'),
                fn($q) => $q->whereDate('data', '>=', $request->data_inicio)
            )
            ->when(
                $request->filled('data_fim'),
                fn($q) => $q->whereDate('data', '<=', $request->data_fim)
            )
            ->orderByDesc('data')
            ->orderByDesc('id');

        $abordagens = $query->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Abordagens');

        $headers = [
            'Código',
            'Data',
            'Matrícula',
            'Marca',
            'Modelo',
            'Agente',
            'Posto',
            'Local',
            'Resultado',
            'Observações',
            'Hotlist',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' =>
                    \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '065F46'],
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        $linha = 2;

        foreach ($abordagens as $a) {
            $emHotlist = Hotlist::where(
                'veiculo_id',
                $a->veiculo_id
            )
                ->where('estado', 'ativo')
                ->exists();

            $sheet->fromArray([
                $a->codigo ?? '—',
                $a->data
                    ? $a->data->format('d/m/Y')
                    : '—',
                $a->veiculo->matricula ?? '—',
                $a->veiculo->marca ?? '—',
                $a->veiculo->modelo ?? '—',
                $a->agente->name ?? '—',
                $a->posto->nome ?? '—',
                $a->local ?? '—',
                $a->resultado ?? '—',
                $a->observacoes ?? '',
                $emHotlist ? 'Sim' : 'Não',
            ], null, 'A' . $linha);

            $linha++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $nomeFicheiro = 'abordagens-' .
            now()->format('Y-m-d_His') .
            '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx(
            $spreadsheet
        );

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            $nomeFicheiro,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    // ============================================================
    // ⭐ IA PARA O MAPA DE CALOR
    // ============================================================
    public function analisarMapaIA(Request $request)
    {
        $request->validate([
            'pergunta'    => 'nullable|string|max:500',
            'camada'      => 'nullable|string|in:todas,detencoes,hotlist,emCurso',
            'q'           => 'nullable|string|max:255',
            'resultado'   => [
                'nullable',
                'string',
                Rule::in([
                    'Sem incidentes',
                    'Com detenção',
                    'Em curso',
                ]),
            ],
            'posto'       => 'nullable|string|max:255',
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
        ]);

        try {
            /*
             * A base do mapa é a MESMA base utilizada pela página
             * de abordagens.
             *
             * A diferença é que aqui NÃO filtramos coordenadas
             * antes de calcular os totais.
             *
             * Assim:
             * - com coordenadas -> aparece no mapa;
             * - sem coordenadas -> não aparece no mapa;
             * - mas continua contabilizada nos resumos.
             */
            $query = Abordagem::with([
                'veiculo:id,matricula,marca,modelo,score',
                'posto:id,nome,latitude,longitude',
                'agente:id,name',
            ])
                ->when($request->filled('q'), function ($q) use ($request) {
                    $termo = '%' . $request->q . '%';

                    $q->where(function ($sub) use ($termo) {
                        $sub->where('codigo', 'like', $termo)
                            ->orWhere('local', 'like', $termo)
                            ->orWhereHas('veiculo', function ($v) use ($termo) {
                                $v->where(
                                    'matricula',
                                    'like',
                                    $termo
                                );
                            })
                            ->orWhereHas('agente', function ($a) use ($termo) {
                                $a->where(
                                    'name',
                                    'like',
                                    $termo
                                );
                            })
                            ->orWhereHas('posto', function ($p) use ($termo) {
                                $p->where(
                                    'nome',
                                    'like',
                                    $termo
                                );
                            });
                    });
                })
                ->when(
                    $request->filled('resultado'),
                    fn($q) => $q->where(
                        'resultado',
                        $request->resultado
                    )
                )
                ->when(
                    $request->filled('posto'),
                    fn($q) => $q->whereHas(
                        'posto',
                        fn($p) => $p->where(
                            'nome',
                            $request->posto
                        )
                    )
                )
                ->when(
                    $request->filled('data_inicio'),
                    fn($q) => $q->whereDate(
                        'data',
                        '>=',
                        $request->data_inicio
                    )
                )
                ->when(
                    $request->filled('data_fim'),
                    fn($q) => $q->whereDate(
                        'data',
                        '<=',
                        $request->data_fim
                    )
                );

            /*
             * Camada visual selecionada no mapa.
             */
            $camada = $request->camada ?? 'todas';

            if ($camada === 'detencoes') {
                $query->where(
                    'resultado',
                    'Com detenção'
                );
            } elseif ($camada === 'hotlist') {
                $query->whereHas('veiculo', function ($v) {
                    $v->whereHas('hotlists', function ($h) {
                        $h->where('estado', 'ativo');
                    });
                });
            } elseif ($camada === 'emCurso') {
                $query->where(
                    'resultado',
                    'Em curso'
                );
            }

            $abordagens = $query
                ->orderByDesc('data')
                ->orderByDesc('id')
                ->get();

            /*
             * Hotlists ativas dos veículos envolvidos.
             *
             * Evita executar uma query Hotlist para cada abordagem.
             */
            $veiculoIds = $abordagens
                ->pluck('veiculo_id')
                ->filter()
                ->unique()
                ->values();

            $hotlistVeiculoIds = Hotlist::whereIn(
                'veiculo_id',
                $veiculoIds
            )
                ->where('estado', 'ativo')
                ->pluck('veiculo_id')
                ->map(fn($id) => (string) $id)
                ->flip();

            /*
             * ====================================================
             * RESUMO GLOBAL
             * ====================================================
             */

            $total = $abordagens->count();

            $semIncidentes = $abordagens
                ->where('resultado', 'Sem incidentes')
                ->count();

            $comDetencao = $abordagens
                ->where('resultado', 'Com detenção')
                ->count();

            $emCurso = $abordagens
                ->where('resultado', 'Em curso')
                ->count();

            $totalHotlist = $abordagens
                ->filter(fn($a) =>
                    $hotlistVeiculoIds->has(
                        (string) $a->veiculo_id
                    )
                )
                ->count();

            /*
             * Abordagens com coordenadas válidas.
             */
            $comCoordenadas = $abordagens->filter(function ($a) {
                return $a->posto
                    && $a->posto->latitude !== null
                    && $a->posto->longitude !== null
                    && (float) $a->posto->latitude !== 0
                    && (float) $a->posto->longitude !== 0;
            });

            $totalComCoordenadas = $comCoordenadas->count();
            $totalSemCoordenadas = $total - $totalComCoordenadas;

            /*
             * ====================================================
             * PONTOS DO MAPA
             * ====================================================
             */
            $pontos = $comCoordenadas
                ->map(function ($a) use ($hotlistVeiculoIds) {
                    $emHotlist = $hotlistVeiculoIds->has(
                        (string) $a->veiculo_id
                    );

                    return [
                        'id' => (string) $a->id,

                        'codigo' => $a->codigo ?? '—',

                        'matricula' =>
                            $a->veiculo->matricula ?? '—',

                        'resultado' =>
                            $a->resultado ?? '—',

                        'posto' =>
                            $a->posto->nome ?? '—',

                        'local' =>
                            $a->local ?? '—',

                        'agente' =>
                            $a->agente->name ?? '—',

                        'data' => $a->data
                            ? $a->data->format('Y-m-d')
                            : null,

                        'hora' => $a->data
                            ? $a->data->format('H:i')
                            : null,

                        'emHotlist' => $emHotlist,

                        'lat' =>
                            (float) $a->posto->latitude,

                        'lng' =>
                            (float) $a->posto->longitude,
                    ];
                })
                ->values()
                ->toArray();

            /*
             * ====================================================
             * AGREGAÇÃO POR ZONA / POSTO
             * ====================================================
             */
            $porZona = $comCoordenadas
                ->groupBy(
                    fn($a) =>
                        $a->posto->nome ?? 'Desconhecido'
                )
                ->map(function ($grupo, $zona) use (
                    $hotlistVeiculoIds
                ) {
                    return [
                        'zona' => $zona,

                        'total' => $grupo->count(),

                        'sem_incidentes' =>
                            $grupo->where(
                                'resultado',
                                'Sem incidentes'
                            )->count(),

                        'detencoes' =>
                            $grupo->where(
                                'resultado',
                                'Com detenção'
                            )->count(),

                        'em_curso' =>
                            $grupo->where(
                                'resultado',
                                'Em curso'
                            )->count(),

                        'hotlist' =>
                            $grupo->filter(
                                fn($a) =>
                                    $hotlistVeiculoIds->has(
                                        (string) $a->veiculo_id
                                    )
                            )->count(),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->take(20)
                ->toArray();

            /*
             * ====================================================
             * AGREGAÇÃO POR HORA
             * ====================================================
             */
            $porHora = $comCoordenadas
                ->groupBy(function ($a) {
                    return $a->data
                        ? $a->data->format('H:00')
                        : 'Sem hora';
                })
                ->map(fn($grupo) => $grupo->count())
                ->sortDesc()
                ->toArray();

            /*
             * ====================================================
             * AGREGAÇÃO POR RESULTADO
             * ====================================================
             */
            $porResultado = [
                'Sem incidentes' => $semIncidentes,
                'Com detenção'   => $comDetencao,
                'Em curso'       => $emCurso,
            ];

            /*
             * ====================================================
             * CONTEXTO REAL PARA A IA
             * ====================================================
             */
            $contexto = [
                'tipo'   => 'mapa_calor',
                'camada' => $camada,

                'filtros' => [
                    'pesquisa' =>
                        $request->q ?: null,

                    'resultado' =>
                        $request->resultado ?: null,

                    'posto' =>
                        $request->posto ?: null,

                    'data_inicio' =>
                        $request->data_inicio ?: null,

                    'data_fim' =>
                        $request->data_fim ?: null,
                ],

                'resumo' => [
                    'total' =>
                        $total,

                    'com_coordenadas' =>
                        $totalComCoordenadas,

                    'sem_coordenadas' =>
                        $totalSemCoordenadas,

                    'sem_incidentes' =>
                        $semIncidentes,

                    'com_detencao' =>
                        $comDetencao,

                    'em_curso' =>
                        $emCurso,

                    'hotlist' =>
                        $totalHotlist,
                ],

                'por_resultado' =>
                    $porResultado,

                'por_zona' =>
                    $porZona,

                'por_hora' =>
                    $porHora,
            ];

            /*
             * Pergunta padrão quando o utilizador apenas
             * solicita uma análise geral.
             */
            $pergunta = trim(
                (string) $request->pergunta
            );

            if ($pergunta === '') {
                $pergunta =
                    'Analisa o mapa de calor das abordagens filtradas. Identifica zonas críticas, horários de maior incidência e sugere prioridades de patrulhamento com base apenas nos dados reais.';
            }

            /*
             * ====================================================
             * CHAMADA À IA
             * ====================================================
             */
            $resposta = $this->ia->conversar([
                'perfil' => 'agente_prm',

                'pergunta' => $pergunta,

                'contexto' => $contexto,

                'dados_reais' => [
                    'resumo' =>
                        $contexto['resumo'],

                    'por_resultado' =>
                        $porResultado,

                    'por_zona' =>
                        $porZona,

                    'por_hora' =>
                        $porHora,

                    'pontos' =>
                        $pontos,
                ],
            ]);

            return response()->json([
                'resposta' =>
                    $resposta['resposta']
                    ?? 'Sem resposta da IA.',

                'sugestoes' =>
                    $resposta['sugestoes']
                    ?? [
                        'Qual é a zona mais crítica?',
                        'Qual é o horário de maior incidência?',
                        'Onde devo reforçar o patrulhamento?',
                    ],

                'total_analisado' =>
                    $total,

                'resumo' =>
                    $contexto['resumo'],

                'por_zona' =>
                    $porZona,

                'por_hora' =>
                    $porHora,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                '[PRM-MAPA-IA] Falha na análise',
                [
                    'erro' =>
                        $e->getMessage(),

                    'linha' =>
                        $e->getLine(),

                    'arquivo' =>
                        $e->getFile(),
                ]
            );

            return response()->json([
                'resposta' =>
                    'Não foi possível analisar o mapa agora.',

                'sugestoes' => [],
            ], 500);
        }
    }
}

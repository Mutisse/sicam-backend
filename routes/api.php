<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InatroController;
use App\Http\Controllers\Api\IssmController;
use App\Http\Controllers\Api\PrmController;
use App\Http\Controllers\Api\GestmulController;
use App\Http\Controllers\Api\ChefeController;

// ⭐ IMPORTS COM LETRA MAIÚSCULA (Padronizado)
use App\Http\Controllers\Api\PrmHotlistController;
use App\Http\Controllers\Api\PrmAbordagemController;
use App\Http\Controllers\Api\PrmAlertaController;
use App\Http\Controllers\Api\PrmRelatorioController;
use App\Http\Controllers\Api\InatroVeiculoController; 
use App\Http\Controllers\Api\InatroCatalogoController;
use App\Http\Controllers\Api\InatroInspecaoController;
use App\Http\Controllers\Api\InatroAgendamentoController;
use App\Http\Controllers\Api\InatroExportacaoController;

use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\RelatorioInatroController;
use App\Http\Controllers\Api\ChefeAgenteController;
use App\Http\Controllers\Api\ChefeEscalaController;
use App\Http\Controllers\Api\ChefePatrulhaController;
use App\Http\Controllers\Api\ChefeVeiculoCriticoController;
use App\Http\Controllers\Api\ChefeAprovacaoController;
use App\Http\Controllers\Api\ChefeRelatorioController;
use App\Http\Controllers\Api\IssmApoliceController;
use App\Http\Controllers\Api\IssmSinistroController;
use App\Http\Controllers\Api\IssmValidacaoController;
use App\Http\Controllers\Api\IssmSeguradoraController;
use App\Http\Controllers\Api\IssmRelatorioController;
use App\Http\Controllers\Api\GestmulContestacaoController;
use App\Http\Controllers\Api\GestmulMultaController;
use App\Http\Controllers\Api\GestmulPagamentoController;
use App\Http\Controllers\Api\GestmulRelatorioController;
use App\Http\Controllers\Api\AdminMonitorizacaoController;
use App\Http\Controllers\Api\AdminUtilizadorController;
use App\Http\Controllers\Api\AdminPerfilController;
use App\Http\Controllers\Api\AdminPostoController;
use App\Http\Controllers\Api\AdminDispositivoController;
use App\Http\Controllers\Api\AdminIntegracaoController;
use App\Http\Controllers\Api\AdminRelatoriosFrotaController;
use App\Http\Controllers\Api\AdminBackupController;
use App\Http\Controllers\Api\AdminRelatoriosOperacoesController;
use App\Http\Controllers\Api\PortalCidadaoController;
use App\Http\Controllers\Api\PortalChatController;
use App\Http\Controllers\Api\AgenteInicioController;
use App\Http\Controllers\Api\AgenteConsultaController;
use App\Http\Controllers\Api\AgenteAutoController;
use App\Http\Controllers\Api\AgenteHistoricoController;
use App\Http\Controllers\Api\IssmCatalogoController;
use App\Http\Controllers\Api\PrmPostoController;

use Illuminate\Support\Facades\Route;

// ============================================================
// AUTENTICAÇÃO
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// ============================================================
// PERFIL DO UTILIZADOR
// ============================================================
Route::prefix('perfil')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',                  [PerfilController::class, 'obter']);
    Route::put('/',                  [PerfilController::class, 'actualizar']);
    Route::put('/senha',             [PerfilController::class, 'alterarSenha']);
    Route::get('/sessoes',           [PerfilController::class, 'sessoes']);
    Route::delete('/sessoes/outras', [PerfilController::class, 'terminarOutrasSessoes']);
    Route::delete('/sessoes/{id}',   [PerfilController::class, 'terminarSessao']);
    Route::get('/preferencias',      [PerfilController::class, 'preferencias']);
    Route::put('/preferencias',      [PerfilController::class, 'guardarPreferencias']);
    Route::post('/foto',             [PerfilController::class, 'uploadFoto']);
});

// ============================================================
// NOTIFICAÇÕES
// ============================================================
Route::prefix('notificacoes')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',                 [NotificacaoController::class, 'index']);
    Route::get('/contagem',         [NotificacaoController::class, 'contagem']);
    Route::put('/ler-todas',        [NotificacaoController::class, 'marcarTodasLidas']);
    Route::put('/{id}/ler',         [NotificacaoController::class, 'marcarLida']);
    Route::delete('/{id}',          [NotificacaoController::class, 'destroy']);
});

// ============================================================
// DASHBOARD
// ============================================================
Route::prefix('dashboard')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/admin',   [AdminDashboardController::class, 'admin']);
    Route::get('/comum',   [DashboardController::class, 'comum']);
    Route::get('/inatro',  [InatroController::class, 'dashboard']);
    Route::get('/issm',    [IssmController::class, 'dashboard']);
    Route::get('/prm',     [PrmController::class, 'dashboard']);
    Route::get('/gestmul', [GestmulController::class, 'dashboard']);
    Route::get('/chefe',   [ChefeController::class, 'dashboard']);

    // ⭐ ROTA DOS CATÁLOGOS PARA O DASHBOARD (Províncias e Postos)
    Route::get('/catalogos', [DashboardController::class, 'catalogos']);
});

// ============================================================
// HOTLIST
// ============================================================
Route::prefix('hotlist')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',              [PrmHotlistController::class, 'index']); 
    Route::get('/kpis',          [PrmHotlistController::class, 'kpisEndpoint']); 
    Route::post('/',             [PrmHotlistController::class, 'store']); 
    Route::put('/{id}',          [PrmHotlistController::class, 'update']); 
    Route::put('/{id}/resolver', [PrmHotlistController::class, 'resolver']); 
    Route::delete('/{id}',       [PrmHotlistController::class, 'destroy']); 
});

// ============================================================
// ABORDAGENS
// ============================================================
Route::prefix('abordagens')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',        [PrmAbordagemController::class, 'index']); 
    Route::post('/',       [PrmAbordagemController::class, 'store']); 
    Route::delete('/{id}', [PrmAbordagemController::class, 'destroy']); 
});

// ============================================================
// ALERTAS
// ============================================================
Route::prefix('alertas')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',              [PrmAlertaController::class, 'index']); 
    Route::put('/ler-todos',     [PrmAlertaController::class, 'marcarTodosLidos']); 
    Route::put('/{id}/ler',      [PrmAlertaController::class, 'marcarLido']); 
    Route::put('/{id}/nao-lido', [PrmAlertaController::class, 'marcarNaoLido']); 
});

// ============================================================
// VEÍCULOS
// ============================================================
Route::prefix('veiculos')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',      [InatroVeiculoController::class, 'index']); 
    Route::post('/',     [InatroVeiculoController::class, 'store']); 
    Route::get('/{id}',  [InatroVeiculoController::class, 'show']); 
});

// ============================================================
// CATÁLOGOS (público INATRO)
// ============================================================
Route::get('/catalogos', [InatroCatalogoController::class, 'index'])->middleware('throttle:api'); 

// ============================================================
// INSPEÇÕES
// ============================================================
Route::prefix('inspecoes')->middleware('throttle:api')->group(function () {
    Route::get('/',          [InatroInspecaoController::class, 'index']); 
    Route::get('/kpis',      [InatroInspecaoController::class, 'kpis']); 
    Route::get('/checklist', [InatroInspecaoController::class, 'checklist']); 
    Route::post('/',         [InatroInspecaoController::class, 'store']); 
    Route::get('/{id}',      [InatroInspecaoController::class, 'show']); 
});
Route::get('/checklist', [ChecklistController::class, 'index'])->middleware('throttle:api');

// ============================================================
// AGENDAMENTOS
// ============================================================
Route::prefix('agendamentos')->middleware('throttle:api')->group(function () {
    Route::get('/',               [InatroAgendamentoController::class, 'index']); 
    Route::get('/kpis',           [InatroAgendamentoController::class, 'kpis']); 
    Route::post('/',              [InatroAgendamentoController::class, 'store']); 
    Route::get('/{id}',           [InatroAgendamentoController::class, 'show']); 
    Route::put('/{id}',           [InatroAgendamentoController::class, 'update']); 
    Route::delete('/{id}',        [InatroAgendamentoController::class, 'destroy']); 
    Route::put('/{id}/confirmar', [InatroAgendamentoController::class, 'confirmar']); 
    Route::put('/{id}/concluir',  [InatroAgendamentoController::class, 'concluir']); 
    Route::put('/{id}/cancelar',  [InatroAgendamentoController::class, 'cancelar']); 
});

// ============================================================
// EXPORTAÇÕES
// ============================================================
Route::prefix('exportacoes')->middleware('throttle:api')->group(function () {
    Route::get('/',        [InatroExportacaoController::class, 'index']); 
    Route::post('/',       [InatroExportacaoController::class, 'store']); 
    Route::post('/xlsx',   [InatroExportacaoController::class, 'gerarXlsx']); 
    Route::post('/pdf',    [InatroExportacaoController::class, 'gerarPdf']); 
    Route::delete('/{id}', [InatroExportacaoController::class, 'destroy']); 
});

// ============================================================
// RELATÓRIOS
// ============================================================
Route::prefix('relatorios')->middleware('throttle:api')->group(function () {
    Route::get('/inatro/dashboard', [RelatorioInatroController::class, 'dashboard']);
    Route::get('/prm',              [PrmRelatorioController::class, 'prm']); 
    Route::get('/chefe',      [ChefeRelatorioController::class, 'index']);
    Route::get('/chefe/pdf',  [ChefeRelatorioController::class, 'pdf']);
    Route::get('/chefe/xlsx', [ChefeRelatorioController::class, 'xlsx']);
    Route::prefix('issm')->group(function () {
        Route::get('/',     [IssmRelatorioController::class, 'index']);
        Route::get('/xlsx', [IssmRelatorioController::class, 'xlsx']);
        Route::get('/pdf',  [IssmRelatorioController::class, 'pdf']);
    });
    Route::get('/gestmul',      [GestmulRelatorioController::class, 'index']);
    Route::get('/gestmul/xlsx', [GestmulRelatorioController::class, 'xlsx']);
    Route::get('/gestmul/pdf',  [GestmulRelatorioController::class, 'pdf']);
});

// ============================================================
// PRM — POSTOS
// ============================================================
Route::get('/prm/postos', [PrmPostoController::class, 'index'])
    ->middleware(['auth:sanctum', 'throttle:api']);

// ============================================================
// CHEFE — AGENTES, ESCALAS, PATRULHAS...
// ============================================================
Route::prefix('chefe/agentes')->middleware('throttle:api')->group(function () {
    Route::get('/',                [ChefeAgenteController::class, 'index']);
    Route::post('/',               [ChefeAgenteController::class, 'store']);
    Route::get('/{id}',            [ChefeAgenteController::class, 'show']);
    Route::put('/{id}',            [ChefeAgenteController::class, 'update']);
    Route::delete('/{id}',         [ChefeAgenteController::class, 'destroy']);
    Route::put('/{id}/activar',    [ChefeAgenteController::class, 'activar']);
    Route::put('/{id}/desactivar', [ChefeAgenteController::class, 'desactivar']);
});

Route::prefix('chefe/escalas')->middleware('throttle:api')->group(function () {
    Route::get('/',              [ChefeEscalaController::class, 'index']);
    Route::post('/',             [ChefeEscalaController::class, 'store']);
    Route::get('/xlsx',          [ChefeEscalaController::class, 'xlsx']);
    Route::get('/pdf',           [ChefeEscalaController::class, 'pdf']);
    Route::get('/{id}',          [ChefeEscalaController::class, 'show']);
    Route::put('/{id}',          [ChefeEscalaController::class, 'update']);
    Route::delete('/{id}',       [ChefeEscalaController::class, 'destroy']);
    Route::put('/{id}/publicar', [ChefeEscalaController::class, 'publicar']);
});

Route::prefix('chefe/patrulhas')->middleware('throttle:api')->group(function () {
    Route::get('/',              [ChefePatrulhaController::class, 'index']);
    Route::post('/',             [ChefePatrulhaController::class, 'store']);
    Route::get('/xlsx',          [ChefePatrulhaController::class, 'xlsx']);
    Route::get('/pdf',           [ChefePatrulhaController::class, 'pdf']);
    Route::get('/{id}',          [ChefePatrulhaController::class, 'show']);
    Route::put('/{id}',          [ChefePatrulhaController::class, 'update']);
    Route::delete('/{id}',       [ChefePatrulhaController::class, 'destroy']);
    Route::put('/{id}/iniciar',  [ChefePatrulhaController::class, 'iniciar']);
    Route::put('/{id}/concluir', [ChefePatrulhaController::class, 'concluir']);
    Route::put('/{id}/cancelar', [ChefePatrulhaController::class, 'cancelar']);
});

Route::prefix('chefe/veiculos-criticos')->middleware('throttle:api')->group(function () {
    Route::get('/',                        [ChefeVeiculoCriticoController::class, 'index']);
    Route::post('/',                       [ChefeVeiculoCriticoController::class, 'store']);
    Route::get('/xlsx',                    [ChefeVeiculoCriticoController::class, 'xlsx']);
    Route::get('/pdf',                     [ChefeVeiculoCriticoController::class, 'pdf']);
    Route::get('/{id}',                    [ChefeVeiculoCriticoController::class, 'show']);
    Route::put('/{id}',                    [ChefeVeiculoCriticoController::class, 'update']);
    Route::delete('/{id}',                 [ChefeVeiculoCriticoController::class, 'destroy']);
    Route::put('/{id}/abater-hotlist',     [ChefeVeiculoCriticoController::class, 'abaterHotlist']);
    Route::put('/{id}/regularizar-seguro', [ChefeVeiculoCriticoController::class, 'regularizarSeguro']);
});

Route::prefix('chefe/aprovacoes')->middleware('throttle:api')->group(function () {
    Route::get('/',              [ChefeAprovacaoController::class, 'index']);
    Route::get('/{id}',          [ChefeAprovacaoController::class, 'show']);
    Route::put('/{id}/aprovar',  [ChefeAprovacaoController::class, 'aprovar']);
    Route::put('/{id}/rejeitar', [ChefeAprovacaoController::class, 'rejeitar']);
});

// ============================================================
// ISSM
// ============================================================
Route::prefix('issm/apolices')->middleware('throttle:api')->group(function () {
    Route::get('/',        [IssmApoliceController::class, 'index']);
    Route::post('/',       [IssmApoliceController::class, 'store']);
    Route::get('/xlsx',    [IssmApoliceController::class, 'xlsx']);
    Route::get('/pdf',     [IssmApoliceController::class, 'pdf']);
    Route::get('/{id}',    [IssmApoliceController::class, 'show']);
    Route::put('/{id}',    [IssmApoliceController::class, 'update']);
    Route::delete('/{id}', [IssmApoliceController::class, 'destroy']);
});

Route::prefix('issm/sinistros')->middleware('throttle:api')->group(function () {
    Route::get('/',                [IssmSinistroController::class, 'index']);
    Route::post('/',               [IssmSinistroController::class, 'store']);
    Route::get('/xlsx',            [IssmSinistroController::class, 'xlsx']);
    Route::get('/pdf',             [IssmSinistroController::class, 'pdf']);
    Route::get('/{id}',            [IssmSinistroController::class, 'show']);
    Route::put('/{id}',            [IssmSinistroController::class, 'update']);
    Route::delete('/{id}',         [IssmSinistroController::class, 'destroy']);
    Route::put('/{id}/em-analise', [IssmSinistroController::class, 'marcarEmAnalise']);
    Route::put('/{id}/resolvido',  [IssmSinistroController::class, 'marcarResolvido']);
    Route::put('/{id}/recusado',   [IssmSinistroController::class, 'recusar']);
});

Route::get('/issm/validacao/{matricula}', [IssmValidacaoController::class, 'consultar'])
    ->where('matricula', '[A-Za-z0-9\-]+')
    ->middleware('throttle:api');

Route::get('/issm/catalogos', [IssmCatalogoController::class, 'index'])->middleware('throttle:api');

Route::prefix('issm/seguradoras')->middleware('throttle:api')->group(function () {
    Route::get('/',                [IssmSeguradoraController::class, 'index']);
    Route::post('/',               [IssmSeguradoraController::class, 'store']);
    Route::get('/xlsx',            [IssmSeguradoraController::class, 'xlsx']);
    Route::get('/pdf',             [IssmSeguradoraController::class, 'pdf']);
    Route::get('/{id}',            [IssmSeguradoraController::class, 'show']);
    Route::put('/{id}',            [IssmSeguradoraController::class, 'update']);
    Route::delete('/{id}',         [IssmSeguradoraController::class, 'destroy']);
    Route::put('/{id}/activar',    [IssmSeguradoraController::class, 'activar']);
    Route::put('/{id}/suspender',  [IssmSeguradoraController::class, 'suspender']);
    Route::put('/{id}/inactivar',  [IssmSeguradoraController::class, 'inactivar']);
});

// ============================================================
// GESTMUL
// ============================================================
Route::prefix('gestmul/contestacoes')->middleware('throttle:api')->group(function () {
    Route::get('/',                [GestmulContestacaoController::class, 'index']);
    Route::post('/',               [GestmulContestacaoController::class, 'store']);
    Route::get('/xlsx',            [GestmulContestacaoController::class, 'xlsx']);
    Route::get('/pdf',             [GestmulContestacaoController::class, 'pdf']);
    Route::get('/{id}',            [GestmulContestacaoController::class, 'show']);
    Route::put('/{id}',            [GestmulContestacaoController::class, 'update']);
    Route::delete('/{id}',         [GestmulContestacaoController::class, 'destroy']);
    Route::put('/{id}/em-analise', [GestmulContestacaoController::class, 'marcarEmAnalise']);
    Route::put('/{id}/cancelar',   [GestmulContestacaoController::class, 'cancelar']);
    Route::post('/{id}/decidir',   [GestmulContestacaoController::class, 'decidir']);
});

Route::prefix('gestmul/multas')->middleware('throttle:api')->group(function () {
    Route::get('/',                [GestmulMultaController::class, 'index']);
    Route::get('/xlsx',            [GestmulMultaController::class, 'xlsx']);
    Route::get('/{id}',            [GestmulMultaController::class, 'show']);
    Route::put('/{id}',            [GestmulMultaController::class, 'update']);
    Route::delete('/{id}',         [GestmulMultaController::class, 'destroy']);
    Route::put('/{id}/contestar',  [GestmulMultaController::class, 'contestar']);
    Route::put('/{id}/anular',     [GestmulMultaController::class, 'anular']);
    Route::post('/{id}/pagar',     [GestmulMultaController::class, 'pagar']);
    Route::get('/{id}/recibo/pdf', [GestmulMultaController::class, 'reciboPdf']);
});

Route::prefix('gestmul/pagamentos')->middleware('throttle:api')->group(function () {
    Route::get('/',                [GestmulPagamentoController::class, 'index']);
    Route::post('/',               [GestmulPagamentoController::class, 'store']);
    Route::get('/xlsx',            [GestmulPagamentoController::class, 'xlsx']);
    Route::get('/{id}',            [GestmulPagamentoController::class, 'show']);
    Route::put('/{id}',            [GestmulPagamentoController::class, 'update']);
    Route::delete('/{id}',         [GestmulPagamentoController::class, 'destroy']);
    Route::put('/{id}/confirmar',  [GestmulPagamentoController::class, 'confirmar']);
    Route::put('/{id}/anular',     [GestmulPagamentoController::class, 'anular']);
    Route::get('/{id}/recibo/pdf', [GestmulPagamentoController::class, 'reciboPdf']);
});

// ============================================================
// ADMIN
// ============================================================
Route::prefix('admin/monitorizacao')->middleware('throttle:api')->group(function () {
    Route::get('/',                              [AdminMonitorizacaoController::class, 'index']);
    Route::put('/alertas/{id}/silenciar',        [AdminMonitorizacaoController::class, 'silenciarAlerta']);
    Route::delete('/alertas/silenciados',        [AdminMonitorizacaoController::class, 'limparSilenciados']);
    Route::post('/integracoes/{nome}/reiniciar', [AdminMonitorizacaoController::class, 'reiniciarIntegracao']);
});

Route::prefix('admin/utilizadores')->middleware('throttle:api')->group(function () {
    Route::get('/',              [AdminUtilizadorController::class, 'index']);
    Route::post('/',             [AdminUtilizadorController::class, 'store']);
    Route::get('/xlsx',          [AdminUtilizadorController::class, 'xlsx']);
    Route::get('/{id}',          [AdminUtilizadorController::class, 'show']);
    Route::put('/{id}',          [AdminUtilizadorController::class, 'update']);
    Route::delete('/{id}',       [AdminUtilizadorController::class, 'destroy']);
    Route::put('/{id}/bloquear', [AdminUtilizadorController::class, 'bloquear']);
    Route::put('/{id}/activar',  [AdminUtilizadorController::class, 'activar']);
    Route::put('/{id}/senha',    [AdminUtilizadorController::class, 'alterarSenha']);
});

Route::prefix('admin/perfis')->middleware('throttle:api')->group(function () {
    Route::get('/',              [AdminPerfilController::class, 'index']);
    Route::post('/',             [AdminPerfilController::class, 'store']);
    Route::get('/xlsx',          [AdminPerfilController::class, 'xlsx']);
    Route::get('/{id}',          [AdminPerfilController::class, 'show']);
    Route::put('/{id}',          [AdminPerfilController::class, 'update']);
    Route::delete('/{id}',       [AdminPerfilController::class, 'destroy']);
    Route::post('/{id}/clonar',  [AdminPerfilController::class, 'clonar']);
    Route::put('/{id}/bloquear', [AdminPerfilController::class, 'bloquear']);
    Route::put('/{id}/activar',  [AdminPerfilController::class, 'activar']);
});

Route::prefix('admin/postos')->middleware('throttle:api')->group(function () {
    Route::get('/',        [AdminPostoController::class, 'index']);
    Route::post('/',       [AdminPostoController::class, 'store']);
    Route::get('/xlsx',    [AdminPostoController::class, 'xlsx']);
    Route::get('/{id}',    [AdminPostoController::class, 'show']);
    Route::put('/{id}',    [AdminPostoController::class, 'update']);
    Route::delete('/{id}', [AdminPostoController::class, 'destroy']);
});

Route::prefix('admin/dispositivos')->middleware('throttle:api')->group(function () {
    Route::get('/',                       [AdminDispositivoController::class, 'index']);
    Route::post('/',                      [AdminDispositivoController::class, 'store']);
    Route::get('/xlsx',                   [AdminDispositivoController::class, 'xlsx']);
    Route::get('/{id}',                   [AdminDispositivoController::class, 'show']);
    Route::put('/{id}',                   [AdminDispositivoController::class, 'update']);
    Route::delete('/{id}',                [AdminDispositivoController::class, 'destroy']);
    Route::put('/{id}/actualizar-versao', [AdminDispositivoController::class, 'marcarAtualizado']);
    Route::put('/{id}/desassociar',       [AdminDispositivoController::class, 'desassociar']);
});

Route::prefix('admin/integracoes')->middleware('throttle:api')->group(function () {
    Route::get('/', [AdminIntegracaoController::class, 'index']);
});

Route::prefix('admin/relatorios/frota')->middleware('throttle:api')->group(function () {
    Route::get('/',     [AdminRelatoriosFrotaController::class, 'index']);
    Route::get('/xlsx', [AdminRelatoriosFrotaController::class, 'xlsx']);
    Route::get('/pdf',  [AdminRelatoriosFrotaController::class, 'pdf']);
});

Route::prefix('admin/backups')->middleware('throttle:api')->group(function () {
    Route::get('/',               [AdminBackupController::class, 'index']);
    Route::post('/',              [AdminBackupController::class, 'store']);
    Route::delete('/{id}',        [AdminBackupController::class, 'destroy']);
    Route::put('/{id}/restaurar', [AdminBackupController::class, 'restaurar']);
    Route::get('/{id}/download',  [AdminBackupController::class, 'download']);
    Route::put('/tipos/{key}',    [AdminBackupController::class, 'actualizarTipo']);
});

Route::prefix('admin/relatorios/operacoes')->middleware('throttle:api')->group(function () {
    Route::get('/',     [AdminRelatoriosOperacoesController::class, 'index']);
    Route::get('/xlsx', [AdminRelatoriosOperacoesController::class, 'xlsx']);
    Route::get('/pdf',  [AdminRelatoriosOperacoesController::class, 'pdf']);
});

// ============================================================
// PORTAL DO CIDADÃO
// ============================================================
Route::prefix('portal')->middleware('throttle:api')->group(function () {
    Route::get('/noticias',        [PortalCidadaoController::class, 'noticias']);
    Route::get('/cartas/{numero}', [PortalCidadaoController::class, 'consultarCarta'])
        ->where('numero', '[0-9]{9}');
    Route::get('/faq',             [PortalCidadaoController::class, 'faq']);
    Route::post('/chat',           [PortalChatController::class, 'responder']);

    Route::get('/meus-veiculos', [PortalCidadaoController::class, 'meusVeiculos'])
        ->middleware('auth:sanctum');
});

// ============================================================
// AGENTE
// ============================================================
Route::prefix('agente')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/inicio',                       [AgenteInicioController::class, 'index']);
    Route::post('/consultas/registar',          [AgenteInicioController::class, 'registarConsulta']);
    Route::get('/consulta/{matricula}',         [AgenteConsultaController::class, 'consultar']);
    Route::get('/consulta/{matricula}/detalhe', [AgenteConsultaController::class, 'detalheCompleto']);
});

Route::prefix('agente/autos')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',              [AgenteAutoController::class, 'index']);
    Route::post('/',             [AgenteAutoController::class, 'store']);
    Route::get('/{id}',          [AgenteAutoController::class, 'show']);
    Route::put('/{id}/imprimir', [AgenteAutoController::class, 'imprimir']);
});

Route::prefix('agente/historico')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/',     [AgenteHistoricoController::class, 'index']);
    Route::post('/',    [AgenteHistoricoController::class, 'store']);
    Route::get('/xlsx', [AgenteHistoricoController::class, 'xlsx']);
});
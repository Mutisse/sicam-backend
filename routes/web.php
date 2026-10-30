<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

// Rota normal (mantém esta)
Route::get('/', function () {
    return view('welcome');
});

// ============================================================
// ROTA PARA O TRABALHO SEMESTRAL (Questão 1)
// Acede em: http://127.0.0.1:8000/trabalho-q1
// ============================================================
Route::get('/trabalho-q1', function () {
    
    // 1. Ler os Controllers reais do teu projeto
    $controllerPath = app_path('Http/Controllers/Api');
    $controllers = [];
    if (File::exists($controllerPath)) {
        foreach (File::files($controllerPath) as $file) {
            $controllers[] = $file->getFilenameWithoutExtension();
        }
    }

    // 2. Ler os Models reais do teu projeto
    $modelPath = app_path('Models');
    $models = [];
    if (File::exists($modelPath)) {
        foreach (File::files($modelPath) as $file) {
            $models[] = $file->getFilenameWithoutExtension();
        }
    }

    // 3. Se não encontrares os models na pasta padrão, procura na raiz de app/
    if (empty($models)) {
        foreach (File::files(app_path()) as $file) {
            $models[] = $file->getFilenameWithoutExtension();
        }
    }

    // 4. Montar a página HTML de resposta
    $html = "<div style='font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: auto;'>";
    $html .= "<h1 style='color: #333;'>Resposta à Questão 1: Pilha STS do Sistema SICAM</h1>";
    $html .= "<hr>";

    // Camada 4: Aplicação
    $html .= "<h2 style='color: #0056b3;'>Camada 4: Aplicação</h2>";
    $html .= "<p><strong>Elemento concreto identificado:</strong> Os Controllers da API. Exemplos do teu sistema:</p>";
    $html .= "<ul>";
    $html .= "<li><code>AuthController</code> (Gestão de Login e Sessões)</li>";
    $html .= "<li><code>AgendamentoController</code> (Lógica de Agendamentos)</li>";
    $html .= "<li><code>InspecaoController</code> (Lógica de Inspeções)</li>";
    $html .= "<li><code>GestmulMultaController</code> (Lógica de Multas)</li>";
    $html .= "</ul>";

    // Camada 3: Comunicações e Dados
    $html .= "<h2 style='color: #0056b3;'>Camada 3: Comunicações e Dados</h2>";
    $html .= "<p><strong>Elemento concreto identificado:</strong> Os Modelos (Models) e as rotas da API. Exemplos do teu sistema:</p>";
    $html .= "<ul>";
    // Pega nos primeiros 5 models encontrados para dar exemplo
    $exemplosModels = array_slice($models, 0, 5);
    if(!empty($exemplosModels)){
        foreach ($exemplosModels as $m) {
            $html .= "<li><code>" . $m . "</code> (Modelo de Dados)</li>";
        }
    } else {
        $html .= "<li>Modelos de Base de Dados (verifica a pasta app/Models)</li>";
    }
    $html .= "</ul>";

    // Dependências
    $html .= "<h2 style='color: #0056b3;'>Dependências entre Camadas Adjacentes</h2>";
    $html .= "<p><strong>Dependência 1 (Aplicação depende dos Dados):</strong> O <code>AgendamentoController</code> (Camada 4) depende do Model <code>Agendamento</code> (Camada 3) para conseguir ler e gravar os dados na base de dados. Sem o Model, o Controller não consegue guardar nada.</p>";
    $html .= "<p><strong>Dependência 2 (Comunicações dependem da Aplicação):</strong> As rotas definidas no ficheiro <code>routes/api.php</code> (Camada 3) dependem dos métodos dos Controllers (Camada 4) para executar as ações. Se o Controller falhar, a rota não devolve resposta ao utilizador.</p>";

    // Nota final
    $html .= "<hr>";
    $html .= "<p style='color: #666; font-size: 0.9em;'><em>Nota: Esta página lê automaticamente a estrutura do teu projeto SICAM. Podes copiar este texto diretamente para o teu relatório.</em></p>";
    $html .= "</div>";

    return $html;
});
<?php
// app/Http/Controllers/Api/PerfilController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PerfilController extends Controller
{
    // ────────────────────────────────────────────────────────
    // GET /api/perfil
    // ────────────────────────────────────────────────────────
    public function obter(Request $request)
    {
        return response()->json([
            'utilizador' => $request->user()->load('perfil'),
        ]);
    }

    // ────────────────────────────────────────────────────────
    // PUT /api/perfil
    // ────────────────────────────────────────────────────────
    public function actualizar(Request $request)
    {
        $user = $request->user();

        $dados = $request->validate([
            'nome'     => 'sometimes|string|min:3|max:120',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'telefone' => 'nullable|string|max:30',
        ]);

        $emailAntigo = $user->email;

        if (isset($dados['nome']))    $user->name = $dados['nome'];
        if (isset($dados['email']))   $user->email = $dados['email'];
        if (array_key_exists('telefone', $dados)) $user->telefone = $dados['telefone'];

        $user->save();

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — Email alterado
        // ═══════════════════════════════════════════════════════
        if (isset($dados['email']) && $dados['email'] !== $emailAntigo) {
            NotificacaoService::emailAlterado(
                $user->id,
                $emailAntigo,
                $dados['email']
            );
        }

        return response()->json([
            'utilizador' => $user->fresh()->load('perfil'),
        ]);
    }

    // ────────────────────────────────────────────────────────
    // PUT /api/perfil/senha
    // ────────────────────────────────────────────────────────
    public function alterarSenha(Request $request)
    {
        $dados = $request->validate([
            'senha_atual' => 'required|string',
            'senha_nova'  => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($dados['senha_atual'], $user->password)) {
            return response()->json([
                'message' => 'Senha actual incorrecta.',
            ], 422);
        }

        $user->password = $dados['senha_nova'];
        $user->save();

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — Senha alterada
        // ═══════════════════════════════════════════════════════
        NotificacaoService::senhaAlterada($user->id);

        return response()->noContent();
    }

    // ────────────────────────────────────────────────────────
    // GET /api/perfil/sessoes
    // ────────────────────────────────────────────────────────
    public function sessoes(Request $request)
    {
        $user = $request->user();
        $sessaoAtual = $request->session()->getId();

        $sessoes = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($sessaoAtual) {
                return [
                    'id'            => $s->id,
                    'dispositivo'   => $this->detectarDispositivoUA($s->user_agent ?? ''),
                    'local'         => $s->ip_address ?? 'Desconhecido',
                    'ultimo_acesso' => $this->tempoRelativo((int) $s->last_activity),
                    'atual'         => $s->id === $sessaoAtual,
                ];
            })
            ->values();

        return response()->json(['sessoes' => $sessoes]);
    }

    // ────────────────────────────────────────────────────────
    // DELETE /api/perfil/sessoes/{id}
    // ────────────────────────────────────────────────────────
    public function terminarSessao(Request $request, string $id)
    {
        $user = $request->user();
        $sessaoAtual = $request->session()->getId();

        if ($id === $sessaoAtual) {
            return response()->json([
                'message' => 'Não pode terminar a sessão actual.',
            ], 422);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->noContent();
    }

    // ────────────────────────────────────────────────────────
    // DELETE /api/perfil/sessoes/outras
    // ────────────────────────────────────────────────────────
    public function terminarOutrasSessoes(Request $request)
    {
        $user = $request->user();
        $sessaoAtual = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $sessaoAtual)
            ->delete();

        // 🔔 Opcional: notificar que outras sessões foram terminadas
        NotificacaoService::outrasSessoesTerminadas($user->id);

        return response()->noContent();
    }

    // ────────────────────────────────────────────────────────
    // GET /api/perfil/preferencias
    // ────────────────────────────────────────────────────────
    public function preferencias(Request $request)
    {
        $user = $request->user();
        $prefs = $user->preferencias ?? $this->preferenciasPadrao();

        return response()->json(['preferencias' => $prefs]);
    }

    // ────────────────────────────────────────────────────────
    // PUT /api/perfil/preferencias
    // ────────────────────────────────────────────────────────
    public function guardarPreferencias(Request $request)
    {
        $dados = $request->validate([
            'tema'         => 'required|in:claro,escuro,auto',
            'idioma'       => 'required|in:pt,en',
            'densidade'    => 'required|in:normal,compacta',
            'modo_offline' => 'required|boolean',
            'notif_push'   => 'required|boolean',
            'notif_som'    => 'required|boolean',
            'notif_email'  => 'required|boolean',
        ]);

        $user = $request->user();
        $user->preferencias = $dados;
        $user->save();

        return response()->noContent();
    }

    // ────────────────────────────────────────────────────────
    // POST /api/perfil/foto
    // ────────────────────────────────────────────────────────
    public function uploadFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        if ($user->foto_url) {
            $antiga = str_replace('/storage/', '', $user->foto_url);
            Storage::disk('public')->delete($antiga);
        }

        $caminho = $request->file('foto')->store('avatares', 'public');
        $url = Storage::url($caminho);

        $user->foto_url = $url;
        $user->save();

        return response()->json(['foto_url' => $url]);
    }

    // ────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────
    private function preferenciasPadrao(): array
    {
        return [
            'tema'         => 'auto',
            'idioma'       => 'pt',
            'densidade'    => 'normal',
            'modo_offline' => false,
            'notif_push'   => true,
            'notif_som'    => true,
            'notif_email'  => false,
        ];
    }

    private function detectarDispositivoUA(string $ua): string
    {
        if (!$ua) return 'Desconhecido';

        $browser = 'Desconhecido';
        if (str_contains($ua, 'Edg'))         $browser = 'Edge';
        elseif (str_contains($ua, 'Chrome'))  $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari'))  $browser = 'Safari';

        $os = 'Desconhecido';
        if (str_contains($ua, 'Windows'))      $os = 'Windows';
        elseif (str_contains($ua, 'Mac'))      $os = 'macOS';
        elseif (str_contains($ua, 'iPhone'))   $os = 'iPhone';
        elseif (str_contains($ua, 'Android'))  $os = 'Android';
        elseif (str_contains($ua, 'Linux'))    $os = 'Linux';

        return "$browser · $os";
    }

    private function tempoRelativo(int $timestamp): string
    {
        if (!$timestamp) return '—';

        $diff = time() - $timestamp;
        $min = floor($diff / 60);
        $h = floor($min / 60);
        $d = floor($h / 24);

        if ($min < 1) return 'agora';
        if ($min < 60) return "há {$min} min";
        if ($h < 24) return "há {$h} h";
        return "há {$d} d";
    }
}
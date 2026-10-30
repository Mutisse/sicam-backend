<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'senha' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->senha, $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        if ($user->estado === 'Bloqueado') {
            return response()->json([
                'message' => 'Conta bloqueada. Contacte o administrador.',
            ], 403);
        }

        $ip        = $request->ip();
        $userAgent = substr($request->userAgent() ?? 'desconhecido', 0, 100);
        $sessionId = $request->session()->getId();

        // ═══════════════════════════════════════════════════════
        // ⭐ Detectar se é login de NOVO dispositivo
        //    (antes de gravar a sessão atual)
        // ═══════════════════════════════════════════════════════
        $dispositivoConhecido = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $sessionId)
            ->where(function ($q) use ($ip, $userAgent) {
                $q->where('ip_address', $ip)
                  ->orWhere('user_agent', 'like', "%{$userAgent}%");
            })
            ->exists();

        // ⭐ Autenticar
        Auth::login($user);
        $request->session()->regenerate();

        // ⭐ Forçar user_id na sessão
        $request->session()->put('user_id', $user->id);

        // ⭐ Gravar user_id na tabela `sessions` (com IP e UA)
        DB::table('sessions')
            ->where('id', $sessionId)
            ->update([
                'user_id'       => $user->id,
                'ip_address'    => $ip,
                'user_agent'    => $userAgent,
                'last_activity' => time(),
            ]);

        // ⭐ Atualizar `ultimo_acesso` do user
        $user->update(['ultimo_acesso' => now()]);

        // ═══════════════════════════════════════════════════════
        // 🔔 GATILHO — Login de novo dispositivo
        // ═══════════════════════════════════════════════════════
        if (!$dispositivoConhecido) {
            NotificacaoService::loginNovoDispositivo(
                $user->id,
                $ip,
                $userAgent
            );
        }

        return response()->json([
            'utilizador' => $user->fresh()->load('perfil'),
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return response()->json([
            'utilizador' => $request->user()->load('perfil'),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $sessionId = $request->session()->getId();

        DB::table('sessions')->where('id', $sessionId)->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
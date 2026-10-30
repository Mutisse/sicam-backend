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

        // ═══════════════════════════════════════════════════════
        // ⭐ Detectar se é login de NOVO dispositivo
        // ═══════════════════════════════════════════════════════
        $dispositivoConhecido = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where(function ($q) use ($ip, $userAgent) {
                $q->where('ip_address', $ip)
                  ->orWhere('user_agent', 'like', "%{$userAgent}%");
            })
            ->exists();

        // ⭐ Criar token Sanctum (Bearer) — funciona entre domínios
        $token = $user->createToken('sicam-token')->plainTextToken;

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
            'token'      => $token,
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
        // Apagar o token atual
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->noContent();
    }
}
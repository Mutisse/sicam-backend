<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'senha' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->senha, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if ($user->estado === 'Bloqueado') {
            return response()->json(['message' => 'Conta bloqueada. Contacte o administrador.'], 403);
        }

        $token = $user->createToken('sicam-token')->plainTextToken;
        $user->update(['ultimo_acesso' => now()]);

        return response()->json([
            'utilizador' => $this->prepararUtilizador($user),
            'token'      => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'utilizador' => $this->prepararUtilizador($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        return response()->noContent();
    }

    private function prepararUtilizador(User $user): array
    {
        $user->load(['perfil.permissoes']);
        $userData = $user->toArray();
        
        unset($userData['perfil']);
        unset($userData['perfil_id']);

        $permissoes = [];
        
        // ⭐ O Backend SÓ envia o que o utilizador tem permissão para VER
        if ($user->perfil && $user->perfil->permissoes) {
            foreach ($user->perfil->permissoes as $p) {
                if ($p->ver) {
                    $permissoes[$p->modulo] = [
                        'ver'    => (bool) $p->ver,
                        'criar'  => (bool) $p->criar,
                        'editar' => (bool) $p->editar,
                        'apagar' => (bool) $p->apagar,
                    ];
                }
            }
        }

        $userData['permissoes'] = $permissoes;
        $userData['perfil_label'] = $user->perfil->label ?? 'Utilizador';
        $userData['perfil_key'] = $user->perfil->key ?? 'hidden';

        return $userData;
    }
}
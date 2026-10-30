<?php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificacaoService
{
    /**
     * ═══════════════════════════════════════════════════════
     * EVENTOS DO SISTEMA
     * ═══════════════════════════════════════════════════════
     */

    /**
     * 🔴 Abordagem resultou em detenção
     */
    public static function abordagemComDetencao(
        string $codigoAbordagem,
        string $matricula,
        string $posto,
        string $local
    ): void {
        $destinatarios = self::utilizadoresPorPerfil(['prm', 'chefe']);

        Notificacao::criarMultiplas(
            $destinatarios,
            'Veículo detido em ' . $posto,
            "Abordagem {$codigoAbordagem} resultou em detenção do veículo {$matricula} em {$local}.",
            'critico',
            'abordagem',
            $matricula
        );

        self::log('abordagem.detencao', $matricula, count($destinatarios));
    }

    /**
     * 🔴 Veículo da hotlist foi abordado
     */
    public static function veiculoHotlistAbordado(
        string $matricula,
        string $local,
        string $resultado
    ): void {
        $destinatarios = self::utilizadoresPorPerfil(['prm']);

        Notificacao::criarMultiplas(
            $destinatarios,
            'Veículo da hotlist abordado!',
            "Veículo {$matricula} (hotlist) foi abordado em {$local}. Resultado: {$resultado}.",
            'critico',
            'hotlist',
            $matricula
        );

        self::log('hotlist.abordado', $matricula, count($destinatarios));
    }

    /**
     * 🔴 Veículo adicionado à hotlist
     */
    public static function veiculoAdicionadoHotlist(
        string $matricula,
        string $motivo,
        string $perigo
    ): void {
        $destinatarios = self::utilizadoresPorPerfil(['prm', 'chefe']);

        Notificacao::criarMultiplas(
            $destinatarios,
            'Novo veículo na hotlist',
            "Veículo {$matricula} adicionado à hotlist. Motivo: {$motivo}. Perigo: {$perigo}.",
            $perigo === 'alto' ? 'critico' : 'aviso',
            'hotlist',
            $matricula
        );

        self::log('hotlist.novo', $matricula, count($destinatarios));
    }

    /**
     * 🔴 Veículo resolvido (removido da hotlist)
     */
    public static function veiculoResolvido(
        string $matricula,
        string $motivo
    ): void {
        $destinatarios = self::utilizadoresPorPerfil(['prm', 'chefe']);

        Notificacao::criarMultiplas(
            $destinatarios,
            'Veículo removido da hotlist',
            "Veículo {$matricula} foi resolvido. Motivo original: {$motivo}.",
            'info',
            'hotlist',
            $matricula
        );

        self::log('hotlist.resolvido', $matricula, count($destinatarios));
    }

    /**
     * 🔴 Multa emitida
     */
    public static function multaEmitida(
        int $userIdProprietario,
        string $matricula,
        string $infracao,
        float $valor,
        string $numeroMulta
    ): void {
        if (!$userIdProprietario) return;

        Notificacao::criarMultiplas(
            [$userIdProprietario],
            'Nova multa emitida',
            "Foi emitida a multa {$numeroMulta} por {$infracao} no veículo {$matricula}. Valor: " . number_format($valor, 2, ',', '.') . " MT.",
            'critico',
            'multa',
            $matricula
        );

        self::log('multa.emitida', $numeroMulta, 1);
    }

    /**
     * 🔴 Falha numa integração
     */
    public static function falhaIntegracao(
        string $servico,
        string $erro
    ): void {
        $destinatarios = self::utilizadoresPorPerfil(['admin']);

        Notificacao::criarMultiplas(
            $destinatarios,
            "Falha na integração {$servico}",
            "Erro detectado: {$erro}.",
            'erro',
            'sistema',
            $servico
        );

        self::log('sistema.falha', $servico, count($destinatarios));
    }

    /**
     * 🟡 Login de novo dispositivo
     */
    public static function loginNovoDispositivo(
        int $userId,
        string $ip,
        string $userAgent
    ): void {
        Notificacao::criarMultiplas(
            [$userId],
            'Login de novo dispositivo',
            "Foi detectado um novo acesso à sua conta a partir de {$ip} ({$userAgent}).",
            'aviso',
            'seguranca',
            null
        );

        self::log('seguranca.login', "user:{$userId}", 1);
    }

    /**
     * 🟡 Senha alterada
     */
    public static function senhaAlterada(int $userId): void
    {
        Notificacao::criarMultiplas(
            [$userId],
            'Senha alterada',
            'A sua senha foi alterada com sucesso. Se não foi você, contacte o administrador.',
            'aviso',
            'seguranca',
            null
        );

        self::log('seguranca.senha', "user:{$userId}", 1);
    }

    /**
     * 🟡 Email alterado
     */
    public static function emailAlterado(
        int $userId,
        string $emailAntigo,
        string $emailNovo
    ): void {
        Notificacao::criarMultiplas(
            [$userId],
            'Email alterado',
            "O seu email foi alterado de {$emailAntigo} para {$emailNovo}. Se não foi você, contacte o administrador.",
            'aviso',
            'seguranca',
            null
        );

        self::log('seguranca.email', "user:{$userId}", 1);
    }

    /**
     * 🟡 Outras sessões terminadas
     */
    public static function outrasSessoesTerminadas(int $userId): void
    {
        Notificacao::criarMultiplas(
            [$userId],
            'Outras sessões encerradas',
            'Todas as suas outras sessões foram terminadas com sucesso.',
            'info',
            'seguranca',
            null
        );

        self::log('seguranca.sessoes', "user:{$userId}", 1);
    }

    /**
     * ═══════════════════════════════════════════════════════
     * HELPERS
     * ═══════════════════════════════════════════════════════
     */

    /**
     * Devolve os IDs dos utilizadores com os perfis indicados.
     */
    private static function utilizadoresPorPerfil(array $perfis): array
    {
        return User::whereHas('perfil', function ($q) use ($perfis) {
            $q->whereIn('key', $perfis);
        })->pluck('id')->toArray();
    }

    /**
     * Log simples para auditoria.
     */
    private static function log(string $evento, string $referencia, int $total): void
    {
        Log::info("[Notificacao] {$evento} → {$referencia} → {$total} destinatário(s)");
    }
}
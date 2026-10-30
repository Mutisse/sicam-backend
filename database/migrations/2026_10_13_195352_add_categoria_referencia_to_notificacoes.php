<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->string('categoria', 40)->nullable()->after('tipo');
            $table->string('referencia', 60)->nullable()->after('categoria');
            $table->index(['user_id', 'lida']);
        });
    }

    public function down(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'lida']);
            $table->dropColumn(['categoria', 'referencia']);
        });
    }
};
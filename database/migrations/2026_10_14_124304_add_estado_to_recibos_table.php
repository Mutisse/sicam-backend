<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->enum('estado', ['Confirmado', 'Pendente', 'Anulado'])
                ->default('Confirmado')
                ->after('valor');

            $table->text('observacoes')->nullable()->after('estado');

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropColumn(['estado', 'observacoes']);
        });
    }
};
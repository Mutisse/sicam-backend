<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspecoes', function (Blueprint $table) {
            if (Schema::hasColumn('inspecoes', 'itens')) {
                $table->dropColumn('itens');
            }
            $table->json('checklist')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('inspecoes', function (Blueprint $table) {
            if (Schema::hasColumn('inspecoes', 'checklist')) {
                $table->dropColumn('checklist');
            }
            $table->json('itens')->nullable()->after('observacoes');
        });
    }
};
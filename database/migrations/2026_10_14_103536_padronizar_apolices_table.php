<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apolices', function (Blueprint $table) {
            if (!Schema::hasColumn('apolices', 'segurado')) {
                $table->string('segurado', 120)->nullable()->after('numero');
            }
            if (!Schema::hasColumn('apolices', 'nif')) {
                $table->string('nif', 9)->nullable()->after('segurado');
            }
            if (!Schema::hasColumn('apolices', 'telefone')) {
                $table->string('telefone', 30)->nullable()->after('nif');
            }
            if (!Schema::hasColumn('apolices', 'email')) {
                $table->string('email', 120)->nullable()->after('telefone');
            }
            if (!Schema::hasColumn('apolices', 'premio')) {
                $table->unsignedBigInteger('premio')->default(0)->after('validade');
            }
            if (!Schema::hasColumn('apolices', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apolices', function (Blueprint $table) {
            $table->dropColumn([
                'segurado', 'nif', 'telefone', 'email', 'premio', 'observacoes',
            ]);
        });
    }
};
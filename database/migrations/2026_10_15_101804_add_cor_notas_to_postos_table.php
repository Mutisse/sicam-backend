<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postos', function (Blueprint $table) {
            $table->string('cor', 20)->default('#2563eb')->after('estado');
            $table->text('notas')->nullable()->after('cor');
        });
    }

    public function down(): void
    {
        Schema::table('postos', function (Blueprint $table) {
            $table->dropColumn(['cor', 'notas']);
        });
    }
};
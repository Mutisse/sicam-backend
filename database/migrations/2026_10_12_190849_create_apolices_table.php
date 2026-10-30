<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apolices', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();  // AP-001
            $table->unsignedBigInteger('veiculo_id');
            $table->string('seguradora', 120);
            $table->string('tipo', 40)->default('Terceiros'); // Terceiros | Contra-todos
            $table->date('inicio');
            $table->date('validade');
            $table->enum('estado', ['Activa', 'Expirada', 'Sinistro'])
                ->default('Activa');
            $table->timestamps();

            $table->index('veiculo_id');
            $table->index('estado');
            $table->index('validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apolices');
    }
};
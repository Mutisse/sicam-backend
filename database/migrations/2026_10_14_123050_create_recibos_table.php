<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique();

            $table->foreignId('multa_id')
                ->constrained('multas')
                ->cascadeOnDelete();

            $table->string('metodo', 30);
            $table->string('pagador', 160);
            $table->string('referencia', 120)->nullable();
            $table->decimal('valor', 12, 2);

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('multa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
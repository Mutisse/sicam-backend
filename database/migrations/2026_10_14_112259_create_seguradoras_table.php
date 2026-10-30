<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguradoras', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 20)->unique();
            $table->string('sigla', 20);
            $table->string('nome', 160);
            $table->string('nif', 9)->unique();

            $table->enum('categoria', ['Nacional', 'Internacional', 'Mútua'])
                ->default('Nacional');

            $table->string('email', 120);
            $table->string('telefone', 30);
            $table->string('website', 160)->nullable();

            $table->string('endereco', 200);
            $table->string('cidade', 120);
            $table->string('provincia', 120);

            $table->string('responsavel', 120);
            $table->string('contacto_responsavel', 30)->nullable();

            $table->unsignedInteger('numero_apoles')->default(0);
            $table->unsignedInteger('numero_sinistros')->default(0);

            $table->enum('estado', ['Activa', 'Suspensa', 'Inactiva'])
                ->default('Activa');

            $table->text('observacoes')->nullable();

            $table->timestamp('registada_em')->nullable();

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('estado');
            $table->index('categoria');
            $table->index('provincia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguradoras');
    }
};
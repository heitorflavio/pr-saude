<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cid10', function (Blueprint $table) {
            $table->char('codigo', 7)->primary();
            $table->string('descricao', 255);

            $table->index('descricao', 'ix_cid10_descricao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cid10');
    }
};

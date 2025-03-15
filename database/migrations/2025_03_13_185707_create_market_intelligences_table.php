<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_intelligences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('price_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_intelligences');
    }
};

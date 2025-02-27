<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_deck', function (Blueprint $table) {
            $table->id();
            $table->string('card_id');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreignId('deck_id')->constrained('decks')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_deck');
    }
};

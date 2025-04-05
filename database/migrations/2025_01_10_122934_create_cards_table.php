<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->string('id');
            $table->foreignId('deck_id')->constrained('decks');
            $table->string('type');
            $table->string('priority');
            $table->string('origin');
            $table->string('destination');
            $table->integer('quantity');
            $table->integer('revenue');
            $table->boolean('is_initial')->default(false);
            $table->string('generated_for_room_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'deck_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

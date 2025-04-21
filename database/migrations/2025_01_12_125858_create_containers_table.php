<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('card_id');
            $table->foreignId('deck_id');
            $table->string('color');
            $table->string('type');
            $table->string('last_processed_by')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->boolean('is_restowed')->default(false);
            $table->timestamps();

            $table->foreign(['card_id', 'deck_id'])->references(['id', 'deck_id'])->on('cards')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};

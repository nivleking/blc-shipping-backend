<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('card_temporaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('room_id');
            $table->string('card_id');
            $table->foreignId('deck_id');
            $table->string('status')->default('selected');
            $table->integer('round')->nullable();
            $table->boolean('is_backlog')->default(false);
            $table->integer('original_round')->nullable();
            $table->json('unfulfilled_containers')->nullable();
            $table->boolean('revenue_granted')->default(false);
            $table->integer('fulfillment_round')->nullable();
            $table->timestamps();
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign(['card_id', 'deck_id'])->references(['id', 'deck_id'])->on('cards')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_temporary');
    }
};

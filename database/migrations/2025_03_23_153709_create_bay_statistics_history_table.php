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
        Schema::create('bay_statistics_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->integer('week');
            $table->unsignedBigInteger('discharge_moves')->default(0);
            $table->unsignedBigInteger('load_moves')->default(0);
            $table->json('bay_pairs')->nullable();
            $table->json('bay_moves')->nullable();
            $table->integer('long_crane_moves')->default(0);
            $table->integer('extra_moves_on_long_crane')->default(0);
            $table->bigInteger('restowage_penalty')->default(0);
            $table->bigInteger('restowage_moves')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'room_id', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bay_statistics_history');
    }
};

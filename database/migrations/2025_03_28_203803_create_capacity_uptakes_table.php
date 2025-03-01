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
        Schema::create('capacity_uptakes', function (Blueprint $table) {
            $table->id();
            $table->string('room_id');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('week');
            $table->json('capacity_data')->nullable();
            $table->json('sales_calls_data')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'user_id', 'week']);

            // Foreign key to rooms table
            $table->foreign('room_id')->references('id')->on('rooms')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capacity_uptakes');
    }
};

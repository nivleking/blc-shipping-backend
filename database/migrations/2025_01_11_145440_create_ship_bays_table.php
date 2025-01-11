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
        Schema::create('ship_bays', function (Blueprint $table) {
            $table->id();
            $table->json('arena');
            $table->foreignId('user_id')->constrained();
            $table->string('room_id'); // Change to string
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade'); // Add foreign key constraint
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ship_bays');
    }
};

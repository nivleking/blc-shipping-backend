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
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->string('card_id');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->string('status')->default('selected');
            $table->timestamps();
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

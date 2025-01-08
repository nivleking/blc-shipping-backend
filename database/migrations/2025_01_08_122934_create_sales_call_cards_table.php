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
        // Type -> Kalau id nya kelipatan 5, maka type nya adalah 'Reefer', selain itu 'Dry'
        // Priority -> Committed dan Non-Committed
        // Origin -> TBA
        // Destination -> TBA
        // Quantity -> Jumlah kontainer dalam 1 sales call card
        // Revenue -> Total pendapatan dari semua kontainer dalam 1 sales call card
        Schema::create('sales_call_cards', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('priority');
            $table->string('origin');
            $table->string('destination');
            $table->bigInteger('quantity');
            $table->bigInteger('revenue');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_call_cards');
    }
};

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
        Schema::create('rooms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deck_id')->nullable()->constrained('sales_call_card_decks')->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('status')->default('created');
            $table->integer('max_users')->default(0);
            $table->json('bay_size')->nullable();
            $table->integer('bay_count')->default(0);
            $table->json('users')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

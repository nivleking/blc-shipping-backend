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
            $table->foreignId('deck_id')->nullable()->constrained('decks')->cascadeOnDelete();
            $table->foreignId('ship_layout_id')->nullable()->constrained('ship_layouts');
            $table->string('name');
            $table->text('description');
            $table->string('status')->default('created');
            $table->integer('max_users')->default(0);
            $table->json('users')->nullable();
            $table->json('assigned_users')->nullable();
            $table->json('bay_size')->nullable();
            $table->integer('bay_count')->default(0);
            $table->json('bay_types')->nullable();
            $table->integer('total_rounds')->default(1);
            $table->integer('cards_limit_per_round')->default(1);
            $table->json('swap_config')->nullable();
            $table->boolean('is_final_unloading')->default(false);
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

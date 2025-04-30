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
            $table->bigInteger('move_cost')->default(1000000);
            $table->json('dock_warehouse_costs')->nullable();
            $table->bigInteger('restowage_cost')->default(3500000);
            $table->integer('cards_limit_per_round')->default(1);
            $table->integer('cards_must_process_per_round')->default(1);
            $table->json('swap_config')->nullable();
            $table->timestamps();
            // $table->bigInteger('extra_moves_cost')->default(50000);
            // $table->integer('ideal_crane_split')->default(2);
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

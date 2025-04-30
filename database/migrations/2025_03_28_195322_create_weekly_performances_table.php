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
        Schema::create('weekly_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->integer('week');
            $table->integer('discharge_moves')->default(0);
            $table->integer('load_moves')->default(0);

            // Restowage data
            $table->integer('restowage_container_count')->default(0);
            $table->integer('restowage_moves')->default(0);
            $table->bigInteger('restowage_penalty')->default(0);

            // Container counts
            $table->json('unrolled_container_counts')->nullable();
            $table->json('dock_warehouse_container_counts')->nullable();

            // Total penalties
            $table->bigInteger('total_penalty')->default(0);
            $table->bigInteger('dock_warehouse_penalty')->default(0);
            $table->bigInteger('unrolled_penalty')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('move_costs')->default(0);
            $table->bigInteger('net_result')->default(0);
            $table->bigInteger('dry_containers_loaded')->default(0);
            $table->bigInteger('reefer_containers_loaded')->default(0);
            // $table->bigInteger('extra_moves_penalty')->default(0);
            // $table->integer('dry_containers_not_loaded')->default(0);
            // $table->integer('reefer_containers_not_loaded')->default(0);
            // $table->integer('committed_dry_containers_not_loaded')->default(0);
            // $table->integer('committed_reefer_containers_not_loaded')->default(0);
            // $table->integer('non_committed_dry_containers_not_loaded')->default(0);
            // $table->integer('non_committed_reefer_containers_not_loaded')->default(0);
            // $table->integer('long_crane_moves')->default(0);
            // $table->integer('extra_moves_on_long_crane')->default(0);
            // $table->integer('ideal_crane_split')->default(0);
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['user_id', 'room_id', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_performances');
    }
};

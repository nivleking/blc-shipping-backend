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
            $table->foreignId('user_id')->constrained();
            $table->string('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->integer('week');
            $table->string('port');
            $table->json('accepted_cards')->nullable();
            $table->json('rejected_cards')->nullable();
            $table->json('arena_start')->nullable();
            $table->json('arena_end')->nullable();
            $table->integer('dry_containers_accepted')->default(0);
            $table->integer('reefer_containers_accepted')->default(0);
            $table->integer('committed_containers_accepted')->default(0);
            $table->integer('non_committed_containers_accepted')->default(0);
            $table->integer('dry_containers_rejected')->default(0);
            $table->integer('reefer_containers_rejected')->default(0);
            $table->integer('committed_containers_rejected')->default(0);
            $table->integer('non_committed_containers_rejected')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'room_id', 'week']);
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

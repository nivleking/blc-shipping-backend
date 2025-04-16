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
            $table->foreignId('user_id')->constrained();
            $table->string('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->string('port');
            $table->json('arena');
            $table->string('section')->default('section1');
            $table->bigInteger('total_revenue')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('penalty')->default(0);
            $table->bigInteger('extra_moves_penalty')->default(0);
            $table->bigInteger('backlog_penalty')->default(0);
            $table->bigInteger('restowage_penalty')->default(0);
            $table->json('restowage_containers')->nullable();
            $table->json('backlog_containers')->nullable();
            $table->integer('restowage_moves')->default(0);
            $table->unsignedBigInteger('discharge_moves')->default(0);
            $table->unsignedBigInteger('load_moves')->default(0);
            $table->unsignedBigInteger('processed_cards')->default(0);
            $table->unsignedBigInteger('accepted_cards')->default(0);
            $table->unsignedBigInteger('rejected_cards')->default(0);
            $table->integer('current_round')->default(1);
            $table->integer('current_round_cards')->default(0);
            $table->json('bay_pairs')->nullable();
            $table->json('bay_moves')->nullable();
            $table->integer('long_crane_moves')->default(0);
            $table->integer('extra_moves_on_long_crane')->default(0);
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_answers', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('puzzle_number')->unique();
            $table->foreignId('street_id')->constrained();
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('player_token')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('daily_answer_id')->constrained();
            $table->unsignedTinyInteger('guess_count')->default(0);
            $table->timestamp('solved_at')->nullable();
            $table->timestamps();

            $table->unique(['player_token', 'daily_answer_id']);
        });

        Schema::create('guesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('street_id')->constrained();
            $table->unsignedTinyInteger('guess_number');
            $table->decimal('distance_miles', 6, 2);
            $table->string('direction', 2)->nullable(); // N, NE, ... null on a correct guess
            $table->boolean('ward_match');
            $table->boolean('postcode_match');
            $table->boolean('street_type_match');
            $table->boolean('first_letter_match');
            $table->timestamps();

            $table->unique(['game_id', 'guess_number']);
            $table->unique(['game_id', 'street_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guesses');
        Schema::dropIfExists('games');
        Schema::dropIfExists('daily_answers');
    }
};

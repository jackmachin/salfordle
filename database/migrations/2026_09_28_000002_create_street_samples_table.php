<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('street_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('street_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedSmallInteger('bearing'); // street direction at this point, degrees
            $table->string('status')->nullable(); // Metadata API status; null = not yet checked
            $table->string('pano_id')->nullable();
            $table->decimal('pano_lat', 9, 6)->nullable();
            $table->decimal('pano_lng', 9, 6)->nullable();
            $table->string('pano_date', 7)->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->unique(['street_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('street_samples');
    }
};

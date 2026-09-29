<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->unique(); // disambiguates same-named streets, e.g. "Church Street (Eccles)"
            $table->string('sub_area')->nullable(); // flavour text only, never a filter
            $table->string('ward')->nullable()->index();
            $table->string('postcode_district', 8)->nullable()->index();
            $table->string('street_type')->nullable()->index();
            $table->char('first_letter', 1)->index();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->unsignedInteger('length_m')->nullable();
            $table->json('geometry'); // GeoJSON MultiLineString of the grouped OSM ways
            $table->json('osm_way_ids');
            $table->decimal('coverage_score', 5, 4)->nullable(); // 0-1
            $table->string('pano_id')->nullable();
            $table->string('pano_date', 7)->nullable(); // YYYY-MM from the Metadata API
            $table->unsignedSmallInteger('heading')->nullable(); // degrees, biased along the street
            $table->boolean('is_active')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streets');
    }
};

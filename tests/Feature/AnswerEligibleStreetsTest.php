<?php

namespace Tests\Feature;

use App\Models\Street;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerEligibleStreetsTest extends TestCase
{
    use RefreshDatabase;

    private function street(string $name, array $attributes = []): Street
    {
        return Street::create($attributes + [
            'name' => $name,
            'display_name' => $name,
            'first_letter' => $name[0],
            'lat' => 53.48,
            'lng' => -2.29,
            'length_m' => 200,
            'geometry' => ['type' => 'MultiLineString', 'coordinates' => []],
            'osm_way_ids' => [],
            'postcode_district' => 'M6',
            'coverage_score' => 0.95,
            'pano_id' => "pano-{$name}",
        ]);
    }

    public function test_only_well_covered_long_streets_with_their_own_pano_are_eligible(): void
    {
        $this->street('Good Street');
        $this->street('Patchy Road', ['coverage_score' => 0.5]);
        $this->street('Stub Close', ['length_m' => 30]);
        $this->street('Dark Lane', ['coverage_score' => null, 'pano_id' => null]);
        $this->street('Corner Avenue', ['pano_id' => 'shared']);
        $this->street('Corner Grove', ['pano_id' => 'shared', 'length_m' => 20]);
        $this->street('Warrington Way', ['postcode_district' => 'WA3']);

        $this->assertSame(['Good Street'], Street::answerEligible()->pluck('name')->all());
    }
}

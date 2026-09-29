<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Street extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'coverage_score' => 'float',
            'geometry' => 'array',
            'osm_way_ids' => 'array',
            'postcodes' => 'array',
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function samples(): HasMany
    {
        return $this->hasMany(StreetSample::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Streets that could fairly be a daily answer, before manual review:
     * enough coverage, long enough to be recognisable, and a pano no other
     * street has locked (a corner pano would make two answers "right").
     */
    public function scopeAnswerEligible(Builder $query): void
    {
        $config = config('streetle.coverage');

        $query->where('coverage_score', '>=', $config['answer_threshold'])
            ->where('length_m', '>=', $config['min_answer_length_metres'])
            ->whereNotIn('postcode_district', $config['excluded_answer_postcode_districts'])
            ->whereNotNull('pano_id')
            ->whereNotIn('pano_id', fn ($q) => $q->select('pano_id')->from('streets')
                ->whereNotNull('pano_id')->groupBy('pano_id')->havingRaw('count(*) > 1'));
    }
}

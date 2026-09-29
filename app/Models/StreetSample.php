<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreetSample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'pano_lat' => 'float',
            'pano_lng' => 'float',
            'checked_at' => 'datetime',
        ];
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }
}

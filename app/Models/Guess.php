<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guess extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'distance_miles' => 'float',
            'ward_match' => 'boolean',
            'postcode_match' => 'boolean',
            'street_type_match' => 'boolean',
            'first_letter_match' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}

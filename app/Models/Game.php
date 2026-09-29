<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['solved_at' => 'datetime'];
    }

    public function dailyAnswer(): BelongsTo
    {
        return $this->belongsTo(DailyAnswer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class)->orderBy('guess_number');
    }
}

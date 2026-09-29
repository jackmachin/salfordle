<?php

namespace App\Game;

use App\Models\Street;

/** Every guessable street's facts, keyed by id: the starting candidate pool. */
class StreetIndex
{
    /** @var array<int, StreetFacts>|null */
    private ?array $facts = null;

    /** @return array<int, StreetFacts> */
    public function all(): array
    {
        return $this->facts ??= Street::query()
            ->get(['id', 'ward', 'postcode_district', 'street_type', 'first_letter', 'lat', 'lng'])
            ->mapWithKeys(fn (Street $s) => [$s->id => StreetFacts::fromModel($s)])
            ->all();
    }

    public function get(int $id): StreetFacts
    {
        return $this->all()[$id];
    }
}

<?php

namespace App\Game;

use App\Models\Street;

/** The attributes the game compares, detached from Eloquent so scoring stays pure. */
final readonly class StreetFacts
{
    public function __construct(
        public int $id,
        public ?string $ward,
        public ?string $postcodeDistrict,
        public ?string $streetType,
        public string $firstLetter,
        public float $lat,
        public float $lng,
    ) {}

    public static function fromModel(Street $street): self
    {
        return new self(
            $street->id,
            $street->ward,
            $street->postcode_district,
            $street->street_type,
            $street->first_letter,
            $street->lat,
            $street->lng,
        );
    }
}

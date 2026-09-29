<?php

namespace App\Game;

final readonly class GuessResult
{
    /**
     * @param  'before'|'after'|null  $letterHint  where the answer's first letter sits relative to the guess's, when they differ
     * @param  array<int, StreetFacts>  $pool  candidates still consistent with every guess so far
     */
    public function __construct(
        public bool $correct,
        public bool $wardMatch,
        public bool $postcodeMatch,
        public bool $streetTypeMatch,
        public bool $firstLetterMatch,
        public ?string $letterHint,
        public float $distanceMiles,
        public ?string $direction,
        public array $pool,
    ) {}
}

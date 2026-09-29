<?php

namespace App\Streets;

final class StreetName
{
    /**
     * Recognised street-type suffixes. Anything else is "Other" so the
     * street-type filter still has a value to compare.
     */
    public const TYPES = [
        'Road', 'Street', 'Avenue', 'Close', 'Lane', 'Drive', 'Way', 'Grove',
        'Crescent', 'Place', 'Court', 'Gardens', 'Walk', 'Terrace', 'Square',
        'Mews', 'View', 'Rise', 'Hill', 'Row', 'Green', 'Fold', 'Brow', 'Park',
        'Parade', 'Approach', 'Croft', 'Bank', 'Wharf', 'Quay', 'Boulevard',
        'Vale', 'Mount', 'Chase', 'Gate', 'Link', 'Meadow', 'Meadows', 'Grange',
        'Brook', 'Lea', 'Yard', 'Precinct', 'Broadway', 'Parkway', 'Circle',
        'Roundabout', 'Bridge',
    ];

    public const OTHER = 'Other';

    private const DIRECTIONAL_SUFFIXES = ['North', 'South', 'East', 'West', 'Upper', 'Lower'];

    /** Tidy an OSM name: trim, collapse whitespace, straighten quotes. */
    public static function clean(string $name): string
    {
        $name = str_replace(["\u{2019}", "\u{2018}"], "'", $name);

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /** Key used to decide whether two OSM names are the same street name. */
    public static function key(string $name): string
    {
        return mb_strtolower(str_replace('.', '', self::clean($name)));
    }

    /** "Bolton Road West" -> "Road", "Broadway" -> "Broadway", "The Height" -> "Other". */
    public static function type(string $name): string
    {
        $words = explode(' ', self::clean($name));

        while (count($words) > 1 && in_array(end($words), self::DIRECTIONAL_SUFFIXES, true)) {
            array_pop($words);
        }

        $last = ucfirst(strtolower(end($words)));

        return in_array($last, self::TYPES, true) ? $last : self::OTHER;
    }

    public static function firstLetter(string $name): string
    {
        return preg_match('/[\p{L}\p{N}]/u', $name, $match)
            ? mb_strtoupper(mb_substr(iconv('UTF-8', 'ASCII//TRANSLIT', $match[0]) ?: $match[0], 0, 1))
            : '#';
    }
}

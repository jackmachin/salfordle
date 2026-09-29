<?php

return [

    // Overpass, ONS and postcodes.io all ask for an identifying User-Agent.
    'user_agent' => env('STREETLE_USER_AGENT', 'salfordle/0.1 (Salford street game data import)'),

    // Salford metropolitan borough.
    'borough_gss' => 'E08000006',

    /*
    |--------------------------------------------------------------------------
    | OpenStreetMap (Overpass)
    |--------------------------------------------------------------------------
    |
    | Endpoints are tried in order; the main instance is often busy.
    |
    */

    'overpass' => [
        'endpoints' => [
            'https://overpass-api.de/api/interpreter',
            'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
        ],
        'highway_types' => [
            'trunk', 'primary', 'secondary', 'tertiary', 'unclassified',
            'residential', 'living_street', 'pedestrian', 'road',
        ],
        // Used for the flavour-text sub_area and to disambiguate same-named streets.
        'place_types' => ['town', 'village', 'suburb', 'quarter', 'neighbourhood', 'hamlet'],
    ],

    // Same-named OSM ways closer than this are treated as one street.
    'group_gap_metres' => 150,

    // An existing street is updated in place on re-import if a same-named group lands this close.
    'reimport_match_metres' => 300,

    /*
    |--------------------------------------------------------------------------
    | Ward boundaries (ONS Open Geography, full resolution, May 2026)
    |--------------------------------------------------------------------------
    */

    'wards' => [
        'url' => 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/WD_MAY_2026_UK_BFC/FeatureServer/0/query',
        'district_field' => 'LAD26CD',
        'code_field' => 'WD26CD',
        'name_field' => 'WD26NM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Street View coverage (Metadata API: free, but rate limited)
    |--------------------------------------------------------------------------
    */

    'coverage' => [
        'metadata_url' => 'https://maps.googleapis.com/maps/api/streetview/metadata',
        'sample_spacing_metres' => 40,
        'max_samples_per_street' => 40, // long roads get wider spacing instead
        'search_radius_metres' => 50,
        // A pano further than this from the street's own line is on a different street
        // (e.g. the main road at the end of a cul-de-sac) and doesn't count.
        'max_pano_offset_metres' => 15,
        'min_pano_year' => 2015,
        'answer_threshold' => 0.7,
        // Shorter stubs mostly show the road they branch off. Still guessable, never the answer.
        'min_answer_length_metres' => 50,
        // Border strays: so rare that a green postcode tile would give the answer away. Still guessable.
        'excluded_answer_postcode_districts' => ['WA3'],
        'concurrency' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Game
    |--------------------------------------------------------------------------
    */

    'game' => [
        // The puzzle day rolls over at local midnight.
        'timezone' => 'Europe/London',
        // Puzzle #1. Numbers count days from here.
        'first_puzzle_date' => env('STREETLE_FIRST_PUZZLE_DATE') ?: '2026-09-28', // ?: so an empty .env value doesn't count
        // When true, only streets approved in manual review (is_active) are picked as answers.
        'require_review' => (bool) env('STREETLE_REQUIRE_REVIEW', false),
    ],

    'image' => [
        'url' => 'https://maps.googleapis.com/maps/api/streetview',
        'size' => '640x400',
        'fov' => 90,
        // Our own ceiling on billable image fetches per day, behind the Google Cloud quota.
        'daily_cap' => (int) (env('STREETLE_IMAGE_DAILY_CAP') ?: 330),
        'per_player_daily' => 20,
    ],

    'postcodes' => [
        'url' => 'https://api.postcodes.io/postcodes',
        'radius_metres' => 500,
        'points_per_street' => 5,

        // Full postcodes for search: collected around points this far apart along every street...
        'search_spacing_metres' => 60,
        'search_radius_metres' => 150,
        // ...then each postcode goes to the street nearest its centre (plus any within tie_metres
        // of that, for corner postcodes), if one is within max_street_metres.
        'max_street_metres' => 80,
        'tie_metres' => 15,
    ],

];

# Salfordle

A daily guessing game: name the Salford street in a Street View image. Each guess shows how your street compares with the answer (ward, postcode district, street type, first letter, distance and direction) and rules out every street the clues exclude.

Laravel 13 JSON API + React 19 / TypeScript / Tailwind, built with Vite. Design notes are in [HANDOVER.md](HANDOVER.md); working conventions for coding agents are in [CLAUDE.md](CLAUDE.md).

## Running locally

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # loads the 3,645 streets from database/data/streets.json
php artisan serve                # and, in another terminal:
npm run dev
```

Set `GOOGLE_MAPS_KEY` in `.env` (Street View Static API enabled) for the puzzle image.

## Tests

```sh
php vendor/bin/phpunit   # backend
npm test                 # frontend (Vitest)
npx tsc -p .             # type-check
```

## Street data

`database/data/streets.json` is the output of the data pipeline, committed so any environment can be seeded without rerunning it. To rebuild it (all steps are resumable and cache their downloads):

```sh
php artisan streets:import            # OSM roads, grouped into streets
php artisan streets:assign-areas      # ward (ONS boundaries) and postcode district
php artisan streets:assign-postcodes  # full postcodes, for postcode search
php artisan streets:check-coverage    # Street View coverage, locked image and heading (needs the key)
php artisan streets:export            # write database/data/streets.json
```

`php artisan puzzles:reroll` swaps a day's street for another (e.g. when a street sign is in shot).

## Configuration

Tunables live in `config/streetle.php`. Environment settings:

| Variable | Purpose |
|---|---|
| `GOOGLE_MAPS_KEY` | Street View Static API key. Restrict it to that API and, ideally, to the server's IP (only the server calls Google). |
| `GOOGLE_MAPS_REFERER` | Only if the key is restricted by website instead: the address to send as `Referer`, e.g. `https://salfordle.co.uk/`. |
| `STREETLE_FIRST_PUZZLE_DATE` | Date of puzzle #1 (numbers count from here). |
| `STREETLE_REQUIRE_REVIEW` | `true` to only pick answers approved in manual review. |
| `STREETLE_IMAGE_DAILY_CAP` | Daily ceiling on billable image fetches (default 330, the free tier). |

## Data credits

Street data © OpenStreetMap contributors (ODbL). Ward boundaries and postcodes from the Office for National Statistics via postcodes.io; contains OS data © Crown copyright and database right (Open Government Licence). Imagery © Google.

# Salfordle

Daily "-dle" game: guess a Salford street from a static Street View image. Full design, decisions and open items live in [HANDOVER.md](HANDOVER.md). Read it before making changes.

## Stack
- Laravel 13 (PHP 8.3), **JSON API** under `/api`. The only Blade view is the shell for the React app.
- React 19 + TypeScript + Tailwind 4, built with Vite, in `resources/js`.
- Google Static Street View API, with images addressed by a locked `pano_id`.
- Laravel Boost is deliberately **not** used. Don't install it.

## Running it
- Dev: `php artisan serve` plus `npm run dev` (React + Vite in `resources/js`, served by the one Blade shell `resources/views/app.blade.php`). `npm run build` for production assets.
- Tests: `php vendor/bin/phpunit` (backend) and `npm test` (Vitest, frontend). Type-check with `npx tsc -p .`.
- Street data pipeline, in order: `streets:import`, `streets:assign-areas`, `streets:assign-postcodes`, `streets:check-coverage`, then `streets:export` to refresh the committed `database/data/streets.json` (loaded by `php artisan db:seed`). Each is resumable and caches downloads in `storage/app/private/streetle/`. Tunables are in `config/streetle.php`.
- Deploying: see DEPLOY.md (`./deploy.ps1`). Production is Hostinger shared hosting with MySQL and no Node, so frontend assets are built locally and uploaded.
- Each `/api/puzzles/{number}/image` hit is a billable Street View request, so don't hammer it while testing.

## Conventions
- Game scoring stays a pure, unit-tested function: `applyGuess(candidatePool, guess, answer)`.
- Validate guesses server-side. API responses must never leak the answer before the game ends. The candidate list is fine to send: it is the clues worked through, and the UI shows it anyway.
- Distances are in miles. Directions use 8 compass points.
- Never read or print `.env`. Secrets (e.g. the Google Maps API key) live there.

# Streetle: Project Handover

A daily "-dle" game: guess a Salford street from a static Street View image. Closer to Squirdle than GeoGuessr: text/autocomplete guessing, no map. Each wrong guess eliminates candidates via categorical filters.

## Stack (decided)
- **Backend**: Laravel API only (no Blade), JSON responses
- **Frontend**: React (Vite), lightweight state (Zustand or Context)
- **Street View**: Static Street View API, images fetched by locked `pano_id` (not lat/lng, so results stay deterministic)
- **Play model**: anonymous by default, identified by a long-life cookie (`player_token` UUID) so streaks/history work without login. Accounts are an optional layer: on signup, attach the existing token's games to the new `user_id`. Streak counter is the natural "sign up to protect your streak" hook.

## Game mechanics (decided)
- Player guesses a street via searchable autocomplete over the full street list.
- Every guess reveals **all** filter results at once.
- **Categorical filters (hard elimination)**: a "no match" removes every remaining candidate sharing that value (e.g. wrong on Patricroft-type ward/postcode/type/letter clears all streets sharing it).
  - Council ward (official polygon; replaces informal "sub-area", which is too fuzzy, e.g. Claremont Road could be Claremont / The Height / Pendleton)
  - Postcode district
  - Street type (Road, Close, Avenue, etc.), locked in
  - First letter (possibly alphabet banding, before/after), locked in
- **Distance + 8-point compass direction**: informational only, never eliminates. Deliberately non-monotonic: distance bouncing up and down while the pool always narrows is what makes shared results funny.
- Distance is displayed in **miles**.
- Sub-area name may still be shown as flavour text under the image, but is not a filter.
- No residential/retail zoning classification.
- Backend scoring is a pure function: `applyGuess(candidatePool, guessStreet, answerStreet) -> {matches, distance, direction, newCandidatePool}`. Keep it unit-testable. Validate guesses server-side so the answer can't be read from network calls.
- Frontend needs a live candidate pool panel ("X streets remaining") alongside the Street View image.

## Share grid (draft)
```
Streetle #47 - 4/6

🟩🟩⬜🟩 ↗️ 4.2mi
🟩🟩🟩🟩 ↘️ 1.8mi
🟩🟩🟩🟩 ↗️ 3.1mi
🟩🟩🟩🟩 🎯 SOLVED
```
One row per guess. Fixed square order: ward, postcode district, street type, first letter. Build after core mechanics work.

## Schema (draft)
```
streets
  id, name, ward, postcode_district, street_type,
  sub_area (display only), lat, lng (centroid),
  pano_id, coverage_score, is_active

daily_answers
  id, date (unique), street_id   -- seeded/picked so everyone gets the same puzzle

games
  id, player_token, user_id (nullable), daily_answer_id,
  solved_at, guess_count, created_at

guesses   -- normalized, not JSON (built for future stats/leaderboards)
  id, game_id, street_id, guess_number,
  distance_miles, direction,
  ward_match, postcode_match, street_type_match, first_letter_match
```

## FIRST TASK: street data + coverage pipeline
This is the prerequisite for everything else and the hardest part.

1. **Get geometry, not just names.** Pull Salford street geometries (LineStrings) from OSM via Overpass. Group ways by street name, since OSM splits one street into many segments. Also look at Salford Council open data as an alternative/supplement.
2. **Attributes per street.**
   - Postcode district: from OSM tags or a postcode lookup on the centroid.
   - Council ward: fetch ward boundary GeoJSON (Salford Council open data, ONS, or OSM) and do point-in-polygon on each street centroid. Check for streets that straddle wards and decide how to handle them (centroid ward is the simple default).
   - Street type: parse from the name suffix.
3. **Sample points** along each LineString every ~30-50m (long streets get more, cul-de-sacs 1-2).
4. **Check coverage with the Street View Metadata API** (free, no per-image cost) for every sample point, with a ~50m radius. Store `status`, `pano_id`, capture date.
5. **Score**: coverage % = OK points / total points. Threshold around 70%+ to enter the answer pool (tunable).
6. **Lock `pano_id`s** for passing streets. Prefer recent capture dates; filter out very old/grainy imagery.
7. **Heading**: static images default to an arbitrary heading, which can point straight at a street sign and give the answer away. Bias heading along the street's bearing rather than across it.
8. **Manual curation pass** on the borderline/final set (street signs in frame, poor imagery) before launch. Add an `is_active` flag toggled by this review, ideally with a simple review script/page.

Deliverable for this stage: a populated `streets` table (or seed CSV/JSON) with ward, postcode district, street type, centroid, coverage score and locked pano IDs, plus the Laravel migrations above.

## Open items / to check once real data exists
- How well ward assignment holds up for long streets that cross ward boundaries.
- Rough size of the street list (likely a few thousand names): decides whether autocomplete runs client-side or via a debounced search endpoint.
- Alphabet-banding vs flat first-letter match.
- Current Street View pricing for static images (check before launch).

## Later (not now)
- Share grid rendering
- Stats/leaderboards ("hardest street this week", average guesses), enabled by the normalized guesses table
- Accounts and streak claiming

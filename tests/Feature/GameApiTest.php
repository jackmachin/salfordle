<?php

namespace Tests\Feature;

use App\Game\DailyPuzzles;
use App\Models\DailyAnswer;
use App\Models\Street;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    private DailyAnswer $puzzle;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['streetle.game.first_puzzle_date' => now('Europe/London')->toDateString()]);

        // Eight wrong candidates plus room for the answer, across varied clue values.
        $wards = ['Eccles', 'Ordsall', 'Claremont'];
        $types = ['Road', 'Street', 'Close'];

        foreach (range(0, 8) as $i) {
            $name = chr(65 + $i).'name '.$types[$i % 3];
            Street::create([
                'name' => $name,
                'display_name' => $name,
                'ward' => $wards[intdiv($i, 3)],
                'postcode_district' => 'M'.(5 + $i % 2),
                'street_type' => $types[$i % 3],
                'first_letter' => chr(65 + $i),
                'lat' => 53.48 + $i * 0.004,
                'lng' => -2.29 + $i * 0.003,
                'length_m' => 200,
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => []],
                'osm_way_ids' => [],
                'coverage_score' => 1,
                'pano_id' => "secret-pano-{$i}",
                'heading' => 90,
            ]);
        }

        $this->puzzle = app(DailyPuzzles::class)->today();
    }

    private function play(string $method, string $uri, array $data = []): TestResponse
    {
        // json() only sends cookies with credentials, like a browser fetch would same-origin.
        $response = $this->token
            ? $this->withCredentials()->withCookie('player_token', $this->token)->json($method, $uri, $data)
            : $this->json($method, $uri, $data);

        $this->token ??= $response->getCookie('player_token')?->getValue();

        return $response;
    }

    private function wrongStreets(): array
    {
        return Street::whereKeyNot($this->puzzle->street_id)->pluck('id')->all();
    }

    public function test_the_puzzle_gives_nothing_away_and_issues_a_player_cookie(): void
    {
        $response = $this->play('GET', '/api/puzzle')
            ->assertOk()
            ->assertJsonPath('puzzle.number', 1)
            ->assertJsonPath('status', 'playing')
            ->assertJsonPath('remaining', 9)
            ->assertJsonPath('answer', null);

        $this->assertNotNull($this->token);
        $this->assertStringNotContainsString('secret-pano', $response->getContent());
        $this->assertStringNotContainsString($this->puzzle->street->display_name, $response->getContent());
    }

    public function test_a_wrong_guess_narrows_the_pool_and_a_right_one_wins(): void
    {
        $this->play('POST', '/api/guesses', ['street_id' => $this->wrongStreets()[0], 'puzzle' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'playing')
            ->assertJsonCount(1, 'guesses')
            ->assertJsonPath('guesses.0.correct', false)
            ->assertJsonPath('answer', null);

        $this->assertLessThan(9, $this->play('GET', '/api/puzzle')->json('remaining'));

        $this->play('POST', '/api/guesses', ['street_id' => $this->puzzle->street_id])
            ->assertOk()
            ->assertJsonPath('status', 'won')
            ->assertJsonPath('remaining', 1)
            ->assertJsonPath('guesses.1.correct', true)
            ->assertJsonPath('guesses.1.direction', null)
            ->assertJsonPath('answer.id', $this->puzzle->street_id);
    }

    public function test_candidates_and_letter_hints_follow_the_clues(): void
    {
        $wrong = Street::find($this->wrongStreets()[0]);
        $answer = $this->puzzle->street;

        $response = $this->play('POST', '/api/guesses', ['street_id' => $wrong->id])->assertOk();

        $candidates = $response->json('candidates');
        $this->assertCount($response->json('remaining'), $candidates);
        $this->assertContains($answer->id, $candidates);
        $this->assertNotContains($wrong->id, $candidates);

        $expectedHint = $answer->first_letter === $wrong->first_letter ? null
            : ($answer->first_letter > $wrong->first_letter ? 'after' : 'before');
        $response->assertJsonPath('guesses.0.first_letter_hint', $expectedHint);

        // District counts cover exactly the candidates, and include the answer's district.
        $districts = $response->json('remaining_postcode_districts');
        $this->assertSame(count($candidates), array_sum($districts));
        $this->assertArrayHasKey($answer->postcode_district, $districts);
    }

    public function test_progress_follows_the_cookie(): void
    {
        $this->play('POST', '/api/guesses', ['street_id' => $this->wrongStreets()[0]])->assertOk();

        $this->play('GET', '/api/puzzle')->assertJsonCount(1, 'guesses');

        // A different (new) player starts fresh.
        $this->token = null;
        $this->defaultCookies = [];
        $this->withCredentials = false;
        $this->play('GET', '/api/puzzle')->assertJsonCount(0, 'guesses');
    }

    public function test_six_wrong_guesses_lose_and_reveal_the_answer(): void
    {
        $wrong = array_slice($this->wrongStreets(), 0, 6);

        foreach (array_slice($wrong, 0, 5) as $id) {
            $this->play('POST', '/api/guesses', ['street_id' => $id])->assertOk()->assertJsonPath('status', 'playing');
        }

        $this->play('POST', '/api/guesses', ['street_id' => $wrong[5]])
            ->assertJsonPath('status', 'lost')->assertJsonPath('answer.id', $this->puzzle->street_id);

        $this->play('POST', '/api/guesses', ['street_id' => $this->puzzle->street_id])->assertStatus(409);
    }

    public function test_repeat_guesses_and_stale_puzzles_are_rejected(): void
    {
        $id = $this->wrongStreets()[0];

        $this->play('POST', '/api/guesses', ['street_id' => $id])->assertOk();
        $this->play('POST', '/api/guesses', ['street_id' => $id])->assertJsonValidationErrors('street_id');
        $this->play('POST', '/api/guesses', ['street_id' => $this->wrongStreets()[1], 'puzzle' => 0])->assertStatus(409);
        $this->play('POST', '/api/guesses', ['street_id' => 999999])->assertJsonValidationErrors('street_id');
    }

    public function test_the_street_list_has_names_only(): void
    {
        $this->play('GET', '/api/streets')
            ->assertOk()
            ->assertJsonCount(9)
            ->assertJsonStructure([['id', 'name', 'district', 'postcodes']])
            ->assertJsonMissingPath('0.ward');
    }

    public function test_the_street_list_must_be_revalidated_and_answers_304_when_unchanged(): void
    {
        $first = $this->getJson('/api/streets')->assertOk();
        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('no-cache', $first->headers->get('Cache-Control'));

        $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/streets')->assertStatus(304);

        // Any data change gives a new ETag, so browsers pick it up.
        Street::first()->update(['postcodes' => ['M5 1AA']]);
        $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/streets')->assertOk();
    }

    public function test_the_image_is_proxied_without_exposing_the_pano(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);

        $response = $this->play('GET', '/api/puzzles/1/image')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->assertSame('JPEGDATA', $response->getContent());
        Http::assertSent(fn (HttpRequest $r) => $r['pano'] === $this->puzzle->street->pano_id && $r['heading'] == 90);
    }

    public function test_each_day_has_its_own_image_url_and_old_ones_stop_working(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);

        $today = $this->play('GET', '/api/puzzle')->json('puzzle.image_url');

        $this->travel(1)->days();
        $tomorrow = $this->play('GET', '/api/puzzle')->json('puzzle.image_url');

        // Same URL on both days would let the browser's cache show yesterday's street.
        $this->assertNotSame($today, $tomorrow);
        $this->play('GET', '/api/puzzles/1/image')->assertNotFound();
        $this->play('GET', '/api/puzzles/2/image')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_rerolling_a_puzzle_swaps_the_street_clears_games_and_changes_the_image_url(): void
    {
        $this->play('POST', '/api/guesses', ['street_id' => $this->wrongStreets()[0]])->assertOk();
        $before = $this->play('GET', '/api/puzzle')->json('puzzle');
        $oldStreet = $this->puzzle->street_id;

        $this->travel(5)->seconds();
        $this->artisan('puzzles:reroll', ['--force' => true])->assertSuccessful();

        $after = $this->play('GET', '/api/puzzle')->assertJsonCount(0, 'guesses')->json('puzzle');

        $this->assertNotSame($oldStreet, $this->puzzle->fresh()->street_id);
        $this->assertSame($before['number'], $after['number']);
        $this->assertNotSame($before['image_url'], $after['image_url']);
    }

    public function test_the_image_request_sends_a_referer_only_when_configured(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->play('GET', '/api/puzzles/1/image')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => ! $r->hasHeader('Referer'));

        config(['services.google_maps.referer' => 'https://salfordle.co.uk/']);
        $this->play('GET', '/api/puzzles/1/image')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->header('Referer') === ['https://salfordle.co.uk/']);
    }

    public function test_image_fetches_stop_at_the_daily_cap(): void
    {
        config(['streetle.image.daily_cap' => 1]);
        Http::fake(['maps.googleapis.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->play('GET', '/api/puzzles/1/image')->assertOk();
        $this->play('GET', '/api/puzzles/1/image')->assertStatus(503);

        Http::assertSentCount(1);
    }
}

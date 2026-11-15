<?php

namespace Tests\Feature;

use App\Http\Controllers\ScheduleController;
use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function seedSnapshot(): void
    {
        ScheduleSnapshot::create(['tournament_external_id' => '700', 'payload' => [
            'tournament' => ['name' => 'Testo turnyras'],
            'matches' => [[
                'match_id' => 1, 'date' => '2026-11-26', 'time' => '10:00', 'court' => ['court_id' => 1, 'name' => 'Court 1'],
                'participants' => [['side' => 1, 'name' => 'Jonas', 'surname' => 'Žukas']], 'sets' => [], 'winner_side' => null,
            ]],
            'places' => ['jonas zukas' => 'Klaipėda', 'kitas zmogus' => 'Vilnius'],
        ]]);
    }

    public function test_page_and_data_use_admin_courts_and_places(): void
    {
        $this->seedSnapshot();
        Tournament::create(['slug' => 't', 'tournated_id' => '700', 'status' => 'upcoming', 'date_start' => '2026-11-26', 'location' => 'X',
            'tv_courts' => '3, 1,2', 'tv_screen_inch' => 55, 'tv_distance_m' => 2.0]);

        $this->get('/tv/700')->assertOk()->assertSee('tv\/700\/data', false)->assertSee('Testo turnyras');

        $json = $this->getJson('/tv/700/data')->assertOk()->json();
        $this->assertSame([3, 1, 2], $json['courts']);
        $this->assertSame(['jonas zukas' => 'Klaipėda'], $json['places']); // only people on the screen
        $this->assertEqualsWithDelta(Tournament::tvFontShare(55, 2.0), $json['font_share'], 1e-9);
        $this->assertSame([5, 6], $this->getJson('/tv/700/data?kortai=5,6')->json('courts'));
        $this->assertGreaterThan($json['font_share'], $this->getJson('/tv/700/data?m=4')->json('font_share'));
    }

    public function test_hidden_schedule_hides_the_tv_page(): void
    {
        $this->seedSnapshot();
        Tournament::create(['slug' => 't', 'tournated_id' => '700', 'show_schedule' => false, 'status' => 'upcoming', 'date_start' => '2026-11-26', 'location' => 'X']);

        $this->get('/tv/700')->assertNotFound();
        $this->getJson('/tv/700/data')->assertNotFound();
    }

    public function test_places_can_be_switched_off(): void
    {
        $this->seedSnapshot();
        Tournament::create(['slug' => 't', 'tournated_id' => '700', 'status' => 'upcoming', 'date_start' => '2026-11-26', 'location' => 'X', 'tv_show_place' => false]);

        $this->assertSame([], $this->getJson('/tv/700/data')->json('places'));
    }

    public function test_text_size_follows_distance_and_screen(): void
    {
        $near = Tournament::tvFontShare(55, 2.0);
        $this->assertGreaterThan($near, Tournament::tvFontShare(55, 3.0));   // further away -> bigger
        $this->assertLessThan($near, Tournament::tvFontShare(65, 2.0));      // bigger TV -> smaller share
        $this->assertGreaterThanOrEqual(6, Tournament::tvMaxCourts(55, 2.0));
    }

    public function test_short_tv_address_opens_todays_tournament_or_lists_them(): void
    {
        $this->seedSnapshot();
        Tournament::create(['slug' => 't', 'tournated_id' => '700', 'status' => 'upcoming', 'date_start' => now()->addDays(3)->toDateString(), 'location' => 'X']);
        $this->get('/tv')->assertOk()->assertSee('/tv/700', false)->assertSee('Rodyti');

        Tournament::where('tournated_id', '700')->update(['date_start' => now('Europe/Vilnius')->toDateString()]);
        $this->get('/tv')->assertRedirect('/tv/700');
    }

    public function test_admin_screens_pick_courts_by_tournated_id_across_venues(): void
    {
        $day = now('Europe/Vilnius')->toDateString();
        $m = fn (int $id, int $courtId, string $court, string $who) => [
            'match_id' => $id, 'date' => $day, 'time' => '10:00', 'court' => ['court_id' => $courtId, 'name' => $court],
            'participants' => [['side' => 1, 'name' => $who, 'surname' => 'X']], 'sets' => [], 'winner_side' => null,
        ];
        ScheduleSnapshot::create(['tournament_external_id' => '800', 'payload' => [
            'tournament' => ['name' => 'Dvi arenos'],
            'matches' => [$m(1, 11, 'Vidus 1', 'Ana'), $m(2, 21, 'Skycop 1', 'Bea')],
            'places' => ['ana x' => 'Klaipėda', 'bea x' => 'Vilnius'],
        ]]);
        Tournament::create(['slug' => 'd', 'tournated_id' => '800', 'status' => 'active', 'date_start' => $day, 'location' => 'X',
            'tv_screen_inch' => 55, 'tv_distance_m' => 2.0,
            'tv_screens' => [['name' => 'Vidus', 'courts' => ['11']], ['name' => 'Skycop', 'courts' => [21], 'inch' => '75', 'distance' => '4']]]);

        $one = $this->getJson('/tv/800/data?ekranas=1')->assertOk()->json();
        $this->assertSame([11], $one['court_ids']);
        $this->assertSame(['ana x' => 'Klaipėda'], $one['places']);       // Skycop 1 is not on this screen
        $two = $this->getJson('/tv/800/data?ekranas=2')->json();
        $this->assertSame([21], $two['court_ids']);
        $this->assertEqualsWithDelta(Tournament::tvFontShare(55, 2.0), $one['font_share'], 1e-9);   // screen 1: tournament default
        $this->assertEqualsWithDelta(Tournament::tvFontShare(75, 4.0), $two['font_share'], 1e-9);   // screen 2: its own size + distance
        $this->assertIsInt($one['server_ms']);

        // short addresses for the TVs on the tournament day
        $this->get('/tv/2')->assertOk()->assertSee('Dvi arenos · Skycop')->assertSee('ekranas=2', false);
        $this->get('/tv')->assertOk()->assertSee('ekranas=1', false)->assertSee('Vidus');   // 2 screens: a chooser, not a redirect
        $this->assertSame([21 => 'Skycop 1', 11 => 'Vidus 1'], ScheduleController::courtNames('800')); // natural name order
    }
}

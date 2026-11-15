<?php

namespace Tests\Feature;

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
}

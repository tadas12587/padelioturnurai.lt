<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournatedSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentTournatedLinkTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(array $attrs = []): Tournament
    {
        return Tournament::create($attrs + ['slug' => 'klubu-lyga-iii', 'status' => 'upcoming', 'date_start' => '2026-11-26', 'location' => 'Klaipėda']);
    }

    public function test_tournated_id_accepts_number_or_link(): void
    {
        $this->assertSame('11532', Tournament::parseTournatedId('11532'));
        $this->assertSame('11532', Tournament::parseTournatedId('#11532'));
        $this->assertSame('11532', Tournament::parseTournatedId('https://play.padel.lt/tournament/11532/order_of_play'));
        $this->assertNull(Tournament::parseTournatedId('padel'));
    }

    public function test_setting_changing_and_removing_the_id_controls_the_sync(): void
    {
        $t = $this->tournament(['tournated_id' => '11532']);
        $this->assertTrue(TournatedSync::where('tournament_external_id', '11532')->value('enabled'));

        $t->update(['tournated_id' => '12000']);
        $this->assertFalse(TournatedSync::where('tournament_external_id', '11532')->value('enabled'));
        $this->assertTrue(TournatedSync::where('tournament_external_id', '12000')->value('enabled'));

        $t->delete();
        $this->assertFalse(TournatedSync::where('tournament_external_id', '12000')->value('enabled'));
    }

    public function test_toggles_hide_schedule_and_stop_sync(): void
    {
        \App\Models\ScheduleSnapshot::create(['tournament_external_id' => '11532', 'payload' => ['matches' => [], 'tournament' => ['name' => 'X']]]);
        $t = $this->tournament(['tournated_id' => '11532']);
        $this->get('/grafikas/11532')->assertOk();

        $t->update(['show_schedule' => false, 'sync_enabled' => false]);
        $this->assertFalse(TournatedSync::where('tournament_external_id', '11532')->value('enabled'));
        // (the site's 404 view needs routes unavailable in tests, so check the exception itself)
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->get('/grafikas/11532/data');
    }

    public function test_stats_auto_mode_counts_tournaments_plus_history(): void
    {
        $this->tournament(['slug' => 'a', 'date_start' => '2024-05-01', 'participants_count' => 100]);
        $this->tournament(['slug' => 'b', 'date_start' => '2026-06-01', 'participants_count' => 50]);
        $this->tournament(['slug' => 'c', 'date_start' => now()->addYear()->toDateString(), 'participants_count' => 999]); // not held yet

        $p = \App\Models\Stat::create(['key' => 'total_participants', 'value' => 1, 'mode' => 'auto', 'base_value' => 1000, 'label_lt' => 'x', 'label_en' => 'x']);
        $n = \App\Models\Stat::create(['key' => 'total_tournaments', 'value' => 1, 'mode' => 'auto', 'label_lt' => 'x', 'label_en' => 'x']);
        $y = \App\Models\Stat::create(['key' => 'years_active', 'value' => 9, 'mode' => 'manual', 'label_lt' => 'x', 'label_en' => 'x']);

        $this->assertSame(1150, $p->display_value);
        $this->assertSame(2, $n->display_value);
        $this->assertSame(9, $y->display_value); // manual stays as typed
    }

    public function test_admin_list_and_edit_pages_render_with_sync_status(): void
    {
        $t = $this->tournament(['tournated_id' => '11532']);
        TournatedSync::where('tournament_external_id', '11532')->update(['last_attempt_at' => now(), 'last_success_at' => now(), 'match_count' => 72, 'last_source' => 'graphql']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/tournaments')->assertOk()->assertSee('Veikia');
        $this->get("/admin/tournaments/{$t->id}/edit")->assertOk()->assertSee('Mačų: <b>72</b>', false)->assertSee('Tournated turnyro ID');
    }
}

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

    public function test_admin_list_and_edit_pages_render_with_sync_status(): void
    {
        $t = $this->tournament(['tournated_id' => '11532']);
        TournatedSync::where('tournament_external_id', '11532')->update(['last_attempt_at' => now(), 'last_success_at' => now(), 'match_count' => 72, 'last_source' => 'graphql']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/tournaments')->assertOk()->assertSee('Veikia');
        $this->get("/admin/tournaments/{$t->id}/edit")->assertOk()->assertSee('Mačų: <b>72</b>', false)->assertSee('Tournated turnyro ID');
    }
}

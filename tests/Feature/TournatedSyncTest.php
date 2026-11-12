<?php

namespace Tests\Feature;

use App\Models\ScheduleSnapshot;
use App\Models\TournatedSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TournatedSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.tournated.key' => 'test-key',
            'services.tournated.base_url' => 'https://api.test/api/v2',
            'services.tournated.graphql_url' => 'https://gql.test/api/graphql',
        ]);
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/tournated/{$name}.json")), true);
    }

    private function empty(): array
    {
        return ['data' => [], 'meta' => ['page' => 1, 'limit' => 100, 'total' => 0, 'totalPages' => 0]];
    }

    private function fakeApi(bool $matchesFail = false, array $teams = [], ?array $matches = null): void
    {
        Http::fake([
            'api.test/api/v2/tournaments/*' => Http::response($this->fixture('tournament-10210')),
            'api.test/api/v2/groups*' => Http::response($this->fixture('groups-10210')),
            'api.test/api/v2/draws*' => Http::response($this->fixture('draws-10210')),
            'api.test/api/v2/standings*' => Http::response($this->fixture('standings-10210')),
            'api.test/api/v2/teams*' => Http::response($teams ? ['data' => $teams, 'meta' => ['totalPages' => 1]] : $this->empty()),
            'api.test/api/v2/matches*' => $matchesFail
                ? Http::response('<!DOCTYPE html><title>502: Bad gateway</title>', 502)
                : Http::response($matches ?? $this->fixture('matches-10210')),
            'gql.test/*' => Http::response($this->fixture('graphql-11532')),
        ]);
    }

    public function test_syncs_tournament_from_official_api(): void
    {
        $this->fakeApi();

        $this->artisan('tournated:sync', ['tournament' => ['10210']])->assertSuccessful();

        $p = ScheduleSnapshot::where('tournament_external_id', '10210')->value('payload');
        $this->assertSame('api', $p['source']);
        $this->assertSame('Lietuvos jaunių padelio čempionatas 2026', $p['tournament']['name']);
        $this->assertCount(8, $p['tournament']['courts']);
        $this->assertCount(3, $p['matches']);

        $m = collect($p['matches'])->firstWhere('match_id', 1301361);
        $this->assertSame('2026-11-07', $m['date']);
        $this->assertSame('11:30', $m['time']);
        $this->assertSame('Court 7', $m['court']['name']);
        $this->assertSame('Vaikinai U10 [LC]', $m['division']);
        $this->assertSame([['side1' => 0, 'side2' => 6], ['side1' => 0, 'side2' => 6]], $m['sets']);
        $this->assertSame(2, $m['winner_side']);
        $this->assertSame('Jokūbas', $m['participants'][0]['name']); // trailing space trimmed

        $this->assertSame('Group A', $p['groups'][0]['name']);
        $this->assertNotEmpty($p['groups'][0]['match_ids']);
        $this->assertSame('U10 M', $p['draws'][0]['title']);
        $this->assertSame(1, $p['standings'][0]['entries'][0]['position']);

        // cursor walk must start at id:0 (their un-cursored first page repeats rows)
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/matches') && $r['cursor'] === 'aWQ6MA');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'gql.test'));
    }

    public function test_never_stores_personal_data(): void
    {
        $this->fakeApi();
        $this->artisan('tournated:sync', ['tournament' => ['10210']])->assertSuccessful();

        $json = json_encode(ScheduleSnapshot::where('tournament_external_id', '10210')->value('payload'));
        foreach (['email', 'phone', 'birthdate', 'birth_year'] as $field) {
            $this->assertStringNotContainsString($field, $json);
        }
    }

    public function test_falls_back_to_graphql_when_matches_endpoint_fails(): void
    {
        $this->fakeApi(matchesFail: true);

        $this->artisan('tournated:sync', ['tournament' => ['11532']])->assertSuccessful();

        $p = ScheduleSnapshot::where('tournament_external_id', '11532')->value('payload');
        $this->assertSame('graphql', $p['source']);
        $this->assertCount(2, $p['matches']);

        // teamScore "6:3 6:1" but teamWinner is team2 -> sets flipped to team order
        $flipped = collect($p['matches'])->firstWhere('match_id', 1261848);
        $this->assertSame(2, $flipped['winner_side']);
        $this->assertSame(['side1' => 3, 'side2' => 6], $flipped['sets'][0]);
        $this->assertNotEmpty($flipped['division']);
        $this->assertNotNull($flipped['team1']['title']);
    }

    public function test_team_events_take_division_names_from_graphql(): void
    {
        // API rows sharing an id with the GraphQL fixture
        $api = $this->fixture('matches-10210');
        $api['data'][0]['match_id'] = 1261848;
        $this->fakeApi(teams: [['team_id' => 3408, 'team_name' => 'Pasitaškom']], matches: $api);

        $this->artisan('tournated:sync', ['tournament' => ['11532']])->assertSuccessful();

        $p = ScheduleSnapshot::where('tournament_external_id', '11532')->value('payload');
        $gqlDivision = $this->fixture('graphql-11532')['data']['tournamentAllMatches']['matchesArray'][0]['matches'][1]['name'];
        $this->assertSame('api', $p['source']);
        $this->assertSame($gqlDivision, collect($p['matches'])->firstWhere('match_id', 1261848)['division']);
        $this->assertSame('Pasitaškom', $p['teams'][0]['title']);
    }

    public function test_add_option_tracks_tournament_and_records_status(): void
    {
        $this->fakeApi();

        $this->artisan('tournated:sync', ['tournament' => ['10210'], '--add' => true])->assertSuccessful();

        $sync = TournatedSync::where('tournament_external_id', '10210')->first();
        $this->assertTrue($sync->enabled);
        $this->assertNotNull($sync->last_success_at);
        $this->assertSame('api', $sync->last_source);
        $this->assertSame(3, $sync->match_count);
        $this->assertNull($sync->last_error);
    }

    public function test_scheduled_run_skips_tournaments_not_due(): void
    {
        $this->fakeApi();
        $this->artisan('tournated:sync', ['tournament' => ['10210'], '--add' => true])->assertSuccessful();
        Http::swap(new HttpFactory);
        Http::fake(); // anything sent now would be recorded

        // 10210 ended 2026-11-08; a run right after the last one is not due
        $this->artisan('tournated:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_failure_keeps_previous_snapshot_and_records_error(): void
    {
        $this->fakeApi();
        $this->artisan('tournated:sync', ['tournament' => ['10210'], '--add' => true])->assertSuccessful();

        Http::swap(new HttpFactory);
        Http::fake(['*' => Http::response('<html>502</html>', 502)]);
        $this->artisan('tournated:sync', ['tournament' => ['10210']])->assertFailed();

        $this->assertCount(3, ScheduleSnapshot::where('tournament_external_id', '10210')->value('payload')['matches']);
        $this->assertStringContainsString('Mačų gauti nepavyko', TournatedSync::first()->last_error);
    }

    public function test_public_schedule_page_renders_synced_tournament(): void
    {
        $this->fakeApi();
        $this->artisan('tournated:sync', ['tournament' => ['10210']])->assertSuccessful();

        $this->get('/grafikas/10210')->assertOk()->assertSee('Baubkus');
    }
}

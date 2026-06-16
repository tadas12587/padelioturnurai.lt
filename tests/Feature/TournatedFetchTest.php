<?php

namespace Tests\Feature;

use App\Services\TournatedClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TournatedFetchTest extends TestCase
{
    public function test_groups_sends_origin_header_and_parses(): void
    {
        Http::fake([
            'api.tournated.com/*' => Http::response([
                'data' => ['groups' => [[
                    'id' => 1, 'name' => 'A', 'segment' => 'MD',
                    'entries' => [], 'matches' => [],
                ]]],
            ]),
        ]);

        $groups = (new TournatedClient)->groups(47817);

        $this->assertCount(1, $groups);
        $this->assertSame('A', $groups[0]['name']);
        Http::assertSent(fn ($req) =>
            $req->hasHeader('Origin', 'https://play.padel.lt')
            && str_contains($req->body(), '47817')
        );
    }

    public function test_groups_returns_empty_on_failure(): void
    {
        Http::fake(['api.tournated.com/*' => Http::response(null, 500)]);

        $groups = (new TournatedClient)->groups(999);

        $this->assertSame([], $groups);
    }

    public function test_tournament_returns_title_and_categories(): void
    {
        Http::fake([
            'api.tournated.com/*' => Http::response([
                'data' => ['tournament' => [
                    'title' => 'Test turnyras',
                    'tournamentCategory' => [
                        ['id' => 47817, 'category' => ['id' => 10265, 'name' => 'Vaikinai U10'], 'mde' => 4],
                    ],
                ]],
            ]),
        ]);

        $info = (new TournatedClient)->tournament(10229);

        $this->assertSame('Test turnyras', $info['title']);
        $this->assertCount(1, $info['tournamentCategory']);
        $this->assertSame(47817, $info['tournamentCategory'][0]['id']);
    }

    public function test_tournament_returns_empty_on_failure(): void
    {
        Http::fake(['api.tournated.com/*' => Http::response(null, 500)]);

        $this->assertSame([], (new TournatedClient)->tournament(999));
    }
}

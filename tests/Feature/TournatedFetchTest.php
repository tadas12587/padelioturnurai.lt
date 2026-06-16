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
}

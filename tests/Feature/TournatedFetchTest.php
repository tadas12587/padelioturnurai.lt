<?php

namespace Tests\Feature;

use App\Services\TournatedClient;
use Tests\TestCase;

class TournatedFetchTest extends TestCase
{
    public function test_groups_parses_transport_response(): void
    {
        $client = new TournatedClient(fn () => json_encode([
            'data' => ['groups' => [[
                'id' => 1, 'name' => 'A', 'segment' => 'MD',
                'entries' => [], 'matches' => [],
            ]]],
        ]));

        $groups = $client->groups(47817);

        $this->assertCount(1, $groups);
        $this->assertSame('A', $groups[0]['name']);
    }

    public function test_groups_sends_category_id_in_query(): void
    {
        $seen = null;
        $client = new TournatedClient(function (string $payload) use (&$seen) {
            $seen = $payload;
            return json_encode(['data' => ['groups' => []]]);
        });

        $client->groups(47817);

        $this->assertNotNull($seen);
        $this->assertStringContainsString('47817', $seen);
    }

    public function test_groups_returns_empty_on_failure(): void
    {
        $client = new TournatedClient(fn () => null);

        $this->assertSame([], $client->groups(999));
    }

    public function test_tournament_returns_title_and_categories(): void
    {
        $client = new TournatedClient(fn () => json_encode([
            'data' => ['tournament' => [
                'title' => 'Test turnyras',
                'tournamentCategory' => [
                    ['id' => 47817, 'category' => ['id' => 10265, 'name' => 'Vaikinai U10'], 'mde' => 4],
                ],
            ]],
        ]));

        $info = $client->tournament(10229);

        $this->assertSame('Test turnyras', $info['title']);
        $this->assertCount(1, $info['tournamentCategory']);
        $this->assertSame(47817, $info['tournamentCategory'][0]['id']);
    }

    public function test_tournament_returns_empty_on_failure(): void
    {
        $client = new TournatedClient(fn () => null);

        $this->assertSame([], $client->tournament(999));
    }
}

<?php

namespace Tests\Feature;

use App\Models\OverlaySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.overlay.ingest_token' => 'secret-token']);
    }

    public function test_ingest_rejects_missing_or_wrong_token(): void
    {
        $this->postJson('/overlay/ingest', ['tournament_id' => '10229'])
            ->assertStatus(403);

        $this->postJson('/overlay/ingest', ['tournament_id' => '10229'], ['X-Overlay-Token' => 'wrong'])
            ->assertStatus(403);

        $this->assertDatabaseCount('overlay_snapshots', 0);
    }

    public function test_ingest_stores_snapshot_with_valid_token(): void
    {
        $payload = [
            'tournament_id' => '10229',
            'title' => 'Test turnyras',
            'categories' => [
                ['id' => 47817, 'category' => ['id' => 1, 'name' => 'U10'], 'mde' => 4],
            ],
            'groups_by_category' => [
                '47817' => [['id' => 5, 'name' => 'A', 'entries' => [], 'matches' => []]],
            ],
            'category_stages' => ['47817' => ['has_groups' => true, 'has_bracket' => false]],
            'brackets_by_category' => ['53642' => ['rounds' => [], 'third' => null]],
        ];

        $this->postJson('/overlay/ingest', $payload, ['X-Overlay-Token' => 'secret-token'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $snapshot = OverlaySnapshot::where('tournament_external_id', '10229')->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('Test turnyras', $snapshot->payload['title']);
        $this->assertArrayHasKey('47817', $snapshot->payload['groups_by_category']);
        $this->assertTrue($snapshot->payload['category_stages']['47817']['has_groups']);
        $this->assertArrayHasKey('53642', $snapshot->payload['brackets_by_category']);
    }

    public function test_ingest_overwrites_previous_snapshot(): void
    {
        OverlaySnapshot::create([
            'tournament_external_id' => '10229',
            'payload' => ['title' => 'old', 'categories' => [], 'groups_by_category' => []],
        ]);

        $this->postJson('/overlay/ingest', [
            'tournament_id' => '10229',
            'title' => 'new',
        ], ['X-Overlay-Token' => 'secret-token'])->assertOk();

        $this->assertDatabaseCount('overlay_snapshots', 1);
        $this->assertSame('new', OverlaySnapshot::first()->payload['title']);
    }
}

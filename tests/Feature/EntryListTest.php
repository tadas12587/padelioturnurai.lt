<?php

namespace Tests\Feature;

use App\Models\EntryList;
use App\Models\OverlaySnapshot;
use App\Services\OverlayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryListTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_entry_list_overrides_participants_for_the_draw(): void
    {
        // Tournament categories (as loaded from Tournated) — no participants yet.
        OverlaySnapshot::create(['tournament_external_id' => '10931', 'payload' => [
            'categories' => [
                ['id' => 59197, 'category' => ['id' => 13176, 'name' => 'BESSO - Masters grupė (A-)']],
                ['id' => 59198, 'category' => ['id' => 13177, 'name' => 'KAVOSZINOVAI.LT Vyrų LIGHT PLUS grupė (C+, B-)']],
            ],
            'participants_by_category' => [],
            'matches' => [],
        ]]);

        // Imported Excel keyed by normalised category name.
        EntryList::create(['tournament_external_id' => '10931', 'source_name' => 'list.xlsx', 'data' => [
            EntryList::normCategory('BESSO - Masters grupė (A-)') => [
                ['id' => 'xls-1', 'name' => 'Gerdas Zemeckas / Martynas Dakanis', 'seed' => null, 'pot' => null],
                ['id' => 'xls-2', 'name' => 'Aidas Akcijonaitis / Egidijus Gudavičius', 'seed' => '1', 'pot' => null],
            ],
        ]]);

        $data = app(OverlayData::class);

        // The draw pulls participants for category 59197 → gets the imported pairs.
        $teams = $data->participants('10931', 59197);
        $this->assertCount(2, $teams);
        $this->assertSame('Gerdas Zemeckas / Martynas Dakanis', $teams[0]['name']);

        // A category without an imported list falls back to (empty) scraped data.
        $this->assertSame([], $data->participants('10931', 59198));
    }

    public function test_reimport_replaces_the_previous_list(): void
    {
        EntryList::create(['tournament_external_id' => '10931', 'data' => ['a' => [['id' => 1, 'name' => 'X / Y']]]]);

        EntryList::updateOrCreate(
            ['tournament_external_id' => '10931'],
            ['data' => ['b' => [['id' => 2, 'name' => 'Z / W']]]],
        );

        $this->assertSame(1, EntryList::where('tournament_external_id', '10931')->count());
        $this->assertArrayHasKey('b', EntryList::where('tournament_external_id', '10931')->value('data'));
        $this->assertArrayNotHasKey('a', EntryList::where('tournament_external_id', '10931')->value('data'));
    }
}

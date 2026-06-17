<?php

namespace Tests\Feature;

use App\Filament\Pages\DrawControlPage;
use App\Models\Overlay;
use App\Models\OverlaySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DrawControlTest extends TestCase
{
    use RefreshDatabase;

    private function overlayWithDrawWindow(): Overlay
    {
        OverlaySnapshot::create(['tournament_external_id' => '10424', 'payload' => [
            'participants_by_category' => ['53636' => [
                ['id' => 1, 'name' => 'A / B', 'pot' => 1],
                ['id' => 2, 'name' => 'C / D', 'pot' => 1],
            ]],
        ]]);

        return Overlay::create([
            'name' => 'D', 'type' => 'group_standings', 'tournament_external_id' => '10424',
            'windows' => [[
                'id' => 'w1', 'type' => 'draw', 'name' => 'Traukimas', 'category_id' => 53636,
                'format' => 'groups', 'group_count' => 2, 'group_size' => 1, 'use_pots' => true,
            ]],
            'state' => ['active_window_id' => null, 'next_match' => ''],
        ]);
    }

    public function test_load_participants_freezes_pool_into_state(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants');

        $overlay->refresh();
        $this->assertCount(2, $overlay->state['draws']['w1']['teams']);
        $this->assertSame('A / B', $overlay->state['draws']['w1']['teams'][0]['name']);
    }

    public function test_draw_places_a_team_and_undo_removes_it(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        $comp = Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants')
            ->call('drawNext');

        $overlay->refresh();
        $placed = array_filter($overlay->state['draws']['w1']['slots'], fn ($t) => $t !== null);
        $this->assertCount(1, $placed);

        $comp->call('undo');
        $overlay->refresh();
        $placed = array_filter($overlay->state['draws']['w1']['slots'], fn ($t) => $t !== null);
        $this->assertCount(0, $placed);
    }

    public function test_select_slot_then_place_team(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants')
            ->call('selectSlot', 'A1')
            ->call('placeTeam', '1');

        $this->assertSame('1', (string) $overlay->fresh()->state['draws']['w1']['slots']['A1']);
    }

    public function test_place_bye_into_selected_slot(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants')
            ->call('selectSlot', 'B1')
            ->call('placeBye');

        $this->assertSame('BYE', $overlay->fresh()->state['draws']['w1']['slots']['B1']);
    }

    public function test_add_and_remove_team_edits_pool(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        $comp = Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('loadParticipants')
            ->set('newTeamName', 'Naujas / Žaidėjas')
            ->call('addTeam');

        $teams = $overlay->fresh()->state['draws']['w1']['teams'];
        $this->assertCount(3, $teams);
        $this->assertSame('Naujas / Žaidėjas', end($teams)['name']);

        $comp->call('removeTeam', (string) $teams[0]['id']);
        $this->assertCount(2, $overlay->fresh()->state['draws']['w1']['teams']);
    }

    public function test_play_sets_active_window(): void
    {
        $overlay = $this->overlayWithDrawWindow();

        Livewire::test(DrawControlPage::class)
            ->set('overlayId', $overlay->id)
            ->set('windowId', 'w1')
            ->call('play');

        $this->assertSame('w1', $overlay->fresh()->state['active_window_id']);
    }
}

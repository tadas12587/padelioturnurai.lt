<?php

namespace Tests\Feature;

use App\Models\PlayerPhoto;
use App\Services\PlayerCityImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerCityImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_sets_city_only_for_existing_players_with_a_city(): void
    {
        PlayerPhoto::create(['person_key' => 'jonas petraitis', 'name' => 'Jonas Petraitis', 'gender' => 'V']);
        PlayerPhoto::create(['person_key' => 'antanas kazlauskas', 'name' => 'Antanas Kazlauskas', 'gender' => 'V', 'city' => 'Senas Miestas']);

        $n = PlayerCityImporter::apply([
            'jonas petraitis'     => 'Vilnius',
            'antanas kazlauskas'  => 'Kaunas',   // overwrites the stale value
            'nera tokio zaidejo'  => 'Klaipėda', // no matching player -> ignored
        ]);

        $this->assertSame(2, $n);
        $this->assertSame('Vilnius', PlayerPhoto::where('person_key', 'jonas petraitis')->value('city'));
        $this->assertSame('Kaunas', PlayerPhoto::where('person_key', 'antanas kazlauskas')->value('city'));
    }

    public function test_apply_never_creates_new_players(): void
    {
        $n = PlayerCityImporter::apply(['nobody here' => 'Vilnius']);

        $this->assertSame(0, $n);
        $this->assertSame(0, PlayerPhoto::count());
    }

    public function test_apply_skips_players_missing_from_the_map(): void
    {
        PlayerPhoto::create(['person_key' => 'x y', 'name' => 'X Y', 'gender' => 'V', 'city' => 'Kept']);

        // Empty map -> nothing changes.
        $n = PlayerCityImporter::apply([]);

        $this->assertSame(0, $n);
        $this->assertSame('Kept', PlayerPhoto::where('person_key', 'x y')->value('city'));
    }
}

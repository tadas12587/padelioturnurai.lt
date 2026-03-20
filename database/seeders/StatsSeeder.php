<?php

namespace Database\Seeders;

use App\Models\Stat;
use Illuminate\Database\Seeder;

class StatsSeeder extends Seeder
{
    public function run(): void
    {
        $stats = [
            [
                'key' => 'total_participants',
                'value' => 0,
                'label_lt' => 'Viso dalyvių',
                'label_en' => 'Total participants',
            ],
            [
                'key' => 'total_tournaments',
                'value' => 1,
                'label_lt' => 'Turnyrų surengta',
                'label_en' => 'Tournaments held',
            ],
            [
                'key' => 'years_active',
                'value' => 4,
                'label_lt' => 'Metų aktyvūs',
                'label_en' => 'Years active',
            ],
        ];

        foreach ($stats as $stat) {
            Stat::create($stat);
        }
    }
}

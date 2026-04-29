<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThaiGeographySeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('data/thai_geography.php');

        // Insert provinces
        $chunks = array_chunk($data['provinces'], 50);
        foreach ($chunks as $chunk) {
            DB::table('thai_provinces')->insertOrIgnore($chunk);
        }
        $this->command->info('Inserted ' . count($data['provinces']) . ' provinces.');

        // Insert districts
        $chunks = array_chunk($data['districts'], 100);
        foreach ($chunks as $chunk) {
            DB::table('thai_districts')->insertOrIgnore($chunk);
        }
        $this->command->info('Inserted ' . count($data['districts']) . ' districts.');

        // Insert subdistricts
        $chunks = array_chunk($data['subdistricts'], 200);
        foreach ($chunks as $chunk) {
            DB::table('thai_subdistricts')->insertOrIgnore($chunk);
        }
        $this->command->info('Inserted ' . count($data['subdistricts']) . ' subdistricts.');
    }
}

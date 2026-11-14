<?php

namespace Database\Seeders;

use App\Models\Seksi;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SeksiSeeder::class,
            RakArsipSeeder::class,
        ]);

        $seksi = Seksi::query()->where('nama_seksi', 'Pemerintahan')->first();

        User::query()->updateOrCreate(
            ['email' => 'admin@grogolselatan.local'],
            [
                'name' => 'Admin Arsip',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
                'seksi_id' => $seksi?->id,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'staf@grogolselatan.local'],
            [
                'name' => 'Staf Pengarsipan',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'staf',
                'seksi_id' => $seksi?->id,
            ],
        );
    }
}

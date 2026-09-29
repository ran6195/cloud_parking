<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Page::create([
            'title' => 'Contatti',
            'slug' => 'contatti',
            'is_published' => true,
            'excerpt' => 'Richiedi informazioni.',
            'blocks' => [
                [
                    'type' => 'contact_form',
                    'data' => [
                        'heading' => 'Richiedi informazioni',
                        'submit_label' => 'Invia richiesta',
                    ],
                ],
            ],
        ]);

        Page::create([
            'title' => 'Novità',
            'slug' => 'novita',
            'is_published' => true,
            'excerpt' => 'Le ultime novità.',
            'blocks' => [
                [
                    'type' => 'hub_list',
                    'data' => [
                        'heading' => 'Novità',
                    ],
                ],
            ],
        ]);

        $this->call(LandingPageSeeder::class);
    }
}

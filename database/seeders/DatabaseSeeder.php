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
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => true,
            'is_published' => true,
            'excerpt' => 'Pagina di esempio generata dal CMS.',
            'meta_description' => 'Sito vetrina di esempio generato dal CMS.',
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Benvenuto sul tuo nuovo sito',
                        'subheading' => 'Contenuto di esempio: modifica o elimina questa pagina dal pannello Filament.',
                        'cta_label' => 'Contattaci',
                        'cta_url' => '/contatti',
                    ],
                ],
            ],
        ]);

        Page::create([
            'title' => 'Contatti',
            'slug' => 'contatti',
            'is_published' => true,
            'show_in_menu' => true,
            'menu_order' => 1,
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
            'show_in_menu' => true,
            'menu_order' => 2,
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
    }
}

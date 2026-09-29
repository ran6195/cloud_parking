<?php

namespace Tests\Feature;

use App\Settings\GeneralSettings;
use Database\Seeders\LandingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(GeneralSettings::class);
        $settings->site_name = 'Sosta BLQ';
        $settings->phone = '051 000 0000';
        $settings->save();

        $this->seed(LandingPageSeeder::class);
    }

    public function test_home_renders_all_main_sections(): void
    {
        $response = $this->get('/')->assertOk();

        $response
            ->assertSee('Lasci l&#039;auto da noi, al volo ci pensiamo insieme.', false)
            ->assertSee('Aeroporto di Bologna')
            ->assertSee('id="prenota"', false)
            ->assertSee('Calcola il prezzo')
            ->assertSee('Navetta inclusa')
            ->assertSee('id="come"', false)
            ->assertSee('id="tariffe"', false)
            ->assertSee('Chi è partito con noi')
            ->assertSee('id="dove"', false)
            ->assertSee('id="domande"', false)
            ->assertSee('Il posto è pronto. A te resta solo la valigia.')
            ->assertSee('Sosta BLQ');
    }

    public function test_home_has_a_single_h1_and_structured_data(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('"@type":"ParkingFacility"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('<html lang="it">', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
    }

    public function test_placeholders_are_replaced_with_settings(): void
    {
        $this->get('/')
            ->assertSee('5 minuti in navetta, inclusa nel prezzo')
            ->assertSee('Chiamaci al 051 000 0000')
            ->assertDontSee('{minuti}')
            ->assertDontSee('{telefono}');
    }

    public function test_pricing_table_reads_tariffs_from_settings(): void
    {
        $this->get('/')
            ->assertSee('Fino a 3 giorni')
            ->assertSee('€ 25')
            ->assertSee('Posto coperto');
    }

    public function test_legal_placeholder_pages_exist(): void
    {
        $this->get('/privacy')->assertOk();
        $this->get('/cookie')->assertOk();
        $this->get('/condizioni-del-servizio')->assertOk();
    }
}

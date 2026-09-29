<?php

namespace Tests\Feature;

use App\Mail\NewLeadReceived;
use App\Models\Page;
use App\Models\Redirect;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_page_flagged_as_homepage(): void
    {
        Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => true,
            'is_published' => true,
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Ciao mondo']],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Ciao mondo');
    }

    public function test_unpublished_page_returns_404(): void
    {
        Page::create([
            'title' => 'Bozza',
            'slug' => 'bozza',
            'is_published' => false,
        ]);

        $this->get('/bozza')->assertNotFound();
    }

    public function test_missing_page_falls_back_to_redirect(): void
    {
        Redirect::create([
            'from' => '/vecchia-pagina',
            'to' => '/nuova-pagina',
            'status_code' => 301,
        ]);

        $this->get('/vecchia-pagina')->assertRedirect('/nuova-pagina');
    }

    public function test_missing_page_without_redirect_returns_404(): void
    {
        $this->get('/non-esiste')->assertNotFound();
    }

    public function test_lead_form_submission_is_stored(): void
    {
        Page::create([
            'title' => 'Contatti',
            'slug' => 'contatti',
            'is_published' => true,
        ]);

        $response = $this->post('/contatti', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'message' => 'Richiesta di prova',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
        ]);
    }

    public function test_lead_notification_is_sent_immediately_without_queue(): void
    {
        Mail::fake();

        $settings = app(GeneralSettings::class);
        $settings->email = 'gestore@example.com';
        $settings->save();

        $this->post('/contatti', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
        ])->assertRedirect();

        Mail::assertSent(NewLeadReceived::class, fn ($mail) => $mail->hasTo('gestore@example.com'));
        Mail::assertNothingQueued();
    }

    public function test_lead_is_saved_even_if_the_mailer_fails(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->email = 'gestore@example.com';
        $settings->save();

        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP non raggiungibile'));

        $this->post('/contatti', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
        ])->assertRedirect()->assertSessionHas('lead_sent', true);

        $this->assertDatabaseHas('leads', ['email' => 'mario@example.com']);
    }

    public function test_lead_form_honeypot_silently_rejects_bots(): void
    {
        $this->post('/contatti', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'website' => 'https://spam.example.com',
        ]);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_sitemap_and_robots_are_reachable(): void
    {
        Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => true,
            'is_published' => true,
        ]);

        $this->get('/sitemap.xml')->assertOk();
        $this->get('/robots.txt')->assertOk();
    }
}

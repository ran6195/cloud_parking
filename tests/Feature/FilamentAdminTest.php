<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_load_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/pages/create')->assertOk();
        $this->get('/admin/redirects')->assertOk();
        $this->get('/admin/redirects/create')->assertOk();
        $this->get('/admin/leads')->assertOk();
        $this->get('/admin/manage-general-settings')->assertOk();
        $this->get('/admin/manage-parking-settings')->assertOk();
    }

    public function test_admin_pages_redirect_guests_to_login(): void
    {
        $this->get('/admin/pages')->assertRedirect('/admin/login');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_once_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertOk();
    }

    public function test_authenticated_admin_does_not_loop_between_root_and_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect('/admin');

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect('/admin');
    }

    public function test_authenticated_worker_root_goes_to_worker_home(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)
            ->get('/')
            ->assertRedirect('/worker');
    }

    public function test_login_sets_session_and_reaches_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'username' => 'adminloop',
            'role' => 'admin',
            'password' => 'password',
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'username' => 'adminloop',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);

        $this->get('/admin')->assertOk();
        $this->get('/')->assertRedirect('/admin');
    }
}

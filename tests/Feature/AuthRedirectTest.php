<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_serves_login_without_redirect(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Connectez-vous', false);
    }

    public function test_guest_login_always_returns_200(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Connectez-vous', false);
    }

    public function test_guest_login_ok_and_cookies_are_http_friendly(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $cookies = $response->headers->all('Set-Cookie');
        $joined = implode("\n", $cookies);

        $this->assertStringContainsString('gropro-session=', $joined);
        $this->assertDoesNotMatchRegularExpression('/secure/i', $joined);
        $this->assertMatchesRegularExpression('/samesite=lax/i', $joined);
        $this->assertDoesNotMatchRegularExpression('/domain=/i', $joined);
    }

    public function test_forwarded_proto_https_does_not_force_https_redirects(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => '192.168.100.33:8000',
        ])->get('/');

        $response->assertOk();
        $location = $response->headers->get('Location');
        $this->assertTrue($location === null || ! str_contains($location, 'https://'));

        $login = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
        ])->actingAs(User::factory()->create(['role' => 'admin']))->get('/login');

        $login->assertRedirect('/admin');
        $this->assertStringNotContainsString('https://', (string) $login->headers->get('Location'));
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

    public function test_authenticated_with_intended_root_cannot_bounce_slash_and_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Stale phone tab: session auth + url.intended=/ (classic bounce fuel).
        $fromLogin = $this->actingAs($admin)
            ->withSession(['url.intended' => '/'])
            ->get('/login');

        $fromLogin->assertRedirect('/admin');
        $this->assertNotSame('/', $fromLogin->headers->get('Location'));
        $this->assertNotSame('/login', $fromLogin->headers->get('Location'));

        $fromRoot = $this->actingAs($admin)
            ->withSession(['url.intended' => '/'])
            ->get('/');

        $fromRoot->assertRedirect('/admin');

        // Manual multi-hop: at most one redirect, never back to the other entry.
        $hop1 = $this->actingAs($admin)->withSession(['url.intended' => '/'])->get('/login');
        $hop1->assertRedirect('/admin');

        $hop2 = $this->actingAs($admin)->get('/admin');
        $hop2->assertOk();

        // followingRedirects must settle (framework follows; we assert final 200).
        $this->actingAs($admin)
            ->withSession(['url.intended' => '/'])
            ->followingRedirects()
            ->get('/login')
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['url.intended' => '/'])
            ->followingRedirects()
            ->get('/')
            ->assertOk();
    }

    public function test_authenticated_follow_root_reaches_dashboard_in_one_hop(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get('/')
            ->assertOk();
    }

    public function test_authenticated_worker_root_goes_to_worker_home(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)
            ->get('/')
            ->assertRedirect('/worker');
    }

    public function test_wrong_role_is_forbidden_not_redirect_loop(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)
            ->get('/admin')
            ->assertForbidden();
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

    public function test_login_ignores_intended_slash(): void
    {
        $admin = User::factory()->create([
            'username' => 'intendslash',
            'role' => 'admin',
            'password' => 'password',
        ]);

        $this->withSession(['url.intended' => '/'])
            ->post('/login', [
                'username' => 'intendslash',
                'password' => 'password',
            ])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_https_intended_url_is_ignored_after_login(): void
    {
        $admin = User::factory()->create([
            'username' => 'safeintend',
            'role' => 'admin',
            'password' => 'password',
        ]);

        $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post('/login', [
                'username' => 'safeintend',
                'password' => 'password',
            ])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_guest_follow_redirects_from_protected_page_stays_bounded(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');

        $this->followingRedirects()
            ->get('/admin')
            ->assertOk()
            ->assertSee('Connectez-vous', false);
    }

    public function test_inactive_authenticated_user_gets_login_200_not_bounce(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => false]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Connectez-vous', false);

        $this->actingAs($admin)
            ->get('/login')
            ->assertOk();
    }
}

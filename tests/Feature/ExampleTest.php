<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting `/` are sent to login (auth gate).
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}

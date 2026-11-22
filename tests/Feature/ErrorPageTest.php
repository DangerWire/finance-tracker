<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_404_page_is_rendered_for_missing_routes(): void
    {
        $response = $this->get('/non-existent-route-404');

        $response->assertNotFound();
        $response->assertSee('404');
        $response->assertSee('This is not the web page you are looking for');
        $response->assertSee('Dave or Malvine');
        $response->assertSee('Back to Home');
        $response->assertSee('Log in');
    }

    public function test_custom_404_page_shows_authenticated_navigation_when_logged_in(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/non-existent-route-for-authenticated-user');

        $response->assertNotFound();
        $response->assertSee('404');
        $response->assertSee('Dave or Malvine');
        $response->assertSee('Back to Dashboard');
        $response->assertSee('View Transactions');
    }
}

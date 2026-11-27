<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
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

    public function test_unhandled_server_error_renders_unified_error_page_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);

        Route::middleware('web')->get('/_test/crash-500', function (): never {
            throw new RuntimeException('Sensitive internal detail that must stay in logs');
        });

        $response = $this->get('/_test/crash-500');

        $response->assertStatus(500);
        $response->assertSee('500');
        $response->assertSee('Something went wrong');
        $response->assertSee('Dave or Malvine');
        $response->assertSee('Try again');
        $response->assertDontSee('Sensitive internal detail that must stay in logs');
    }

    public function test_http_errors_403_419_and_503_render_unified_error_page_with_tailored_copy(): void
    {
        Route::middleware('web')->get('/_test/abort-403', fn () => abort(403));
        Route::middleware('web')->get('/_test/abort-419', fn () => abort(419));
        Route::middleware('web')->get('/_test/abort-503', fn () => abort(503));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/_test/abort-403')
            ->assertForbidden()
            ->assertSee('403')
            ->assertSee('Access denied')
            ->assertSee('Dave or Malvine')
            ->assertSee('Back to Dashboard');

        $this->get('/_test/abort-419')
            ->assertStatus(419)
            ->assertSee('419')
            ->assertSee('Page expired')
            ->assertSee('Try again');

        $this->get('/_test/abort-503')
            ->assertStatus(503)
            ->assertSee('503')
            ->assertSee('Service unavailable')
            ->assertSee('Try again');
    }

    public function test_json_requests_still_receive_json_errors_instead_of_html(): void
    {
        Route::middleware('web')->get('/_test/json-500', function (): never {
            throw new RuntimeException('Boom');
        });

        $this->getJson('/_test/json-500')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_auth_redirects_are_not_intercepted_by_error_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}

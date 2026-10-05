<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transactions')
            ->assertSee('Income');
    }

    public function test_user_can_switch_to_indonesian(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('transactions.index'))
            ->put(route('locale.update', 'id'))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas(SetLocale::SESSION_KEY, 'id');

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transaksi')
            ->assertSee('Pemasukan')
            ->assertSee('Pengeluaran');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/locale/fr')
            ->assertNotFound();
    }

    public function test_locale_persists_across_requests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('locale.update', 'id'));

        $this->actingAs($user)->get(route('insights.index'))->assertSee('Wawasan');
        $this->actingAs($user)->get(route('transactions.index'))->assertSee('Transaksi');
    }

    public function test_switching_back_to_english_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('locale.update', 'id'));
        $this->actingAs($user)->get(route('transactions.index'))->assertSee('Transaksi');

        $this->actingAs($user)->put(route('locale.update', 'en'));
        $this->actingAs($user)->get(route('transactions.index'))->assertSee('Transactions');
    }

    public function test_external_redirect_targets_are_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('transactions.index'))
            ->put(route('locale.update', 'id'), ['redirect' => 'https://evil.test/phish'])
            ->assertRedirect(route('transactions.index'));
    }

    public function test_protocol_relative_redirect_targets_are_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('transactions.index'))
            ->put(route('locale.update', 'id'), ['redirect' => '//evil.test'])
            ->assertRedirect(route('transactions.index'));
    }

    public function test_internal_redirect_target_is_honoured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('transactions.index'))
            ->put(route('locale.update', 'id'), ['redirect' => route('insights.index')])
            ->assertRedirect(route('insights.index'));
    }

    public function test_language_can_be_switched_before_signing_in(): void
    {
        $this->from('/login')
            ->put(route('locale.update', 'id'))
            ->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_translation_file_covers_every_key_used_in_views(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('id.json')), true, 512, JSON_THROW_ON_ERROR);

        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all("/__\('((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                // Blade escapes apostrophes as \', which is what the key
                // literally contains inside the translation file.
                if (! array_key_exists($key, $translations)) {
                    $missing[$key][] = basename($file);
                }
            }
        }

        $this->assertSame([], $missing, 'Untranslated keys: '.implode(', ', array_keys($missing)));
    }

    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

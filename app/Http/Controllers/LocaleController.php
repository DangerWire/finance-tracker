<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the active language and return the visitor to where they were.
     *
     * The previous URL is only honoured when it points at this application, so
     * a crafted link cannot bounce someone to an external site.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        // Read from the request body: the switcher posts a hidden field.
        $intended = $request->input('redirect');

        if (is_string($intended) && $this->isSafeRedirect($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->back(fallback: route('dashboard'));
    }

    /**
     * Only allow redirects that stay on this application.
     */
    private function isSafeRedirect(string $url): bool
    {
        // Header injection guard.
        if (str_contains($url, "\r") || str_contains($url, "\n")) {
            return false;
        }

        // Protocol-relative URLs such as //evil.test point elsewhere.
        if (str_starts_with($url, '//')) {
            return false;
        }

        // A relative path is safe by definition.
        if (str_starts_with($url, '/')) {
            return true;
        }

        // An absolute URL is only safe when it targets this application's own
        // host; url()->current() sends exactly this form.
        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host !== null && $host !== false && $host === $appHost;
    }
}

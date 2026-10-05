<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language to the request.
 *
 * The locale lives in the session rather than on the user record so a language
 * can be switched before signing in, which matters for a shared login page.
 */
class SetLocale
{
    /**
     * Locales the application offers.
     *
     * @var array<int, string>
     */
    public const SUPPORTED = ['en', 'id'];

    /**
     * The session key holding the active locale.
     */
    public const SESSION_KEY = 'locale';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(self::SESSION_KEY);

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}

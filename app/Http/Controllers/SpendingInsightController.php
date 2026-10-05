<?php

namespace App\Http\Controllers;

use App\Services\SpendingInsightService;
use App\Services\SpendingStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SpendingInsightController extends Controller
{
    /**
     * Show the insights page with the deterministic statistics.
     *
     * The numbers render without any AI involvement, so the page still works
     * when no provider key is configured.
     */
    public function index(Request $request, SpendingStatsService $stats): View
    {
        $convert = (bool) $request->user()->prefers_base_currency;

        $payload = $stats->forUser($request->user()->id, inBaseCurrency: $convert);

        return view('insights.index', [
            'stats' => $payload,
            'insights' => null,
            'headline' => null,
            'isConfigured' => app(SpendingInsightService::class)->isConfigured(),
            'hasEnoughData' => $this->hasEnoughData($payload),
            'convert' => $convert,
            'error' => null,
        ]);
    }

    /**
     * Toggle whether figures are converted into the base currency.
     */
    public function updatePreference(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prefers_base_currency' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'prefers_base_currency' => $validated['prefers_base_currency'],
        ]);

        return redirect()
            ->route('insights.index')
            ->with('status', __('Preference saved.'));
    }

    /**
     * Ask the model to narrate the precomputed statistics.
     */
    public function generate(
        Request $request,
        SpendingStatsService $stats,
        SpendingInsightService $insights,
    ): RedirectResponse|View {
        $convert = (bool) $request->user()->prefers_base_currency;

        $payload = $stats->forUser($request->user()->id, inBaseCurrency: $convert);

        if (! $this->hasEnoughData($payload)) {
            return back()->withErrors([
                'insights' => 'Record at least '.config('insights.minimum_transactions')
                    .' expenses this month before generating insights.',
            ]);
        }

        if (! $insights->isConfigured()) {
            return back()->withErrors([
                'insights' => 'Set OPENROUTER_API_KEY in your .env file to enable AI insights.',
            ]);
        }

        try {
            $result = $insights->analyze($payload);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'insights' => SpendingInsightService::describeFailure($exception),
            ]);
        }

        return view('insights.index', [
            'stats' => $payload,
            'insights' => $result['insights'],
            'headline' => $result['headline'],
            'isConfigured' => true,
            'hasEnoughData' => true,
            'convert' => $convert,
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasEnoughData(array $payload): bool
    {
        return $payload['transaction_count'] >= config('insights.minimum_transactions');
    }
}

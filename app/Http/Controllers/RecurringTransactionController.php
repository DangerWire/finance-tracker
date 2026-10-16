<?php

namespace App\Http\Controllers;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Http\Requests\StoreRecurringTransactionRequest;
use App\Http\Requests\UpdateRecurringTransactionRequest;
use App\Models\RecurringTransaction;
use App\Services\CategoryService;
use App\Services\RecurringTransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringTransactionController extends Controller
{
    /**
     * Display the user's templates and what is coming up.
     */
    public function index(Request $request): View
    {
        $today = CarbonImmutable::now()->startOfDay();

        return view('recurring.index', [
            'recurring' => $request->user()->recurringTransactions()
                ->orderByDesc('is_active')
                ->orderBy('next_occurrence_on')
                ->paginate(15)
                ->withQueryString(),
            'upcoming' => $request->user()->recurringTransactions()
                ->upcoming($today, 30)
                ->get(),
            'today' => $today,
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(Request $request, CategoryService $categories): View
    {
        return view('recurring.create', [
            'recurring' => new RecurringTransaction([
                'currency' => (string) config('finance.base_currency'),
                'type' => TransactionType::Expense,
                'frequency' => RecurringFrequency::Monthly,
                'auto_post' => true,
                'is_active' => true,
                'starts_on' => CarbonImmutable::now(),
            ]),
            'types' => TransactionType::cases(),
            'frequencies' => RecurringFrequency::cases(),
            'categories' => $categories->forUser($request->user()->id),
        ]);
    }

    /**
     * Store a new template.
     */
    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $recurring = $request->user()->recurringTransactions()->create(
            $this->attributesFrom($request->validated())
        );

        return redirect()
            ->route('recurring.index')
            ->with('status', 'Recurring transaction created.');
    }

    /**
     * Show a single template.
     */
    public function show(Request $request, RecurringTransaction $recurring): View
    {
        $this->authorizeOwnership($request, $recurring);

        return view('recurring.show', [
            'recurring' => $recurring,
            'generated' => $recurring->transactions()->latest('occurred_at')->paginate(20),
        ]);
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(Request $request, RecurringTransaction $recurring, CategoryService $categories): View
    {
        $this->authorizeOwnership($request, $recurring);

        return view('recurring.edit', [
            'recurring' => $recurring,
            'types' => TransactionType::cases(),
            'frequencies' => RecurringFrequency::cases(),
            'categories' => $categories->forUser($request->user()->id),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurring): RedirectResponse
    {
        $this->authorizeOwnership($request, $recurring);

        // attributesFrom derives the cursor from the submitted frequency, so a
        // template whose schedule changes stops firing on the old cadence
        // instead of keeping its previous next_occurrence_on.
        $recurring->update($this->attributesFrom($request->validated()));

        return redirect()
            ->route('recurring.index')
            ->with('status', 'Recurring transaction updated.');
    }

    /**
     * Delete a template.
     *
     * Transactions it already produced are kept. Deleting a template is a
     * statement about the future, not a reason to rewrite history.
     */
    public function destroy(Request $request, RecurringTransaction $recurring): RedirectResponse
    {
        $this->authorizeOwnership($request, $recurring);

        $recurring->delete();

        return redirect()
            ->route('recurring.index')
            ->with('status', 'Recurring transaction deleted.');
    }

    /**
     * Run the generator for the authenticated user now.
     */
    public function run(Request $request, RecurringTransactionRunner $runner): RedirectResponse
    {
        $result = $runner->run($request->user(), CarbonImmutable::now()->startOfDay());

        return redirect()
            ->route('recurring.index')
            ->with('status', $result['posted'] === 1
                ? 'Posted 1 transaction.'
                : 'Posted '.$result['posted'].' transactions.');
    }

    /**
     * Build the attributes, deriving the schedule cursor.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated): array
    {
        $startsOn = CarbonImmutable::createFromFormat('Y-m-d', $validated['starts_on'])->startOfDay();
        $frequency = $validated['frequency'] instanceof RecurringFrequency
            ? $validated['frequency']
            : RecurringFrequency::from($validated['frequency']);

        // The first occurrence is the first one that is not in the past, so a
        // template added today does not immediately fire for a start date last
        // month. next_occurrence_on is NOT NULL, so it always needs a value.
        $next = $frequency->nextOccurrenceAfter($startsOn, CarbonImmutable::now()->startOfDay()->subDay());

        return [
            ...$validated,
            'frequency' => $frequency,
            'starts_on' => $startsOn,
            'next_occurrence_on' => $next,
        ];
    }

    /**
     * Ensure the template belongs to the authenticated user.
     */
    private function authorizeOwnership(Request $request, RecurringTransaction $recurring): void
    {
        abort_unless($recurring->user_id === $request->user()->id, 403);
    }
}

# Project Plan: Finance Tracker - Proactive Budgeting, Visual Analytics & AI Receipt Scanner

**Plan File:** `docs/PLAN-finance-roadmap.md`  
**Status:** DRAFT (Awaiting Execution via `/create`)  
**Project Type:** WEB (Laravel 13, PHP 8.3, Blade, Tailwind CSS, Alpine.js, PostgreSQL/SQLite)  
**Target Goal:** Transform Finance Tracker into an intelligent, proactive financial companion with category budgets, interactive visual analytics, and multimodal AI receipt scanning.

---

## 1. Overview & Context

Finance Tracker currently aggregates multi-currency transactions into a frozen base currency snapshot (`base_amount`, default `CNY`), calculates server-side spending statistics, and provides narrative AI insights via OpenRouter (`nvidia/nemotron-3-super-120b-a12b:free`).

To advance from a historical record-keeping app to an active financial copilot, this roadmap delivers three integrated pillars:
1. **Category Budgets & Overspending Alerts**: Monthly spending targets with visual burn rates and AI alert integration.
2. **Visual Analytics Dashboard**: Interactive Chart.js data visualizations for cashflow, category share, and spending velocity.
3. **AI Receipt & Invoice Scanner**: Multimodal document understanding to auto-fill transactions from receipt photos and PDFs.

### Critical Invariants & Rules (from `PROJECT_CONTEXT.md`)
- **Base Amount Rule**: Every monetary aggregate MUST sum from `base_amount` and explicitly filter `base_currency = config('finance.base_currency')`.
- **Conversion Gateway**: All transaction writes MUST pass through `ConvertTransactionAmount`.
- **Period Boundaries**: Date filtering bounds to `startOfDay()`, `endOfDay()`, and `endOfMonth()` (never `now()`).
- **AI Math Prohibition**: AI never performs arithmetic. All budget percentages, limits, and totals are computed in SQL / services before passing to the model.
- **Mass Assignment**: `user_id` is guarded; always write through `$user->budgets()` and `$user->transactions()`.

---

## 2. Tech Stack & Dependencies

| Layer | Technology | Rationale |
|-------|------------|-----------|
| **Backend Framework** | Laravel 13 / PHP 8.3 | Core application framework. |
| **Database** | PostgreSQL (dev) / SQLite (tests) | Complies with existing multi-environment database architecture. |
| **Frontend** | Blade + Tailwind CSS v4 + Alpine.js | Native stack, fast render, zero bloated SPA complexity. |
| **Visualizations** | Chart.js via Vite | Lightweight, responsive canvas charts encapsulated in Alpine directives. |
| **AI Layer** | Prism (`prism-php/prism`) via OpenRouter | Structured schema enforcement and multimodal vision capabilities. |
| **Testing** | PHPUnit & Pest | High regression coverage with in-memory SQLite isolation. |

---

## 3. Planned File Structure Changes

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── BudgetController.php               # [NEW] CRUD & monthly budget management
│   │   ├── AnalyticsController.php            # [NEW] Serves chart data & analytics view
│   │   └── ReceiptScanController.php          # [NEW] Uploads & parses receipts
│   └── Requests/
│       ├── StoreBudgetRequest.php             # [NEW] Validates budget caps
│       └── UpdateBudgetRequest.php            # [NEW] Validates budget edits
├── Models/
│   └── Budget.php                             # [NEW] Budget entity (category + period_month)
├── Services/
│   ├── SpendingStatsService.php               # [MODIFIED] Adds budget calculation methods
│   ├── SpendingInsightService.php             # [MODIFIED] Includes budget warnings in AI prompt
│   └── ReceiptScanService.php                 # [NEW] Multimodal vision extraction via Prism
database/
├── migrations/
│   └── YYYY_MM_DD_HHMMSS_create_budgets_table.php # [NEW] Budgets schema with unique constraint
resources/
├── js/
│   └── components/
│       └── charts.js                          # [NEW] Alpine.js Chart.js integration
├── views/
│   ├── budgets/
│   │   ├── index.blade.php                    # [NEW] Budget overview & progress bars
│   │   └── partials/form.blade.php            # [NEW] Create/edit budget modal form
│   ├── analytics/
│   │   └── index.blade.php                    # [NEW] Dashboard with interactive charts
│   └── transactions/
│       └── partials/receipt-modal.blade.php   # [NEW] Receipt upload & preview modal
tests/Feature/
├── BudgetTest.php                             # [NEW] Feature tests for budget enforcement
├── AnalyticsTest.php                          # [NEW] Feature tests for analytics queries
└── ReceiptScanTest.php                        # [NEW] Feature tests for receipt parsing
```

---

## 4. Phase Breakdown & Execution Tasks

### 📍 PHASE 1: Category Budgets & Overspending Alerts

#### Task 1.1: Database Schema & Budget Model
- **Agent**: `database-architect` / `backend-specialist`
- **Skills**: `database-design`, `eloquent-expert`, `clean-code`
- **Priority**: P0
- **Dependencies**: None
- **INPUT**: User ID, category string, monthly spending cap (decimal 12,2), target month (`YYYY-MM`).
- **OUTPUT**:
  - Migration creating `budgets` table with composite unique index `(user_id, category, period_month)`.
  - Eloquent model `App\Models\Budget` with fillable attributes, casts, and `User` relationship.
- **VERIFY**:
  - Run `php artisan migrate`.
  - Validate in Tinker that creating duplicate budgets for the same user/category/month throws a unique constraint violation.

#### Task 1.2: Budget Computation in `SpendingStatsService`
- **Agent**: `backend-specialist`
- **Skills**: `laravel-best-practices`, `clean-code`
- **Priority**: P1
- **Dependencies**: Task 1.1
- **INPUT**: Authenticated user, target month (`CarbonImmutable`).
- **OUTPUT**:
  - New method `budgetOverview(int $userId, CarbonImmutable $through): array` in `SpendingStatsService`.
  - Aggregates actual expenses per category (`SUM(base_amount)`) against `budgets.amount`.
  - Computes `spent`, `remaining`, `percentage_used`, and `status` (`safe`, `warning` >= 75%, `exceeded` >= 100%).
- **VERIFY**:
  - Unit/Feature test verifying that transactions in foreign currencies (e.g., USD converted to CNY) match the budget accurately via `base_amount`.

#### Task 1.3: Feed Budget Health into AI Insights
- **Agent**: `backend-specialist`
- **Skills**: `prism-php`, `error-handling-mastery`
- **Priority**: P1
- **Dependencies**: Task 1.2
- **INPUT**: Precomputed budget overview array.
- **OUTPUT**:
  - Update `SpendingInsightService` schema to accept optional `budget_alerts` array.
  - Update prompt instruction: describe over-budget and near-limit categories without inventing numbers.
- **VERIFY**:
  - Run `tests/Feature/SpendingInsightTest.php` with mocked Prism responses verifying correct payload structure.

#### Task 1.4: Budget Management UI & Controller
- **Agent**: `frontend-specialist`
- **Skills**: `blade-mastery`, `tailwind-patterns`, `frontend-design`
- **Priority**: P2
- **Dependencies**: Task 1.2
- **INPUT**: Routes `/budgets`, form requests for setting monthly category limits.
- **OUTPUT**:
  - `BudgetController` (index, store, update, destroy).
  - Clean Blade view (`resources/views/budgets/index.blade.php`) with visual burn-rate progress bars.
  - Quick-summary widget on the main dashboard showing remaining daily budget.
- **VERIFY**:
  - Feature test `tests/Feature/BudgetTest.php` (CRUD actions, authorization checks).
  - UI visual check: progress bar colors render properly (Emerald < 75%, Amber 75-99%, Rose ≥ 100%).

---

### 📍 PHASE 2: Visual Analytics Dashboard

#### Task 2.1: Chart.js Integration via Vite & Alpine.js
- **Agent**: `frontend-specialist`
- **Skills**: `modern-web-guidance`, `tailwind-patterns`
- **Priority**: P1
- **Dependencies**: None
- **INPUT**: `npm install chart.js`.
- **OUTPUT**:
  - Chart.js registered in `resources/js/app.js` with an Alpine directive `x-chart`.
  - Pre-configured color tokens matching the app's clean financial theme (no harsh primary colors).
  - Run `npm run build`.
- **VERIFY**:
  - Verify `public/build/manifest.json` and asset compilation without warnings.

#### Task 2.2: Analytics Controller & Time-Series Data Aggregation
- **Agent**: `backend-specialist`
- **Skills**: `database-design`, `eloquent-expert`
- **Priority**: P1
- **Dependencies**: Task 2.1
- **INPUT**: User ID and date range filter (default: current calendar month + 6-month historical baseline).
- **OUTPUT**:
  - `AnalyticsController::index` delivering structured chart datasets:
    1. **Category Donut**: Top categories by share of spend.
    2. **Cashflow Bar Chart**: 6-month historical Income vs. Expenses.
    3. **Daily Burn Velocity**: Cumulative spend day-by-day compared to straight-line budget pace.
- **VERIFY**:
  - Query benchmarks verifying `whereDate` index usage; no N+1 queries.

#### Task 2.3: Interactive Analytics View
- **Agent**: `frontend-specialist`
- **Skills**: `blade-mastery`, `frontend-design`
- **Priority**: P2
- **Dependencies**: Task 2.1, Task 2.2
- **INPUT**: Clean Blade view at `resources/views/analytics/index.blade.php`.
- **OUTPUT**:
  - Navigation tab added to `navigation.blade.php`.
  - Responsive 3-card chart grid with month switcher.
  - Hover tooltips formatting currency with `number_format($val, 2)` and muted currency badge.
- **VERIFY**:
  - Test responsiveness on mobile viewports; verify canvas re-render on month selection.

---

### 📍 PHASE 3: Multimodal AI Receipt & Invoice Scanner

#### Task 3.1: Receipt Extraction Service via Multimodal LLM
- **Agent**: `backend-specialist`
- **Skills**: `prism-php`, `secure-coding-patterns`
- **Priority**: P1
- **Dependencies**: None
- **INPUT**: Uploaded image file (JPEG/PNG/WebP) or PDF receipt.
- **OUTPUT**:
  - `ReceiptScanService` executing a structured multimodal Prism prompt.
  - Extraction schema:
    - `merchant` (string)
    - `amount` (float)
    - `currency` (string, ISO 3-letter)
    - `occurred_at` (date YYYY-MM-DD)
    - `suggested_category` (string, matched against `$categories->forUser()`)
- **VERIFY**:
  - Unit test mocking multimodal LLM response with sample receipt data.

#### Task 3.2: Receipt Scanner Upload Modal & Form Autofill
- **Agent**: `frontend-specialist`
- **Skills**: `blade-mastery`, `livewire-expert` / Alpine.js
- **Priority**: P2
- **Dependencies**: Task 3.1
- **INPUT**: Drag-and-drop file upload target in `transactions/create.blade.php`.
- **OUTPUT**:
  - `ReceiptScanController::scan` endpoint validating image MIME types and file size (< 5MB).
  - Alpine-powered upload modal: preview image, show extraction status spinner, and autofill fields into the transaction form.
  - Once approved by the user, submitting writes via `ConvertTransactionAmount` into `transactions`.
- **VERIFY**:
  - End-to-end feature test verifying that scanning a USD receipt autofills the transaction, passes through `ConvertTransactionAmount`, and stores frozen `base_amount` in CNY.

---

## 5. Phase X: Final Verification & Quality Gate

Before marking each phase as complete, the following mandatory checklist must pass:

- [ ] **Code Formatting**: `vendor/bin/pint --dirty --format agent` passes cleanly.
- [ ] **Test Suite**: `php artisan test --compact` executes with 100% green tests.
- [ ] **Asset Compilation**: `npm run build` generates modern bundles with no missing Tailwind classes.
- [ ] **Localization Integrity**: All new user-facing strings are present in both English views and `lang/id.json`.
- [ ] **Security & Validation**: All file uploads validated (MIME & size); authorization gates (`authorizeOwnership`) enforced on every endpoint.
- [ ] **Invariants Check**: Zero occurrences of raw `amount` summation; all queries filter `base_currency = config('finance.base_currency')`.

---

## 6. Execution Command

When ready to begin implementation, launch with:
```bash
/create docs/PLAN-finance-roadmap.md
```
Or start Phase 1 directly.

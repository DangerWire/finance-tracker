# Project Progress & Roadmap

> **Last Updated**: 2026-10-18  
> **Application**: Personal Finance Tracker  
> **Stack**: Laravel 13, PHP 8.3, Tailwind CSS, Alpine.js, Google Gemini via Prism PHP  
> **Databases**: PostgreSQL (Development), SQLite (Testing)

---

## 📌 Critical Architectural Invariants (Must Follow)
1. **Monetary Aggregation**: Every total or summary figure visible to users is summed from `base_amount` (never `amount`) and filtered by `base_currency = config('finance.base_currency')`.
2. **Transaction Creation**: Never write transactions directly with raw amounts; always route through `App\Actions\ConvertTransactionAmount` to freeze exchange rates.
3. **Period Boundaries**: Date filtering and monthly boundaries bound to `startOfDay()` / `endOfMonth()`, never `now()`. Use `whereDate()` for database date comparisons.
4. **Ownership & Relations**: Always create transactions through the user relation (`$user->transactions()->create([...])`) because `user_id` is guarded against mass assignment.
5. **Strict Bi-lingual Localization**: Every single-quoted string in `__('...')` must have a matching translation in `lang/id.json` (strictly verified by `LocalizationTest`).
6. **Code Style**: Run `vendor/bin/pint --dirty --format agent` on modified PHP files before completing any task.

---

## ✅ Completed Features & Milestones

### 1. Multi-Currency Engine & Exchange Rates
- Support for transactions in arbitrary currencies converted into base currency (`CNY` by default).
- Integration with Frankfurter API provider with frozen historical rates (`applied_rate`).
- Backfill command: `php artisan finance:backfill-base-amounts`.

### 2. Split Transactions
- Allows a single transaction to be split across multiple sub-categories with amount allocation.
- UI modal for dynamically adding/removing split lines with live validation ensuring sub-amounts sum to the total.
- Database relationship `parent_id` with integrity constraints.

### 3. Monthly Budgets & Spending Tracking
- Per-category monthly budget targets with real-time spend progress indicators.
- Proactive budget warning states (normal, warning at 80%, exceeded at 100%+).
- Visual progress bars rendered on dashboard and budget management views.

### 4. Transactions Filter Modal
- Converted transaction filters (type, category, date range) from an inline accordion into an Alpine.js modal popup.
- Clean backdrop, badge indicators for active filters, and quick reset controls.

### 5. Custom GitHub-Style 404 Error Page
- Responsive error view (`resources/views/errors/404.blade.php`) styled with cosmic SVG illustration and starfield.
- Context-aware action buttons (Dashboard for authenticated users, Home / Login for guests).
- Contact note directing users to "Dave or Malvine".

### 6. Recurring Transactions & Scheduled Runner
- Recurring transaction templates (daily, weekly, monthly, yearly) with schedule cursor tracking.
- Console command `finance:generate-recurring` scheduled daily at 04:00.
- "Run now" instant execution button on `/recurring` with explicit `type="submit"`.

### 7. AI Spending Insights (Google Gemini Integration)
- Migrated from OpenRouter to Google Gemini API (`GEMINI_API_KEY`, `gemini-2.5-flash`) via Prism PHP.
- Structured AI generation with strict JSON schema (headline + up to 4 categorized observations).
- Zero-hallucination guarantee: model receives precomputed SQL statistics and is strictly forbidden from performing arithmetic.

### 9. Cyberpunk Scroll-Driven Landing Experience (Endfield Style)
- Replaced starter welcome page with a pinned full-viewport interactive storytelling experience (`resources/views/welcome.blade.php`).
- Powered by GreenSock (GSAP), `ScrollTrigger`, and `Lenis` smooth momentum scrolling (`resources/js/landing-scroll.js`).
- 5 pinned telemetry stages: Protocol Overview, Recurring Automation Engine, Gemini Neural Analysis, Budget Safeguards, and Session Access Pods.
- Cyberpunk tactical aesthetic with high-voltage hazard amber, cyber cyan phosphors, beveled chamfers, and HUD telemetry (100% Purple-free).

### 10. Verification & Quality Assurance
- 194 unit and feature tests passing with 100% pass rate.
- Laravel Pint formatting clean across all PHP files.
- Production assets built with Vite (`app.js`, `app.css`).

### 11. Modern Fintech UI Redesign (Sure.am / Maybe Finance Inspiration)
- Replaced the previous cyberpunk theme with a sleek, minimalist, developer-centric fintech aesthetic inspired by `app.sure.am` (Maybe Finance fork / basement.studio craft).
- **Global Palette & Tokens**: Deep obsidian canvas (`#09090b`), hairline borders (`border-white/[0.07]`), elevated card surfaces (`#111218`), and subtle emerald (`#10b981`) and rose (`#f43f5e`) financial delta indicators.
- **Transactions Tab (`/transactions`)**: High-density ledger with top aggregate metric cards (Income, Expenses, Net Balance), segmented active filters, date-grouped rows, and monospaced tabular figures (`font-mono-num`).
- **Insights Tab (`/insights`)**: Clean analytics suite featuring a Google Gemini 2.5 Flash intelligence hub, spending KPI cards, and category allocation progress bars.
- **Landing Page (`/`)**: High-craft SaaS presentation with an interactive live dashboard mockup, net worth SVG area curve, and a 4-pillar bento feature grid.
- **Core Components**: Redesigned buttons, inputs, dropdowns, navigation pills, and application mark.

### 12. User Main Currency & Locked Transaction Currency (Completed 2026-11-14)
- Added user-selectable primary currency in profile settings (`/profile`), stored on `users.currency` with dynamic fallback to `config('finance.base_currency')`.
- Supported currencies list in `config/finance.php`: IDR, USD, EUR, CNY, JPY, GBP, SGD, AUD, MYR.
- Locked currency field in Income & Expense forms (`resources/views/transactions/partials/form.blade.php`) with lock indicator and direct link to Settings.
- Optional automatic recalculation of past transaction base amounts and conversion rates upon changing the primary currency.
- Redesigned Profile & Settings views in the Sure.am dark aesthetic.
- Full test coverage in `tests/Feature/CurrencySettingsTest.php` (199 total tests passing).

### 13. Friends & Peer-to-Peer Debt Settlement Engine (Completed 2026-11-15 via Swarm)
- **Friend Connection Network**: Sending, accepting, declining, and removing friend connections via `/friends`.
- **Two-Party Loan Agreement Protocol**: Creation of loans (lent/borrowed) starts in `pending_acceptance`; counterparty must accept or decline before the loan becomes `active`.
- **Mutual Repayment Confirmation**:
  - Debtor (borrower) marks payment submitted (`payment_submitted`).
  - Creditor (lender) can dispute (`active`) or confirm receipt (`settled`).
- **Atomic Dual-Ledger Sync**: `App\Actions\SettleDebtAction` runs inside a DB transaction with row locking (`lockForUpdate`), posting `+` Income (`Debt Repayment`) for the lender and `-` Expense (`Debt Settlement`) for the borrower through `ConvertTransactionAmount`.
- **Tactical UI Hubs**:
  - `/friends`: Search/add by email, pending requests, active friends list with action menus.
  - `/debts`: KPI metrics (Total Lent, Total Borrowed, Net Position), pending approvals, active loans, awaiting confirmation alerts, settled history, and 'Record Loan' modal.
- **Verification & Testing**:
  - 28 new feature tests across `FriendshipTest`, `DebtAgreementTest`, and `DebtSettlementTest`.
  - All 227 tests in the suite pass (660 assertions).

---

## 🚀 In Progress / Next Up

### Feature: Monthly Balance Rollover
* **Objective**: Add a recurring monthly allowance system with automatic rollover of unused balances into the next billing cycle.
* **Requirements**:
  1. **Database Schema**: Fields/tables needed to track `base_balance`, `used_amount`, `extra_balance`, and `billing_cycle_date`.
  2. **Rollover Logic**: Calculate unused balance at cycle end (`unused = remaining base_balance + remaining extra_balance`), assign to next cycle's `extra_balance`, and reset `base_balance`.
  3. **Deduction Priority**: Safely deduct spend with clear priority (e.g., base balance first, then extra balance) preventing balances from going below 0.
  4. **Frontend UI Component**: Dashboard balance breakdown card (Monthly Base Balance, Extra Balance badge, Total Available Balance).

### Feature: Savings Plans (Goal-Based Saving Buckets)
* **Objective**: Allow users to set target-based savings goals (e.g., save `100 CNY` to buy shoes), deposit/withdraw increments over time, track saving pace toward optional deadlines, and optionally log the final purchase as a ledger expense when completed.
* **Requirements**:
  1. **Database Schema**: `savings_plans` (name, target_amount, currency, target_date, category, status, accent, note, purchase_transaction_id, completed_at) and `savings_contributions` (deposit/withdrawal history summed in SQL; `user_id` guarded).
  2. **Virtual Earmarking & Checkout**: Contributions earmark money without creating fake expenses (showing Total Balance vs. Available Unallocated Balance); completing a funded goal offers **Mark Completed Only** or **Complete & Log Purchase Expense** routed atomically through `ConvertTransactionAmount`.
  3. **Pace & Progress Engine**: `SavingsPlanService` computes saved/remaining amounts, percentage, and recommended daily/weekly/monthly saving pace when a target date is set.
  4. **Frontend UI & Dashboard**: `/savings` hub (KPI strip, active/completed tabs, goal cards with quick-deposit presets, contribution history detail view) in dark obsidian theme (zero purple), plus a Dashboard Savings Plans widget and `lang/id.json` parity.
  5. **Telegram Bot**: `/savings` overview and `/save <amount> <plan>` quick deposit with 100% funded milestone alert.

---

## 🛠️ Handy Commands
```bash
# Run tests
php artisan test --compact
php artisan test --filter=RecurringTransactionTest

# Format code
vendor/bin/pint --dirty --format agent

# Check project health & session context
php .agent/scripts/session_manager.php status
```

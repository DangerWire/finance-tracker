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

---

## 🚀 In Progress / Next Up

### Feature: Friends & Peer-to-Peer Debt Settlement (Ready for Implementation)
* **Objective**: Connect friends and track borrowed/lent funds with a 2-party confirmation protocol that automatically generates income (`+`) for the lender and expense (`-`) for the borrower upon verified settlement.
* **Architecture**: Detailed plan artifact in [friend-lending-plan.md](file:///C:/Users/HYPE%20AMD/.gemini/antigravity/brain/41aa6424-0b8e-4fb6-8065-549a6d5965b6/friend-lending-plan.md).
* **Confirmed Decisions**:
  1. **Two-Party Agreement**: Loan creation requires acceptance by the second friend before becoming `active`.
  2. **Repayment-Triggered Ledger**: No transactions are posted at loan creation; transactions are posted strictly upon repayment confirmation.
  3. **Full Settlement Only**: Entire debt is settled in one event.
  4. **Atomic Dual-Ledger Sync**: When verified by the lender, post `+` income to lender and `-` expense to borrower via `ConvertTransactionAmount`.

### Feature: Monthly Balance Rollover
* **Objective**: Add a recurring monthly allowance system with automatic rollover of unused balances into the next billing cycle.
* **Requirements**:
  1. **Database Schema**: Fields/tables needed to track `base_balance`, `used_amount`, `extra_balance`, and `billing_cycle_date`.
  2. **Rollover Logic**: Calculate unused balance at cycle end (`unused = remaining base_balance + remaining extra_balance`), assign to next cycle's `extra_balance`, and reset `base_balance`.
  3. **Deduction Priority**: Safely deduct spend with clear priority (e.g., base balance first, then extra balance) preventing balances from going below 0.
  4. **Frontend UI Component**: Dashboard balance breakdown card (Monthly Base Balance, Extra Balance badge, Total Available Balance).

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

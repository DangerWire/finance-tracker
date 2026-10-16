<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The same values as transactions.type, so a template can produce
            // either an income or an expense without inventing new vocabulary.
            $table->string('type', 10)->default('expense');
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CNY');
            $table->string('category')->nullable();
            $table->text('note')->nullable();
            $table->string('frequency', 10);

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            // The cursor. Occurrences are worked out from this rather than by
            // walking forwards from starts_on, so a template created years ago
            // does not pay for every step it skipped.
            $table->date('next_occurrence_on')->index();

            // The occurrence most recently turned into a transaction. Together
            // with the unique index on transactions this is what makes a
            // repeated run of the scheduled command harmless.
            $table->date('last_occurrence_on')->nullable();

            // Whether a due occurrence becomes a transaction automatically, or
            // is only reported. Reminder-only templates are how a bill can be
            // tracked without silently posting something nobody paid.
            $table->boolean('auto_post')->default(true);

            // How many days ahead of an occurrence to notify. Null means the
            // template never sends a reminder.
            $table->unsignedSmallInteger('remind_days_before')->nullable();

            // The occurrence a reminder was last sent for, so a bill due in a
            // week does not email once a day for seven days.
            $table->date('last_reminded_occurrence_on')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['is_active', 'next_occurrence_on']);
        });

        $this->enforceValidValues();
    }

    /**
     * Constrain the columns at the database level so they cannot drift outside
     * their permitted values. Only PostgreSQL gets native enum types; SQLite has
     * no equivalent and relies on application-level validation.
     */
    private function enforceValidValues(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'transaction_type') THEN
                    CREATE TYPE transaction_type AS ENUM ('income', 'expense');
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'recurring_frequency') THEN
                    CREATE TYPE recurring_frequency AS ENUM (
                        'daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'
                    );
                END IF;
            END
            $$;
        SQL);

        // Postgres cannot cast a varchar default to an enum automatically, so
        // the default has to come off before the type changes and go back on
        // afterwards. This is the same dance the transactions.type migration
        // performs.
        DB::unprepared('ALTER TABLE recurring_transactions ALTER COLUMN type DROP DEFAULT');
        DB::unprepared('ALTER TABLE recurring_transactions ALTER COLUMN frequency DROP DEFAULT');

        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_transactions
                ALTER COLUMN type TYPE transaction_type USING type::transaction_type;

            ALTER TABLE recurring_transactions
                ALTER COLUMN frequency TYPE recurring_frequency USING frequency::recurring_frequency;
        SQL);

        DB::unprepared("ALTER TABLE recurring_transactions ALTER COLUMN type SET DEFAULT 'expense'::transaction_type");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // The columns have to go back to varchar before the types can be
            // dropped; Postgres cannot drop a type that is still in use.
            DB::unprepared('ALTER TABLE recurring_transactions ALTER COLUMN type DROP DEFAULT');

            DB::unprepared(<<<'SQL'
                ALTER TABLE recurring_transactions
                    ALTER COLUMN type TYPE varchar(10) USING type::text;

                ALTER TABLE recurring_transactions
                    ALTER COLUMN frequency TYPE varchar(10) USING frequency::text;

                DROP TYPE IF EXISTS recurring_frequency;
            SQL);
        }

        Schema::dropIfExists('recurring_transactions');
    }
};

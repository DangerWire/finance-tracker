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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 10)->default('expense')->after('amount');
        });

        // Backfill any rows that predate the column.
        DB::table('transactions')->whereNull('type')->update(['type' => 'expense']);

        $this->enforceValidTypes();
    }

    /**
     * Constrain the column at the database level so it cannot drift outside
     * the enum. Only PostgreSQL gets a native enum type; SQLite has no
     * equivalent and relies on application-level validation.
     */
    private function enforceValidTypes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // The column default must be dropped before the type change; Postgres
        // cannot cast a varchar default to the new enum automatically.
        DB::unprepared('ALTER TABLE transactions ALTER COLUMN type DROP DEFAULT');

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'transaction_type') THEN
                    CREATE TYPE transaction_type AS ENUM ('income', 'expense');
                END IF;
            END
            $$;

            ALTER TABLE transactions
                ALTER COLUMN type TYPE transaction_type USING type::transaction_type;

            ALTER TABLE transactions
                ALTER COLUMN type SET DEFAULT 'expense'::transaction_type;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('ALTER TABLE transactions ALTER COLUMN type DROP DEFAULT');

            DB::unprepared(<<<'SQL'
                ALTER TABLE transactions ALTER COLUMN type TYPE varchar(10) USING type::text;
                DROP TYPE IF EXISTS transaction_type;
            SQL);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

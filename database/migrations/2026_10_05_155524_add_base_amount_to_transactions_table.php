<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // The converted snapshot, frozen at entry time so historical totals
            // never shift when rates move. The original amount stays untouched.
            $table->decimal('base_amount', 14, 4)->nullable()->after('amount');
            $table->char('base_currency', 3)->nullable()->after('base_amount');
            // Rate applied, kept so the conversion can be audited later.
            $table->decimal('applied_rate', 18, 8)->nullable()->after('base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'base_currency', 'applied_rate']);
        });
    }
};

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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            // Rates need far more precision than money: a rate stored at the
            // same scale as an amount collapses 7.2431 to 7.24, and the error
            // compounds across every transaction using that rate.
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_pair_date_unique');
            $table->index(['quote_currency', 'rate_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

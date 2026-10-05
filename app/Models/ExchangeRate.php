<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['base_currency', 'quote_currency', 'rate', 'rate_date'])]
class ExchangeRate extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'rate_date' => 'date',
        ];
    }
}

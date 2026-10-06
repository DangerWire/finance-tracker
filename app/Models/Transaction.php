<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amount', 'type', 'currency', 'occurred_at', 'category', 'note', 'base_amount', 'base_currency', 'applied_rate', 'import_id'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'type' => TransactionType::class,
        ];
    }

    /**
     * Get the amount signed by the transaction type.
     */
    public function signedAmount(): float
    {
        return $this->amount * $this->type->sign();
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the import that produced this transaction, when it came from one.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TransactionImport::class, 'import_id');
    }
}

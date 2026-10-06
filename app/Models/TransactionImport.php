<?php

namespace App\Models;

use Database\Factories\TransactionImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One upload's worth of transactions.
 *
 * Grouping rows by their import is what makes a repeated import recoverable:
 * the whole batch can be identified and removed instead of the user hunting
 * for duplicates row by row.
 */
#[Fillable(['user_id', 'filename', 'imported_count'])]
class TransactionImport extends Model
{
    /** @use HasFactory<TransactionImportFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_count' => 'integer',
        ];
    }

    /**
     * Get the user that owns this import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions this import produced.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'import_id');
    }
}

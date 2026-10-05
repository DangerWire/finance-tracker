<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Get the human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }

    /**
     * Get the sign this type contributes to a running balance.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Income => 1,
            self::Expense => -1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'label', 'value');
    }
}

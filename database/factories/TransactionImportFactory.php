<?php

namespace Database\Factories;

use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionImport>
 */
class TransactionImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => 'statement-'.$this->faker->unique()->lexify('??????').'.csv',
            'imported_count' => 0,
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;
use Throwable;

/**
 * Turns precomputed spending statistics into written insights.
 *
 * The model is given finished numbers and instructed to describe them without
 * performing any arithmetic of its own, so a wrong figure cannot appear in the
 * output unless it was already wrong in the database.
 */
class SpendingInsightService
{
    /**
     * Build the schema the model must return. Constraining the shape means the
     * view renders consistent cards rather than parsing prose.
     */
    private function schema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'spending_insights',
            description: 'A written summary of one month of personal spending.',
            properties: [
                'headline' => new StringSchema(
                    name: 'headline',
                    description: 'One sentence summarising the month. No arithmetic.',
                ),
                'insights' => new ArraySchema(
                    name: 'insights',
                    description: 'Between zero and four observations.',
                    items: new ObjectSchema(
                        name: 'insight',
                        description: 'A single observation about the data.',
                        properties: [
                            'title' => new StringSchema(
                                name: 'title',
                                description: 'Short label for the observation.',
                            ),
                            'detail' => new StringSchema(
                                name: 'detail',
                                description: 'One or two sentences quoting only the supplied figures.',
                            ),
                            'severity' => new EnumSchema(
                                name: 'severity',
                                description: 'Whether this needs the user\'s attention.',
                                options: ['info', 'attention'],
                            ),
                            'category' => new StringSchema(
                                name: 'category',
                                description: 'The category this concerns, or an empty string.',
                            ),
                        ],
                        requiredFields: ['title', 'detail', 'severity', 'category'],
                    ),
                ),
            ],
            requiredFields: ['headline', 'insights'],
        );
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{headline: string, insights: array<int, array<string, string>>}
     */
    public function analyze(array $stats): array
    {
        $response = Prism::structured()
            ->using(Provider::OpenRouter, config('insights.model'))
            ->withSchema($this->schema())
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->buildPrompt($stats))
            ->asStructured();

        // The structured payload sits under the 'structured' key; toArray()
        // returns the whole envelope including usage and metadata.
        $decoded = $response->structured ?? [];

        return [
            'headline' => (string) ($decoded['headline'] ?? ''),
            'insights' => array_values(array_map(fn (array $insight): array => [
                'title' => (string) ($insight['title'] ?? ''),
                'detail' => (string) ($insight['detail'] ?? ''),
                'severity' => ($insight['severity'] ?? 'info') === 'attention' ? 'attention' : 'info',
                'category' => (string) ($insight['category'] ?? ''),
            ], $decoded['insights'] ?? [])),
        ];
    }

    /**
     * Whether an AI provider has been configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('insights.api_key'));
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You are a careful personal finance assistant reviewing one person\'s spending.',
            '',
            'CRITICAL RULES:',
            '- Every figure you are given has already been calculated by the database. Quote figures exactly as given.',
            '- NEVER perform arithmetic, percentages, sums or differences yourself. If a comparison is not supplied, do not invent one.',
            '- Do not state a percentage change unless a change_percent field is present and not null.',
            '- If current_period.is_partial is true, the month is incomplete. Say so rather than implying a trend.',
            '- Do not invent categories, dates, merchants or amounts that are not in the data.',
            '- Never mention JSON field names or raw values such as is_unusual, change_percent or share_of_spend_percent. Describe them in plain language instead.',
            '- If current_period.expenses is null, the figures are in the currencies they were recorded in and must NOT be added together. Report each currency separately.',
            '- If the data is too thin to support an insight, return fewer insights. An empty list is better than a guess.',
            '- Be concise and concrete. No generic advice about budgeting.',
            '',
            'Write every insight in the following language: '.$this->outputLanguageInstruction().'.',
        ]);
    }

    /**
     * The language the generated text must be written in.
     */
    private function outputLanguageInstruction(): string
    {
        return match (app()->getLocale()) {
            'id' => 'Indonesian (Bahasa Indonesia)',
            default => 'English',
        };
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function buildPrompt(array $stats): string
    {
        return implode("\n", [
            'Here are this user\'s precomputed spending statistics for ',
            $stats['current_period']['label'],
            '. All amounts are in '.strtoupper((string) $stats['base_currency']).'.',
            '',
            '```json',
            (string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            '```',
            '',
            'Write a short headline and up to four insights based only on these figures.',
        ]);
    }

    /**
     * Convert a provider failure into a message safe to show a user.
     */
    public static function describeFailure(Throwable $exception): string
    {
        Log::error('Spending insight generation failed.', ['error' => $exception->getMessage()]);

        return $exception instanceof RuntimeException && $exception->getMessage() !== ''
            ? $exception->getMessage()
            : 'The AI provider could not be reached. Check OPENROUTER_API_KEY and try again.';
    }
}

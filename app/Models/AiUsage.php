<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * One row per AI API call, with its computed USD cost — the source of
 * truth for the dashboard's spend tracker.
 */
class AiUsage extends Model
{
    protected $fillable = [
        'provider', 'model', 'type',
        'input_tokens', 'output_tokens', 'cost', 'source_id',
    ];

    protected $casts = [
        'input_tokens'  => 'integer',
        'output_tokens' => 'integer',
        'cost'          => 'float',
    ];

    /**
     * Record a call. Never throws — tracking must not break a scrape.
     */
    public static function record(
        string $provider,
        string $model,
        string $type,
        int $inputTokens,
        int $outputTokens,
        ?int $sourceId = null,
    ): void {
        try {
            static::create([
                'provider'      => $provider,
                'model'         => $model,
                'type'          => $type,
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost'          => static::costFor($model, $inputTokens, $outputTokens),
                'source_id'     => $sourceId,
            ]);
        } catch (\Throwable $e) {
            Log::info('AI usage not recorded: ' . $e->getMessage());
        }
    }

    /**
     * USD cost of a call, from the rates in config/ai.php.
     */
    public static function costFor(string $model, int $inputTokens, int $outputTokens): float
    {
        $rate = config("ai.rates.{$model}");

        if (! is_array($rate)) {
            return 0.0;
        }

        return ($inputTokens * $rate['input'] + $outputTokens * $rate['output']) / 1_000_000;
    }
}

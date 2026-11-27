<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user versioned cache for read-heavy financial views and aggregates.
 *
 * Uses a monotonically bumped version counter per user rather than cache tags
 * so O(1) invalidation works identically on file, database, array, and Redis stores.
 */
class UserDataCache
{
    public const DEFAULT_TTL_SECONDS = 300;

    /**
     * Retrieve a cached value for a user segment, or compute and store it.
     *
     * Cached payloads must contain only plain PHP arrays and scalars so they
     * unserialize safely under Laravel's `cache.serializable_classes = false`.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(int $userId, string $segment, Closure $callback, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): mixed
    {
        $key = $this->key($userId, $segment);

        return Cache::remember($key, $ttlSeconds, $callback);
    }

    /**
     * Invalidate all cached segments for a user in O(1) by advancing their version.
     */
    public function bump(?int $userId): void
    {
        if ($userId === null || $userId <= 0) {
            return;
        }

        $versionKey = $this->versionKey($userId);
        $current = (int) Cache::get($versionKey, 1);

        Cache::forever($versionKey, $current + 1);
    }

    /**
     * Get the current cache version for a user.
     */
    public function version(int $userId): int
    {
        return (int) Cache::get($this->versionKey($userId), 1);
    }

    private function key(int $userId, string $segment): string
    {
        return sprintf('finance:v%d:u%d:%s', $this->version($userId), $userId, $segment);
    }

    private function versionKey(int $userId): string
    {
        return sprintf('finance:user_version:%d', $userId);
    }
}

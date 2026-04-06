<?php

namespace App\Services;

use App\Core\DB;

/**
 * Rate Limiter Service
 * Provides rate limiting functionality using MySQL storage
 */
class RateLimiter
{
    /**
     * Check if the request is allowed based on rate limit
     *
     * @param string $key Unique key for the rate limit (e.g., "login:192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public function check(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $db = DB::getInstance();
        $keyHash = md5($key);

        // Get current rate limit record
        $record = $db->first(
            "SELECT * FROM rate_limits WHERE key_hash = ?",
            [$keyHash]
        );

        $now = date('Y-m-d H:i:s');

        if (!$record) {
            // No record exists - create new one
            $windowStart = $now;
            $db->query(
                "INSERT INTO rate_limits (key_hash, attempts, window_start) 
                 VALUES (?, 1, ?)",
                [$keyHash, $windowStart]
            );
            return true;
        }

        // Check if window has expired
        $windowStart = strtotime($record['window_start']);
        $windowEnd = $windowStart + $windowSeconds;
        $currentTime = time();

        if ($currentTime > $windowEnd) {
            // Window expired - reset
            $db->query(
                "UPDATE rate_limits 
                 SET attempts = 1, window_start = ? 
                 WHERE key_hash = ?",
                [$now, $keyHash]
            );
            return true;
        }

        // Check if rate limit exceeded
        if ($record['attempts'] >= $maxAttempts) {
            return false;
        }

        // Increment attempts
        $db->query(
            "UPDATE rate_limits 
             SET attempts = attempts + 1 
             WHERE key_hash = ?",
            [$keyHash]
        );

        return true;
    }

    /**
     * Get remaining attempts and reset time
     *
     * @param string $key Unique key for the rate limit
     * @param int $windowSeconds Time window in seconds
     * @return array ['remaining' => int, 'reset_at' => string]
     */
    public function getStatus(string $key, int $windowSeconds): array
    {
        $db = DB::getInstance();
        $keyHash = md5($key);

        $record = $db->first(
            "SELECT * FROM rate_limits WHERE key_hash = ?",
            [$keyHash]
        );

        if (!$record) {
            return [
                'remaining' => PHP_INT_MAX,
                'reset_at' => null
            ];
        }

        $windowStart = strtotime($record['window_start']);
        $windowEnd = $windowStart + $windowSeconds;
        $currentTime = time();

        if ($currentTime > $windowEnd) {
            return [
                'remaining' => PHP_INT_MAX,
                'reset_at' => null
            ];
        }

        return [
            'remaining' => max(0, 5 - $record['attempts']), // Assuming max 5 attempts
            'reset_at' => date('Y-m-d H:i:s', $windowEnd)
        ];
    }

    /**
     * Reset rate limit for a key
     *
     * @param string $key Unique key for the rate limit
     * @return void
     */
    public function reset(string $key): void
    {
        $db = DB::getInstance();
        $keyHash = md5($key);

        $db->query("DELETE FROM rate_limits WHERE key_hash = ?", [$keyHash]);
    }

    /**
     * Clean up expired rate limit records (called by cron)
     *
     * @return void
     */
    public function cleanup(): void
    {
        $db = DB::getInstance();
        // Delete records older than 1 hour
        $db->query("DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 HOUR");
    }
}

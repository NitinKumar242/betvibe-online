<?php
/**
 * Cleanup expired sessions and rate limits
 * Run this script daily via cron
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\SessionService;
use App\Services\RateLimiter;

echo "Starting cleanup...\n";

// Clean expired sessions
$sessionService = new SessionService();
$sessionService->cleanExpired();
echo "Cleaned expired sessions.\n";

// Clean expired rate limits
$rateLimiter = new RateLimiter();
$rateLimiter->cleanup();
echo "Cleaned expired rate limits.\n";

echo "Cleanup completed.\n";

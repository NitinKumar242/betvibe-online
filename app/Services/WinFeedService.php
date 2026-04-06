<?php
/**
 * BetVibe - Win Feed Service
 * Broadcasts notable wins to all connected WebSocket clients
 */

namespace App\Services;

class WinFeedService
{
    /**
     * Broadcast a win to the live feed (only wins > NPR 50)
     */
    public function broadcast(array $user, string $game, float $payout, float $multiplier): void
    {
        if ($payout < 50) {
            return;
        }

        $maskedName = strlen($user['username']) > 2
            ? substr($user['username'], 0, 2) . '***'
            : $user['username'] . '***';

        $message = [
            'type' => 'win_feed',
            'username' => $maskedName,
            'game' => $game,
            'payout' => number_format($payout, 0),
            'multiplier' => round($multiplier, 2),
            'timestamp' => time(),
        ];

        try {
            WebSocketService::broadcast($message);
        } catch (\Throwable $e) {
            // WebSocket not critical
        }
    }
}

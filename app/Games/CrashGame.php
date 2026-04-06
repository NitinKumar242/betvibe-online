<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Crash Game
 * WebSocket-driven multiplier game
 * Server generates crash point, players cash out before crash
 * States: waiting → in-progress → crashed
 */
class CrashGame implements GameInterface
{
    /**
     * Play game and return result
     * Note: Crash is primarily WebSocket-driven, this is for instant play mode
     * 
     * @param array $betData Game-specific bet data ['auto_cashout' => float|null]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        $autoCashout = isset($betData['auto_cashout']) ? (float) $betData['auto_cashout'] : null;

        if ($autoCashout !== null && $autoCashout < 1.01) {
            throw new \InvalidArgumentException('auto_cashout must be at least 1.01');
        }

        // Generate crash point
        $crashPoint = RNGService::crashMultiplier(0.04);

        // Determine if player would have won with auto cashout
        $won = false;
        $multiplier = 0;
        $payout = 0;

        if ($autoCashout !== null && $autoCashout < $crashPoint) {
            // Player auto-cashed out successfully
            $won = true;
            $multiplier = $autoCashout;
            $payout = $betAmount * $autoCashout;
        }

        return [
            'result' => $won ? 'win' : 'loss',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'crash_point' => $crashPoint,
                'auto_cashout' => $autoCashout,
                'would_have_won' => $autoCashout !== null && $autoCashout < $crashPoint
            ]
        ];
    }

    /**
     * Generate crash multiplier for a round
     * 
     * @return float Crash multiplier
     */
    public static function generateCrashPoint(): float
    {
        return RNGService::crashMultiplier(0.04);
    }

    /**
     * Calculate payout for a cashout
     * 
     * @param float $betAmount Original bet amount
     * @param float $cashoutMultiplier Multiplier at cashout
     * @return float Payout amount
     */
    public static function calculatePayout(float $betAmount, float $cashoutMultiplier): float
    {
        return $betAmount * $cashoutMultiplier;
    }
}

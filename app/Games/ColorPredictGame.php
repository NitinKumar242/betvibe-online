<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Color Predict Game
 * Timer-based round game (3 minutes)
 * Bet Options: Red (1.95x) | Green (1.95x) | Violet (4.5x)
 */
class ColorPredictGame implements GameInterface
{
    /**
     * Play the game and return the result
     * 
     * @param array $betData Game-specific bet data ['color' => 'red|green|violet']
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['color'])) {
            throw new \InvalidArgumentException('Color is required');
        }

        $playerColor = $betData['color'];

        if (!in_array($playerColor, ['red', 'green', 'violet'])) {
            throw new \InvalidArgumentException('Invalid color. Must be red, green, or violet');
        }

        // Generate result using weighted random
        $weights = ['red' => 45, 'green' => 45, 'violet' => 10];
        $resultColor = RNGService::weightedRandom($weights);

        // Determine multiplier based on result
        $multipliers = [
            'red' => 1.95,
            'green' => 1.95,
            'violet' => 4.5
        ];

        // Check if player won
        $won = ($playerColor === $resultColor);
        $multiplier = $won ? $multipliers[$playerColor] : 0;
        $payout = $won ? $betAmount * $multiplier : 0;

        return [
            'result' => $won ? 'win' : 'loss',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'player_color' => $playerColor,
                'result_color' => $resultColor,
                'multiplier' => $multipliers[$resultColor]
            ]
        ];
    }

    /**
     * Generate a result for a round (used by cron timer)
     * 
     * @return array Result data
     */
    public static function generateResult(): array
    {
        $weights = ['red' => 45, 'green' => 45, 'violet' => 10];
        $resultColor = RNGService::weightedRandom($weights);

        $multipliers = [
            'red' => 1.95,
            'green' => 1.95,
            'violet' => 4.5
        ];

        return [
            'color' => $resultColor,
            'multiplier' => $multipliers[$resultColor]
        ];
    }

    /**
     * Get the round duration in seconds
     * 
     * @return int Round duration in seconds
     */
    public static function getRoundDuration(): int
    {
        return 180; // 3 minutes
    }

    /**
     * Get the betting phase duration in seconds
     * 
     * @return int Betting phase duration in seconds
     */
    public static function getBettingDuration(): int
    {
        return 150; // 2.5 minutes
    }
}

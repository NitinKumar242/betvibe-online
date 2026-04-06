<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Limbo Game
 * Instant multiplier game
 * Player sets target multiplier → server generates result → win if result >= target
 * Payout: exactly target multiplier (if win)
 * Win prob: 1/target * 0.97 (3% house edge)
 */
class LimboGame implements GameInterface
{
    /**
     * Play the game and return the result
     * 
     * @param array $betData Game-specific bet data ['target_multiplier' => float]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['target_multiplier'])) {
            throw new \InvalidArgumentException('target_multiplier is required');
        }

        $targetMultiplier = (float) $betData['target_multiplier'];

        // Validate target multiplier range
        if ($targetMultiplier < 1.01 || $targetMultiplier > 1000) {
            throw new \InvalidArgumentException('target_multiplier must be between 1.01 and 1000');
        }

        // Generate result using crash multiplier with 3% house edge
        $result = RNGService::crashMultiplier(0.03);

        // Determine if player won
        $won = ($result >= $targetMultiplier);
        $multiplier = $won ? $targetMultiplier : 0;
        $payout = $won ? $betAmount * $targetMultiplier : 0;

        return [
            'result' => $won ? 'win' : 'loss',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'target_multiplier' => $targetMultiplier,
                'result_multiplier' => $result,
                'win_probability' => round((1 / $targetMultiplier) * 0.97 * 100, 2)
            ]
        ];
    }
}

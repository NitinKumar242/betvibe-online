<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Dice Duel Game
 * Roll 1-100, bet Over/Under a threshold
 * Payout: 99/(100-threshold) for Over | 99/threshold for Under
 */
class DiceDuelGame implements GameInterface
{
    /**
     * Play game and return result
     * 
     * @param array $betData Game-specific bet data ['direction' => 'over'|'under', 'threshold' => int]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['direction']) || !isset($betData['threshold'])) {
            throw new \InvalidArgumentException('direction and threshold are required');
        }

        $direction = $betData['direction'];
        $threshold = (int) $betData['threshold'];

        if (!in_array($direction, ['over', 'under'])) {
            throw new \InvalidArgumentException('direction must be "over" or "under"');
        }

        if ($threshold < 2 || $threshold > 98) {
            throw new \InvalidArgumentException('threshold must be between 2 and 98');
        }

        // Roll dice (1-100)
        $roll = RNGService::randInt(1, 100);

        // Determine if player won
        $won = false;
        if ($direction === 'over') {
            $won = ($roll > $threshold);
        } else {
            $won = ($roll < $threshold);
        }

        // Calculate multiplier
        if ($direction === 'over') {
            $multiplier = $won ? round(99 / (100 - $threshold), 2) : 0;
        } else {
            $multiplier = $won ? round(99 / $threshold, 2) : 0;
        }

        $payout = $won ? $betAmount * $multiplier : 0;

        return [
            'result' => $won ? 'win' : 'loss',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'roll' => $roll,
                'direction' => $direction,
                'threshold' => $threshold,
                'win_probability' => round(($direction === 'over' ? (100 - $threshold) : $threshold - 1) * 0.99, 2)
            ]
        ];
    }
}

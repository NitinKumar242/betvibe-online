<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Coin Flip Game
 * 
 * Heads or Tails. Win = 1.9x.
 * RNG: random_int(0,1) with 50/50 probability
 * Fastest game — no complex UI, 3s animation
 */
class CoinFlipGame implements GameInterface
{
    private const PAYOUT_MULTIPLIER = 1.9;

    /**
     * Play a round of Coin Flip
     * 
     * @param array $betData ['choice' => 'heads'|'tails']
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $choice = $betData['choice'] ?? null;

        // Validate choice
        if (!in_array($choice, ['heads', 'tails'])) {
            throw new \InvalidArgumentException('Invalid choice. Must be heads or tails.');
        }

        // Flip coin (0 = heads, 1 = tails)
        $result = RNGService::randInt(0, 1);
        $resultSide = ($result === 0) ? 'heads' : 'tails';

        // Determine winner
        $won = ($choice === $resultSide);
        $payout = $won ? $betAmount * self::PAYOUT_MULTIPLIER : 0.0;

        return [
            'player_choice' => $choice,
            'result' => $resultSide,
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $won ? self::PAYOUT_MULTIPLIER : 0.0,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Get payout multiplier
     * 
     * @return float
     */
    public static function getPayoutMultiplier(): float
    {
        return self::PAYOUT_MULTIPLIER;
    }

    /**
     * Get available choices
     * 
     * @return array
     */
    public static function getChoices(): array
    {
        return [
            'heads' => ['label' => 'Heads', 'multiplier' => self::PAYOUT_MULTIPLIER],
            'tails' => ['label' => 'Tails', 'multiplier' => self::PAYOUT_MULTIPLIER]
        ];
    }
}

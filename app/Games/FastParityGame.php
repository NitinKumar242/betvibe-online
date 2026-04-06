<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Fast Parity Game
 * Timer-based round game (1 minute)
 * Bet Options: Even (1.9x) | Odd (1.9x) | Specific Number 0-9 (8.5x)
 * Hidden Rule: 0 and 5 = house wins all even/odd bets
 */
class FastParityGame implements GameInterface
{
    /**
     * Play the game and return the result
     * 
     * @param array $betData Game-specific bet data ['type' => 'even|odd|number', 'value' => 0-9]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['type'])) {
            throw new \InvalidArgumentException('Bet type is required');
        }

        $betType = $betData['type'];

        if (!in_array($betType, ['even', 'odd', 'number'])) {
            throw new \InvalidArgumentException('Invalid bet type. Must be even, odd, or number');
        }

        if ($betType === 'number' && !isset($betData['value'])) {
            throw new \InvalidArgumentException('Number value is required for number bets');
        }

        if ($betType === 'number') {
            $playerNumber = (int) $betData['value'];
            if ($playerNumber < 0 || $playerNumber > 9) {
                throw new \InvalidArgumentException('Number must be between 0 and 9');
            }
        }

        // Generate result (0-9)
        $resultNumber = RNGService::randInt(0, 9);
        $isEven = ($resultNumber % 2 === 0);
        $isOdd = !$isEven;

        // Determine if player won
        $won = false;
        $multiplier = 0;

        switch ($betType) {
            case 'even':
                // Hidden rule: 0 and 5 = house wins all even/odd bets
                if ($resultNumber === 0 || $resultNumber === 5) {
                    $won = false;
                } else {
                    $won = $isEven;
                }
                $multiplier = $won ? 1.9 : 0;
                break;

            case 'odd':
                // Hidden rule: 0 and 5 = house wins all even/odd bets
                if ($resultNumber === 0 || $resultNumber === 5) {
                    $won = false;
                } else {
                    $won = $isOdd;
                }
                $multiplier = $won ? 1.9 : 0;
                break;

            case 'number':
                $won = ($playerNumber === $resultNumber);
                $multiplier = $won ? 8.5 : 0;
                break;
        }

        $payout = $won ? $betAmount * $multiplier : 0;

        return [
            'result' => $won ? 'win' : 'loss',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'bet_type' => $betType,
                'player_value' => $betType === 'number' ? $playerNumber : null,
                'result_number' => $resultNumber,
                'is_even' => $isEven,
                'is_odd' => $isOdd,
                'house_wins' => ($resultNumber === 0 || $resultNumber === 5) && in_array($betType, ['even', 'odd'])
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
        $resultNumber = RNGService::randInt(0, 9);
        $isEven = ($resultNumber % 2 === 0);
        $isOdd = !$isEven;

        return [
            'number' => $resultNumber,
            'is_even' => $isEven,
            'is_odd' => $isOdd,
            'house_wins' => ($resultNumber === 0 || $resultNumber === 5)
        ];
    }

    /**
     * Get the round duration in seconds
     * 
     * @return int Round duration in seconds
     */
    public static function getRoundDuration(): int
    {
        return 60; // 1 minute
    }

    /**
     * Get the betting phase duration in seconds
     * 
     * @return int Betting phase duration in seconds
     */
    public static function getBettingDuration(): int
    {
        return 50; // 50 seconds
    }
}

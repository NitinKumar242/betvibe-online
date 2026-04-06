<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Roulette Lite Game
 * 
 * Bet types: Red(1.9x) | Black(1.9x) | Zero(17x) | Odd(1.9x) | Even(1.9x) | High(1.9x) | Low(1.9x)
 * RNG: random_int(0,36)
 * Zero rule: all even-money bets (red/black/odd/even/high/low) lose on 0
 */
class RouletteGame implements GameInterface
{
    private const RED_NUMBERS = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    private const BLACK_NUMBERS = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

    private const PAYOUTS = [
        'red' => 1.9,
        'black' => 1.9,
        'zero' => 17.0,
        'odd' => 1.9,
        'even' => 1.9,
        'high' => 1.9,
        'low' => 1.9
    ];

    /**
     * Play a round of Roulette Lite
     * 
     * @param array $betData ['bet' => 'red'|'black'|'zero'|'odd'|'even'|'high'|'low']
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $bet = $betData['bet'] ?? null;

        // Validate bet
        if (!in_array($bet, ['red', 'black', 'zero', 'odd', 'even', 'high', 'low'])) {
            throw new \InvalidArgumentException('Invalid bet type.');
        }

        // Spin wheel (0-36)
        $resultNumber = RNGService::randInt(0, 36);

        // Determine result properties
        $isRed = in_array($resultNumber, self::RED_NUMBERS);
        $isBlack = in_array($resultNumber, self::BLACK_NUMBERS);
        $isZero = ($resultNumber === 0);
        $isOdd = ($resultNumber > 0 && $resultNumber % 2 === 1);
        $isEven = ($resultNumber > 0 && $resultNumber % 2 === 0);
        $isHigh = ($resultNumber >= 19 && $resultNumber <= 36);
        $isLow = ($resultNumber >= 1 && $resultNumber <= 18);

        // Determine winner
        $won = false;
        switch ($bet) {
            case 'red':
                $won = $isRed;
                break;
            case 'black':
                $won = $isBlack;
                break;
            case 'zero':
                $won = $isZero;
                break;
            case 'odd':
                $won = $isOdd;
                break;
            case 'even':
                $won = $isEven;
                break;
            case 'high':
                $won = $isHigh;
                break;
            case 'low':
                $won = $isLow;
                break;
        }

        // Calculate payout
        $payout = $won ? $betAmount * self::PAYOUTS[$bet] : 0.0;

        return [
            'result_number' => $resultNumber,
            'color' => $isZero ? 'green' : ($isRed ? 'red' : 'black'),
            'player_bet' => $bet,
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $won ? self::PAYOUTS[$bet] : 0.0,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Get payout multiplier for a bet type
     * 
     * @param string $betType
     * @return float
     */
    public static function getPayoutMultiplier(string $betType): float
    {
        return self::PAYOUTS[$betType] ?? 0.0;
    }

    /**
     * Get all available bet types
     * 
     * @return array
     */
    public static function getBetTypes(): array
    {
        return [
            'red' => ['multiplier' => self::PAYOUTS['red'], 'label' => 'Red'],
            'black' => ['multiplier' => self::PAYOUTS['black'], 'label' => 'Black'],
            'zero' => ['multiplier' => self::PAYOUTS['zero'], 'label' => 'Zero'],
            'odd' => ['multiplier' => self::PAYOUTS['odd'], 'label' => 'Odd'],
            'even' => ['multiplier' => self::PAYOUTS['even'], 'label' => 'Even'],
            'high' => ['multiplier' => self::PAYOUTS['high'], 'label' => 'High (19-36)'],
            'low' => ['multiplier' => self::PAYOUTS['low'], 'label' => 'Low (1-18)']
        ];
    }

    /**
     * Get red numbers
     * 
     * @return array
     */
    public static function getRedNumbers(): array
    {
        return self::RED_NUMBERS;
    }

    /**
     * Get black numbers
     * 
     * @return array
     */
    public static function getBlackNumbers(): array
    {
        return self::BLACK_NUMBERS;
    }
}

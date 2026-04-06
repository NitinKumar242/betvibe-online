<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Number Guess Game
 * 
 * Pick exact number 1-10 = 8x (true 10x) OR pick range:
 *   - 1-3 = 2.5x | 1-5 = 1.5x | 6-10 = 1.5x | 8-10 = 2.5x
 * RNG: random_int(1,10)
 */
class NumberGuessGame implements GameInterface
{
    private const PAYOUTS = [
        'exact' => 8.0,
        '1-3' => 2.5,
        '1-5' => 1.5,
        '6-10' => 1.5,
        '8-10' => 2.5
    ];

    private const RANGES = [
        '1-3' => [1, 3],
        '1-5' => [1, 5],
        '6-10' => [6, 10],
        '8-10' => [8, 10]
    ];

    /**
     * Play a round of Number Guess
     * 
     * @param array $betData ['type' => 'exact'|'range', 'value' => int|string]
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $type = $betData['type'] ?? null;
        $value = $betData['value'] ?? null;

        // Validate bet type
        if (!in_array($type, ['exact', 'range'])) {
            throw new \InvalidArgumentException('Invalid bet type. Must be exact or range.');
        }

        // Generate random number
        $resultNumber = RNGService::randInt(1, 10);

        // Determine winner
        $won = false;
        $multiplier = 0.0;
        $betKey = '';

        if ($type === 'exact') {
            // Exact number bet
            if (!is_numeric($value) || $value < 1 || $value > 10) {
                throw new \InvalidArgumentException('Invalid exact number. Must be 1-10.');
            }
            $betKey = 'exact';
            $won = ((int) $value === $resultNumber);
            $multiplier = self::PAYOUTS['exact'];
        } else {
            // Range bet
            if (!isset(self::RANGES[$value])) {
                throw new \InvalidArgumentException('Invalid range. Must be 1-3, 1-5, 6-10, or 8-10.');
            }
            $betKey = $value;
            $range = self::RANGES[$value];
            $won = ($resultNumber >= $range[0] && $resultNumber <= $range[1]);
            $multiplier = self::PAYOUTS[$value];
        }

        // Calculate payout
        $payout = $won ? $betAmount * $multiplier : 0.0;

        return [
            'result_number' => $resultNumber,
            'bet_type' => $type,
            'bet_value' => $value,
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $won ? $multiplier : 0.0,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Get payout multiplier for a bet type
     * 
     * @param string $betKey
     * @return float
     */
    public static function getPayoutMultiplier(string $betKey): float
    {
        return self::PAYOUTS[$betKey] ?? 0.0;
    }

    /**
     * Get all available bet types
     * 
     * @return array
     */
    public static function getBetTypes(): array
    {
        return [
            'exact' => [
                'label' => 'Exact Number (1-10)',
                'multiplier' => self::PAYOUTS['exact'],
                'type' => 'exact'
            ],
            '1-3' => [
                'label' => 'Range 1-3',
                'multiplier' => self::PAYOUTS['1-3'],
                'type' => 'range'
            ],
            '1-5' => [
                'label' => 'Range 1-5',
                'multiplier' => self::PAYOUTS['1-5'],
                'type' => 'range'
            ],
            '6-10' => [
                'label' => 'Range 6-10',
                'multiplier' => self::PAYOUTS['6-10'],
                'type' => 'range'
            ],
            '8-10' => [
                'label' => 'Range 8-10',
                'multiplier' => self::PAYOUTS['8-10'],
                'type' => 'range'
            ]
        ];
    }

    /**
     * Get payout table
     * 
     * @return array
     */
    public static function getPayoutTable(): array
    {
        return self::PAYOUTS;
    }
}

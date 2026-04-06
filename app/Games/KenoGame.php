<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Keno Game
 * 
 * Player picks 1-10 numbers from 1-40. System draws 20 numbers. Payout by matches.
 * Payout table varies by number of picks.
 */
class KenoGame implements GameInterface
{
    private const NUMBERS_RANGE = 40;
    private const NUMBERS_DRAWN = 20;
    private const MAX_PICKS = 10;
    private const MIN_PICKS = 1;

    /**
     * Payout table: [picks][matches] = multiplier
     * Based on standard Keno payouts with house edge
     */
    private const PAYOUT_TABLE = [
        1 => [1 => 3.8],
        2 => [1 => 0, 2 => 12],
        3 => [1 => 0, 2 => 1.5, 3 => 42],
        4 => [1 => 0, 2 => 0.5, 3 => 3, 4 => 120],
        5 => [1 => 0, 2 => 0.5, 3 => 2, 4 => 10, 5 => 700],
        6 => [1 => 0, 2 => 0, 3 => 1, 4 => 4, 5 => 70, 6 => 1500],
        7 => [1 => 0, 2 => 0, 3 => 0.5, 4 => 2, 5 => 20, 6 => 300, 7 => 6000],
        8 => [1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 8, 6 => 80, 7 => 1000, 8 => 10000],
        9 => [1 => 0, 2 => 0, 3 => 0, 4 => 0.5, 5 => 4, 6 => 30, 7 => 300, 8 => 4000, 9 => 25000],
        10 => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 2, 6 => 15, 7 => 100, 8 => 1500, 9 => 8000, 10 => 100000]
    ];

    /**
     * Play a round of Keno
     * 
     * @param array $betData ['numbers' => [1, 5, 10, ...]]
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $pickedNumbers = $betData['numbers'] ?? [];

        // Validate picked numbers
        if (count($pickedNumbers) < self::MIN_PICKS || count($pickedNumbers) > self::MAX_PICKS) {
            throw new \InvalidArgumentException("Must pick between " . self::MIN_PICKS . " and " . self::MAX_PICKS . " numbers.");
        }

        // Validate numbers are unique and within range
        $pickedNumbers = array_unique($pickedNumbers);
        foreach ($pickedNumbers as $num) {
            if ($num < 1 || $num > self::NUMBERS_RANGE) {
                throw new \InvalidArgumentException("Numbers must be between 1 and " . self::NUMBERS_RANGE . ".");
            }
        }

        // Sort picked numbers
        sort($pickedNumbers);
        $pickCount = count($pickedNumbers);

        // Draw 20 random numbers
        $drawnNumbers = $this->drawNumbers();

        // Count matches
        $matches = count(array_intersect($pickedNumbers, $drawnNumbers));

        // Calculate payout
        $multiplier = $this->getPayoutMultiplier($pickCount, $matches);
        $payout = $betAmount * $multiplier;
        $won = ($multiplier > 0);

        return [
            'picked_numbers' => $pickedNumbers,
            'drawn_numbers' => $drawnNumbers,
            'matches' => $matches,
            'pick_count' => $pickCount,
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $multiplier,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Draw 20 random numbers from 1-40
     * 
     * @return array Array of 20 unique numbers
     */
    private function drawNumbers(): array
    {
        $numbers = range(1, self::NUMBERS_RANGE);

        // Fisher-Yates shuffle
        for ($i = self::NUMBERS_RANGE - 1; $i > 0; $i--) {
            $j = RNGService::randInt(0, $i);
            $temp = $numbers[$i];
            $numbers[$i] = $numbers[$j];
            $numbers[$j] = $temp;
        }

        // Return first 20 numbers
        return array_slice($numbers, 0, self::NUMBERS_DRAWN);
    }

    /**
     * Get payout multiplier based on picks and matches
     * 
     * @param int $pickCount Number of numbers picked
     * @param int $matches Number of matches
     * @return float Multiplier
     */
    private function getPayoutMultiplier(int $pickCount, int $matches): float
    {
        if (!isset(self::PAYOUT_TABLE[$pickCount][$matches])) {
            return 0.0;
        }
        return self::PAYOUT_TABLE[$pickCount][$matches];
    }

    /**
     * Get payout table for a specific number of picks
     * 
     * @param int $pickCount
     * @return array
     */
    public static function getPayoutTable(int $pickCount): array
    {
        return self::PAYOUT_TABLE[$pickCount] ?? [];
    }

    /**
     * Get all payout tables
     * 
     * @return array
     */
    public static function getAllPayoutTables(): array
    {
        return self::PAYOUT_TABLE;
    }

    /**
     * Get game configuration
     * 
     * @return array
     */
    public static function getGameConfig(): array
    {
        return [
            'numbers_range' => self::NUMBERS_RANGE,
            'numbers_drawn' => self::NUMBERS_DRAWN,
            'min_picks' => self::MIN_PICKS,
            'max_picks' => self::MAX_PICKS,
            'payout_tables' => self::PAYOUT_TABLE
        ];
    }
}

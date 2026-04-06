<?php

namespace App\Services;

/**
 * RNG Service
 * Cryptographically secure random number generation for all games
 */
class RNGService
{
    /**
     * Cryptographically secure random integer
     * 
     * @param int $min Minimum value (inclusive)
     * @param int $max Maximum value (inclusive)
     * @return int Random integer between min and max
     */
    public static function randInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * Random float between 0.0 and 1.0
     * 
     * @return float Random float between 0.0 and 1.0
     */
    public static function randFloat(): float
    {
        return random_int(0, PHP_INT_MAX) / PHP_INT_MAX;
    }

    /**
     * Weighted random pick
     * 
     * @param array $weights Associative array of weights ['key' => weight]
     * @return string The selected key
     * 
     * @example weightedRandom(['red'=>45, 'green'=>45, 'violet'=>10])
     */
    public static function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * Generate Crash multiplier using provably fair method
     * 
     * @param float $houseEdge House edge (default 4%)
     * @return float Crash multiplier capped at 1000.00, rounded to 2 decimals
     */
    public static function crashMultiplier(float $houseEdge = 0.04): float
    {
        $r = self::randFloat();

        // 4% chance of instant crash
        if ($r < $houseEdge) {
            return 1.00;
        }

        // Inverse CDF distribution
        $multiplier = max(1.00, (1 / (1 - $r)) * (1 - $houseEdge));

        // Cap at 1000.00 and round to 2 decimal places
        return min(round($multiplier, 2), 1000.00);
    }

    /**
     * Generate mine positions for Mines game
     * Uses Fisher-Yates shuffle with random_int for cryptographic security
     * 
     * @param int $gridSize Total number of tiles (e.g., 25 for 5x5)
     * @param int $mineCount Number of mines to place
     * @return array Array of mine positions (0 to gridSize-1)
     */
    public static function generateMines(int $gridSize, int $mineCount): array
    {
        $positions = range(0, $gridSize - 1);

        // Fisher-Yates shuffle using random_int
        for ($i = $gridSize - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $positions[$i];
            $positions[$i] = $positions[$j];
            $positions[$j] = $temp;
        }

        return array_slice($positions, 0, $mineCount);
    }

    /**
     * Generate slot reel symbol
     * Same as weightedRandom - returns symbol name
     * 
     * @param array $weights Associative array of symbol weights
     * @return string The selected symbol name
     */
    public static function generateSlotReel(array $weights): string
    {
        return self::weightedRandom($weights);
    }

    /**
     * Generate cryptographically secure random bytes
     * 
     * @param int $length Number of bytes to generate
     * @return string Random bytes
     */
    public static function generateBytes(int $length): string
    {
        return random_bytes($length);
    }

    /**
     * Generate random float within a range
     * 
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return float Random float between min and max
     */
    public static function generateFloat(float $min, float $max): float
    {
        return $min + self::randFloat() * ($max - $min);
    }

    /**
     * Generate random integer within a range (instance method wrapper)
     * 
     * @param int $min Minimum value (inclusive)
     * @param int $max Maximum value (inclusive)
     * @return int Random integer between min and max
     */
    public function generateInt(int $min, int $max): int
    {
        return self::randInt($min, $max);
    }
}

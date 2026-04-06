<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Spin Wheel Game
 * 
 * 52-segment weighted wheel. Server picks segment, GSAP animates cosmetically.
 * Expected value = ~0.79 → house edge ~21%
 */
class SpinWheelGame implements GameInterface
{
    private const SEGMENTS = [
        0.2,
        0.2,
        0.5,
        0.2,
        0.2,
        0.5,
        0.2,
        0.2,
        0.5,
        0.2,  // 10
        0.2,
        0.5,
        0.2,
        0.2,
        0.5,
        0.2,
        0.2,
        1.5,
        0.2,
        0.2,  // 20
        0.5,
        0.2,
        0.2,
        0.5,
        0.2,
        1.5,
        0.2,
        0.2,
        0.5,
        0.2,  // 30
        0.2,
        1.5,
        0.2,
        0.2,
        3.0,
        0.2,
        0.2,
        1.5,
        0.2,
        0.2,  // 40
        0.5,
        0.2,
        1.5,
        0.2,
        3.0,
        0.2,
        1.5,
        0.2,
        3.0,
        5.0,  // 50
        3.0,
        50.0                                              // 52
    ];

    /**
     * Play a round of Spin Wheel
     * 
     * @param array $betData Empty array (no bet data needed)
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        // Pick random segment
        $resultIndex = RNGService::randInt(0, 51);
        $multiplier = self::SEGMENTS[$resultIndex];

        // Calculate payout
        $payout = $betAmount * $multiplier;
        $won = ($multiplier >= 1.0);

        return [
            'segment_index' => $resultIndex,
            'multiplier' => $multiplier,
            'won' => $won,
            'payout' => $payout,
            'profit' => $payout - $betAmount,
            'segments' => self::SEGMENTS
        ];
    }

    /**
     * Get all wheel segments
     * 
     * @return array
     */
    public static function getSegments(): array
    {
        return self::SEGMENTS;
    }

    /**
     * Get expected value of the wheel
     * 
     * @return float
     */
    public static function getExpectedValue(): float
    {
        return array_sum(self::SEGMENTS) / count(self::SEGMENTS);
    }

    /**
     * Get house edge percentage
     * 
     * @return float
     */
    public static function getHouseEdge(): float
    {
        return (1.0 - self::getExpectedValue()) * 100;
    }
}

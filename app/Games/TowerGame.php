<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Tower Climb Game
 * Multi-step grid game
 * Floors 1-12, each floor has increasing tile count (1 safe among N)
 * Floor 1: 1/2, Floor 2: 1/3, Floor 3: 1/4... Floor 6+: 1/5
 */
class TowerGame implements GameInterface
{
    /**
     * Floor configurations
     * Each floor has a certain number of tiles and a multiplier
     */
    private static $floorConfig = [
        1 => ['tiles' => 2, 'multiplier' => 1.95],
        2 => ['tiles' => 3, 'multiplier' => 2.90],
        3 => ['tiles' => 4, 'multiplier' => 3.85],
        4 => ['tiles' => 5, 'multiplier' => 4.80],
        5 => ['tiles' => 5, 'multiplier' => 5.75],
        6 => ['tiles' => 5, 'multiplier' => 6.70],
        7 => ['tiles' => 5, 'multiplier' => 7.65],
        8 => ['tiles' => 5, 'multiplier' => 8.60],
        9 => ['tiles' => 5, 'multiplier' => 9.55],
        10 => ['tiles' => 5, 'multiplier' => 10.50],
        11 => ['tiles' => 5, 'multiplier' => 11.45],
        12 => ['tiles' => 5, 'multiplier' => 12.40],
    ];

    /**
     * Play game and return result
     * Note: Tower is multi-step, this is for instant play mode
     * 
     * @param array $betData Game-specific bet data ['floor' => int, 'tile_index' => int]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['floor']) || !isset($betData['tile_index'])) {
            throw new \InvalidArgumentException('floor and tile_index are required');
        }

        $floor = (int) $betData['floor'];
        $tileIndex = (int) $betData['tile_index'];

        if ($floor < 1 || $floor > 12) {
            throw new \InvalidArgumentException('floor must be between 1 and 12');
        }

        $config = self::$floorConfig[$floor];

        if ($tileIndex < 0 || $tileIndex >= $config['tiles']) {
            throw new \InvalidArgumentException("tile_index must be between 0 and " . ($config['tiles'] - 1));
        }

        // Generate safe tile position for this floor
        $safeTile = RNGService::randInt(0, $config['tiles'] - 1);
        $isSafe = ($tileIndex === $safeTile);

        if (!$isSafe) {
            // Player picked wrong tile - loss
            return [
                'result' => 'loss',
                'multiplier' => 0,
                'payout' => 0,
                'game_data' => [
                    'floor' => $floor,
                    'tile_index' => $tileIndex,
                    'is_safe' => false,
                    'safe_tile' => $safeTile,
                    'tiles_count' => $config['tiles']
                ]
            ];
        }

        // Safe tile - calculate multiplier
        $multiplier = $config['multiplier'];
        $payout = $betAmount * $multiplier;

        return [
            'result' => 'win',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'floor' => $floor,
                'tile_index' => $tileIndex,
                'is_safe' => true,
                'multiplier' => $multiplier,
                'tiles_count' => $config['tiles']
            ]
        ];
    }

    /**
     * Get floor configuration
     * 
     * @param int $floor Floor number (1-12)
     * @return array|null Floor configuration or null if invalid
     */
    public static function getFloorConfig(int $floor): ?array
    {
        return self::$floorConfig[$floor] ?? null;
    }

    /**
     * Get all floor configurations
     * 
     * @return array All floor configurations
     */
    public static function getAllFloorConfigs(): array
    {
        return self::$floorConfig;
    }

    /**
     * Generate safe tile for a floor
     * 
     * @param int $floor Floor number (1-12)
     * @return int Safe tile index
     */
    public static function generateSafeTile(int $floor): int
    {
        $config = self::$floorConfig[$floor];
        return RNGService::randInt(0, $config['tiles'] - 1);
    }

    /**
     * Calculate cumulative multiplier for multiple floors
     * 
     * @param int $floorsCleared Number of floors cleared
     * @return float Cumulative multiplier
     */
    public static function calculateCumulativeMultiplier(int $floorsCleared): float
    {
        $multiplier = 1.0;

        for ($i = 1; $i <= $floorsCleared; $i++) {
            $config = self::$floorConfig[$i];
            $multiplier *= $config['multiplier'];
        }

        return round($multiplier, 2);
    }
}

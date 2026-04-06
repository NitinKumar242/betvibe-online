<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Mines Game
 * Multi-step grid game
 * 5x5 grid (25 tiles), player picks mine count (1-24)
 * Reveal tiles one by one, multiplier increases with each safe tile
 */
class MinesGame implements GameInterface
{
    /**
     * Play game and return result
     * Note: Mines is multi-step, this is for instant play mode
     * 
     * @param array $betData Game-specific bet data ['mine_count' => int]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['mine_count'])) {
            throw new \InvalidArgumentException('mine_count is required');
        }

        $mineCount = (int) $betData['mine_count'];

        if ($mineCount < 1 || $mineCount > 24) {
            throw new \InvalidArgumentException('mine_count must be between 1 and 24');
        }

        // Generate mine positions
        $minePositions = RNGService::generateMines(25, $mineCount);

        // For instant play, simulate revealing one random tile
        $tileIndex = RNGService::randInt(0, 24);
        $isMine = in_array($tileIndex, $minePositions);

        if ($isMine) {
            // Player hit a mine - instant loss
            return [
                'result' => 'loss',
                'multiplier' => 0,
                'payout' => 0,
                'game_data' => [
                    'mine_count' => $mineCount,
                    'tile_index' => $tileIndex,
                    'is_mine' => true,
                    'mine_positions' => $minePositions
                ]
            ];
        }

        // Safe tile - calculate multiplier
        $multiplier = $this->calculateMultiplier(1, $mineCount);
        $payout = $betAmount * $multiplier;

        return [
            'result' => 'win',
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'mine_count' => $mineCount,
                'tile_index' => $tileIndex,
                'is_mine' => false,
                'multiplier' => $multiplier,
                'mine_positions' => [] // Don't reveal in instant play
            ]
        ];
    }

    /**
     * Calculate multiplier for a given number of safe tiles revealed
     * Formula: multiplier = prev_multiplier * ((25 - mines - safe_so_far) / (25 - safe_so_far)) * 0.97
     * 
     * @param int $safeRevealed Number of safe tiles revealed
     * @param int $mineCount Total number of mines
     * @return float Current multiplier
     */
    public static function calculateMultiplier(int $safeRevealed, int $mineCount): float
    {
        $multiplier = 1.0;
        
        for ($i = 0; $i < $safeRevealed; $i++) {
            $multiplier *= ((25 - $mineCount - $i) / (25 - $i)) * 0.97;
        }
        
        return round($multiplier, 2);
    }

    /**
     * Generate mine positions for a new game
     * 
     * @param int $mineCount Number of mines (1-24)
     * @return array Array of mine positions (0-24)
     */
    public static function generateMinePositions(int $mineCount): array
    {
        return RNGService::generateMines(25, $mineCount);
    }

    /**
     * Check if a tile is a mine
     * 
     * @param int $tileIndex Tile index (0-24)
     * @param array $minePositions Array of mine positions
     * @return bool True if mine, false otherwise
     */
    public static function isMine(int $tileIndex, array $minePositions): bool
    {
        return in_array($tileIndex, $minePositions);
    }
}

<?php

namespace App\Games;

use App\Games\ColorPredictGame;
use App\Games\FastParityGame;
use App\Games\CrashGame;
use App\Games\LimboGame;
use App\Games\MinesGame;
use App\Games\PlinkoGame;
use App\Games\KenoGame;
use App\Games\TowerGame;
use App\Games\DiceDuelGame;
use App\Games\HiLoGame;
use App\Games\DragonTigerGame;
use App\Games\SpinWheelGame;
use App\Games\CoinFlipGame;
use App\Games\RouletteGame;
use App\Games\LuckySlotsGame;
use App\Games\NumberGuessGame;

/**
 * Game Factory
 * Returns the correct game class instance based on game slug
 */
class GameFactory
{
    /**
     * Map of game slugs to their class names
     */
    private static $gameMap = [
        'color-predict' => ColorPredictGame::class,
        'fast-parity' => FastParityGame::class,
        'crash' => CrashGame::class,
        'limbo' => LimboGame::class,
        'mines' => MinesGame::class,
        'plinko' => PlinkoGame::class,
        'keno' => KenoGame::class,
        'tower' => TowerGame::class,
        'dice-duel' => DiceDuelGame::class,
        'hilo' => HiLoGame::class,
        'dragon-tiger' => DragonTigerGame::class,
        'spin-wheel' => SpinWheelGame::class,
        'coin-flip' => CoinFlipGame::class,
        'roulette' => RouletteGame::class,
        'lucky-slots' => LuckySlotsGame::class,
        'number-guess' => NumberGuessGame::class,
    ];

    /**
     * Resolve game slug to game class instance
     * 
     * @param string $slug The game slug
     * @return GameInterface The game instance
     * @throws \InvalidArgumentException If game slug is not found
     */
    public static function resolve(string $slug): GameInterface
    {
        if (!isset(self::$gameMap[$slug])) {
            throw new \InvalidArgumentException("Game '{$slug}' not found");
        }

        $className = self::$gameMap[$slug];

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Game class '{$className}' does not exist");
        }

        return new $className();
    }

    /**
     * Get all available game slugs
     * 
     * @return array Array of game slugs
     */
    public static function getAvailableGames(): array
    {
        return array_keys(self::$gameMap);
    }

    /**
     * Check if a game slug exists
     * 
     * @param string $slug The game slug
     * @return bool True if game exists, false otherwise
     */
    public static function gameExists(string $slug): bool
    {
        return isset(self::$gameMap[$slug]);
    }
}

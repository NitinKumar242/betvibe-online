<?php

namespace App\Games;

/**
 * Game Interface
 * All game classes must implement this interface
 */
interface GameInterface
{
    /**
     * Play the game and return the result
     * 
     * @param array $betData Game-specific bet data (e.g., color, target multiplier, etc.)
     * @param float $betAmount The amount wagered
     * @return array Result array with the following structure:
     *               [
     *                 'result' => 'win|loss',
     *                 'multiplier' => float,
     *                 'payout' => float,
     *                 'game_data' => array  // Game-specific data (revealed tiles, cards, etc.)
     *               ]
     */
    public function play(array $betData, float $betAmount): array;
}

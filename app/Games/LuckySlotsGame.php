<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Lucky Slots Game
 * 
 * 3 reels, 5 paylines. Each reel independently picks symbol via weighted RNG.
 * Near-miss: if 2 high symbols match, 3rd reel biased toward adjacent non-matching symbol
 * Payout table:
 *   - 3× diamond = 100x | 3× seven = 30x | 3× grape = 10x
 *   - 3× orange = 5x | 3× lemon = 3x | 3× cherry = 2x
 *   - 2× diamond = 3x | all other 2× match = 0.5x
 *   - No match = 0x
 */
class LuckySlotsGame implements GameInterface
{
    private const SYMBOLS = ['cherry', 'lemon', 'orange', 'grape', 'seven', 'diamond'];

    private const WEIGHTS = [
        'cherry' => 35,
        'lemon' => 25,
        'orange' => 20,
        'grape' => 12,
        'seven' => 6,
        'diamond' => 2,
    ];

    private const PAYOUTS = [
        '3_diamond' => 100.0,
        '3_seven' => 30.0,
        '3_grape' => 10.0,
        '3_orange' => 5.0,
        '3_lemon' => 3.0,
        '3_cherry' => 2.0,
        '2_diamond' => 3.0,
        '2_other' => 0.5,
    ];

    private const HIGH_SYMBOLS = ['seven', 'diamond'];

    /**
     * Play a round of Lucky Slots
     * 
     * @param array $betData Empty array (no bet data needed)
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        // Spin reels
        $reels = [
            $this->spinReel(),
            $this->spinReel(),
            $this->spinReel()
        ];

        // Check for near-miss illusion
        $reels = $this->applyNearMiss($reels);

        // Count symbol occurrences
        $symbolCounts = array_count_values($reels);

        // Determine payout
        $payoutResult = $this->calculatePayout($reels, $symbolCounts);
        $multiplier = $payoutResult['multiplier'];
        $payout = $betAmount * $multiplier;
        $won = ($multiplier > 0);

        return [
            'reels' => $reels,
            'symbol_counts' => $symbolCounts,
            'win_type' => $payoutResult['type'],
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $multiplier,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Spin a single reel using weighted RNG
     * 
     * @return string Symbol
     */
    private function spinReel(): string
    {
        return RNGService::weightedRandom(self::WEIGHTS);
    }

    /**
     * Apply near-miss illusion for high symbols
     * If 2 reels match on high symbol, bias 3rd reel toward adjacent non-matching symbol
     * 
     * @param array $reels
     * @return array Modified reels
     */
    private function applyNearMiss(array $reels): array
    {
        // Check if first two reels match on a high symbol
        if (in_array($reels[0], self::HIGH_SYMBOLS) && $reels[0] === $reels[1]) {
            // Get symbol index
            $symbolIndex = array_search($reels[0], self::SYMBOLS);

            // Get adjacent symbols (with wrap-around)
            $prevIndex = ($symbolIndex - 1 + count(self::SYMBOLS)) % count(self::SYMBOLS);
            $nextIndex = ($symbolIndex + 1) % count(self::SYMBOLS);

            $adjacentSymbols = [
                self::SYMBOLS[$prevIndex],
                self::SYMBOLS[$nextIndex]
            ];

            // Bias third reel toward adjacent symbols (70% chance)
            if (RNGService::randInt(1, 100) <= 70) {
                $biasedWeights = self::WEIGHTS;

                // Increase weights for adjacent symbols
                foreach ($adjacentSymbols as $adjacent) {
                    $biasedWeights[$adjacent] *= 5;
                }

                // Decrease weight for matching symbol
                $biasedWeights[$reels[0]] = max(1, $biasedWeights[$reels[0]] / 10);

                $reels[2] = RNGService::weightedRandom($biasedWeights);
            }
        }

        return $reels;
    }

    /**
     * Calculate payout based on symbol combinations
     * 
     * @param array $reels
     * @param array $symbolCounts
     * @return array ['multiplier' => float, 'type' => string]
     */
    private function calculatePayout(array $reels, array $symbolCounts): array
    {
        // Check for 3 of a kind
        foreach ($symbolCounts as $symbol => $count) {
            if ($count === 3) {
                $key = "3_{$symbol}";
                if (isset(self::PAYOUTS[$key])) {
                    return [
                        'multiplier' => self::PAYOUTS[$key],
                        'type' => $key
                    ];
                }
            }
        }

        // Check for 2 of a kind
        foreach ($symbolCounts as $symbol => $count) {
            if ($count === 2) {
                if ($symbol === 'diamond') {
                    return [
                        'multiplier' => self::PAYOUTS['2_diamond'],
                        'type' => '2_diamond'
                    ];
                } else {
                    return [
                        'multiplier' => self::PAYOUTS['2_other'],
                        'type' => '2_other'
                    ];
                }
            }
        }

        // No match
        return [
            'multiplier' => 0.0,
            'type' => 'no_match'
        ];
    }

    /**
     * Get all symbols
     * 
     * @return array
     */
    public static function getSymbols(): array
    {
        return self::SYMBOLS;
    }

    /**
     * Get symbol weights
     * 
     * @return array
     */
    public static function getWeights(): array
    {
        return self::WEIGHTS;
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

    /**
     * Get game configuration
     * 
     * @return array
     */
    public static function getGameConfig(): array
    {
        return [
            'reels' => 3,
            'paylines' => 5,
            'symbols' => self::SYMBOLS,
            'weights' => self::WEIGHTS,
            'payouts' => self::PAYOUTS
        ];
    }
}

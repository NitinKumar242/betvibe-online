<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Plinko Game
 * 
 * Grid: 12 rows, 13 slots
 * Risk modes: Low | Medium | High (different multiplier distributions)
 * Mechanics: Server picks slot via weighted RNG. Frontend animates ball purely cosmetically.
 */
class PlinkoGame implements GameInterface
{
    private const SLOTS = 13;

    private const MULTIPLIERS = [
        'low' => [0.5, 0.7, 1.0, 1.0, 1.0, 1.5, 1.0, 1.0, 1.0, 0.7, 0.5, 0.3, 0.2],
        'medium' => [3.0, 1.5, 1.0, 0.7, 0.5, 0.5, 0.5, 0.5, 0.7, 1.0, 1.5, 3.0, 5.0],
        'high' => [1000, 10, 3, 1, 0.5, 0.3, 0.2, 0.3, 0.5, 1, 3, 10, 1000]
    ];

    private const SLOT_WEIGHTS = [
        'low' => [8, 10, 12, 12, 12, 12, 12, 12, 12, 10, 8, 6, 4],
        'medium' => [2, 4, 8, 12, 15, 18, 18, 18, 15, 12, 8, 4, 2],
        'high' => [0.5, 1, 2, 5, 10, 15, 24, 15, 10, 5, 2, 1, 0.5]
    ];

    /**
     * Play a round of Plinko
     * 
     * @param array $betData ['risk' => 'low'|'medium'|'high']
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $risk = $betData['risk'] ?? 'medium';

        // Validate risk level
        if (!in_array($risk, ['low', 'medium', 'high'])) {
            throw new \InvalidArgumentException('Invalid risk level. Must be low, medium, or high.');
        }

        // Pick slot using weighted RNG
        $slotIndex = $this->pickSlot($risk);
        $multiplier = self::MULTIPLIERS[$risk][$slotIndex];

        // Calculate payout
        $payout = $betAmount * $multiplier;
        $won = ($multiplier >= 1.0);

        return [
            'risk' => $risk,
            'slot_index' => $slotIndex,
            'multiplier' => $multiplier,
            'won' => $won,
            'payout' => $payout,
            'profit' => $payout - $betAmount,
            'all_multipliers' => self::MULTIPLIERS[$risk]
        ];
    }

    /**
     * Pick a slot using weighted RNG
     * 
     * @param string $risk
     * @return int Slot index (0-12)
     */
    private function pickSlot(string $risk): int
    {
        $weights = self::SLOT_WEIGHTS[$risk];

        // Normalize weights to integers
        $normalizedWeights = [];
        foreach ($weights as $weight) {
            $normalizedWeights[] = (int) ($weight * 10);
        }

        // Use weighted random selection
        $totalWeight = array_sum($normalizedWeights);
        $random = RNGService::randInt(1, $totalWeight);

        $currentWeight = 0;
        foreach ($normalizedWeights as $index => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $index;
            }
        }

        // Fallback to last slot
        return self::SLOTS - 1;
    }

    /**
     * Get multipliers for a risk level
     * 
     * @param string $risk
     * @return array
     */
    public static function getMultipliers(string $risk): array
    {
        return self::MULTIPLIERS[$risk] ?? self::MULTIPLIERS['medium'];
    }

    /**
     * Get all risk levels
     * 
     * @return array
     */
    public static function getRiskLevels(): array
    {
        return [
            'low' => [
                'label' => 'Low Risk',
                'multipliers' => self::MULTIPLIERS['low'],
                'description' => 'Consistent small wins'
            ],
            'medium' => [
                'label' => 'Medium Risk',
                'multipliers' => self::MULTIPLIERS['medium'],
                'description' => 'Balanced risk and reward'
            ],
            'high' => [
                'label' => 'High Risk',
                'multipliers' => self::MULTIPLIERS['high'],
                'description' => 'High variance, big wins possible'
            ]
        ];
    }

    /**
     * Get game configuration
     * 
     * @return array
     */
    public static function getGameConfig(): array
    {
        return [
            'rows' => 12,
            'slots' => self::SLOTS,
            'risk_levels' => self::getRiskLevels()
        ];
    }
}

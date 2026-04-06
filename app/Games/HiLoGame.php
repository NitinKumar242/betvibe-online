<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * HiLo Cards Game
 * Multi-step card game
 * Card shown (2-Ace), player bets: next card Higher or Lower
 * Correct = multiplier up, keep going or cashout. Wrong = lose all.
 */
class HiLoGame implements GameInterface
{
    /**
     * Card values (2-14, where 14 = Ace)
     */
    private static $cardValues = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

    /**
     * Payout factors per card value and direction
     */
    private static $payoutFactors = [
        // For higher bets: [card_value] => multiplier
        'higher' => [
            2 => 1.15,
            3 => 1.20,
            4 => 1.25,
            5 => 1.30,
            6 => 1.35,
            7 => 1.40,
            8 => 1.45,
            9 => 1.50,
            10 => 1.55,
            11 => 1.60,
            12 => 1.65,
            13 => 1.70,
            14 => 1.75
        ],
        // For lower bets: [card_value] => multiplier
        'lower' => [
            2 => 1.75,
            3 => 1.70,
            4 => 1.65,
            5 => 1.60,
            6 => 1.55,
            7 => 1.50,
            8 => 1.45,
            9 => 1.40,
            10 => 1.35,
            11 => 1.30,
            12 => 1.25,
            13 => 1.20,
            14 => 1.15
        ]
    ];

    /**
     * Play game and return result
     * Note: HiLo is multi-step, this is for instant play mode
     * 
     * @param array $betData Game-specific bet data ['direction' => 'higher'|'lower', 'current_card' => int]
     * @param float $betAmount The amount wagered
     * @return array Result array
     */
    public function play(array $betData, float $betAmount): array
    {
        // Validate bet data
        if (!isset($betData['direction']) || !isset($betData['current_card'])) {
            throw new \InvalidArgumentException('direction and current_card are required');
        }

        $direction = $betData['direction'];
        $currentCard = (int) $betData['current_card'];

        if (!in_array($direction, ['higher', 'lower'])) {
            throw new \InvalidArgumentException('direction must be "higher" or "lower"');
        }

        if (!in_array($currentCard, self::$cardValues)) {
            throw new \InvalidArgumentException('current_card must be between 2 and 14');
        }

        // Generate next card
        $nextCard = self::$cardValues[RNGService::randInt(0, count(self::$cardValues) - 1)];

        // Determine if player won
        $won = false;
        $isPush = false;

        if ($nextCard === $currentCard) {
            // Push - equal cards, no win/loss
            $isPush = true;
        } elseif ($direction === 'higher') {
            $won = ($nextCard > $currentCard);
        } else {
            $won = ($nextCard < $currentCard);
        }

        // Calculate multiplier
        $multiplier = 0;
        if ($won) {
            $multiplier = self::$payoutFactors[$direction][$currentCard];
        }

        $payout = $won ? $betAmount * $multiplier : 0;

        return [
            'result' => $isPush ? 'push' : ($won ? 'win' : 'loss'),
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => [
                'current_card' => $currentCard,
                'next_card' => $nextCard,
                'direction' => $direction,
                'is_push' => $isPush,
                'payout_factor' => $won ? self::$payoutFactors[$direction][$currentCard] : 0
            ]
        ];
    }

    /**
     * Generate a shuffled deck
     * 
     * @return array Shuffled deck of card values
     */
    public static function generateDeck(): array
    {
        $deck = self::$cardValues;

        // Fisher-Yates shuffle
        for ($i = count($deck) - 1; $i > 0; $i--) {
            $j = RNGService::randInt(0, $i);
            $temp = $deck[$i];
            $deck[$i] = $deck[$j];
            $deck[$j] = $temp;
        }

        return $deck;
    }

    /**
     * Get card name from value
     * 
     * @param int $value Card value (2-14)
     * @return string Card name
     */
    public static function getCardName(int $value): string
    {
        $names = [
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '10',
            11 => 'Jack',
            12 => 'Queen',
            13 => 'King',
            14 => 'Ace'
        ];

        return $names[$value] ?? (string) $value;
    }

    /**
     * Get payout factor for a card and direction
     * 
     * @param int $cardValue Card value (2-14)
     * @param string $direction 'higher' or 'lower'
     * @return float Payout factor
     */
    public static function getPayoutFactor(int $cardValue, string $direction): float
    {
        return self::$payoutFactors[$direction][$cardValue] ?? 1.0;
    }
}

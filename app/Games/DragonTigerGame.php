<?php

namespace App\Games;

use App\Services\RNGService;

/**
 * Dragon Tiger Game
 * 
 * Two cards dealt face-up: Dragon vs Tiger. Bet which is higher (or Tie).
 * Payouts: Dragon/Tiger = 1.95x | Tie = 8x
 * Equal cards: counts as Tie, non-Tie bets lose half
 */
class DragonTigerGame implements GameInterface
{
    private const CARD_VALUES = [
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '6' => 6,
        '7' => 7,
        '8' => 8,
        '9' => 9,
        '10' => 10,
        'J' => 11,
        'Q' => 12,
        'K' => 13,
        'A' => 14
    ];

    private const SUITS = ['hearts', 'diamonds', 'clubs', 'spades'];

    private const PAYOUTS = [
        'dragon' => 1.95,
        'tiger' => 1.95,
        'tie' => 8.0
    ];

    /**
     * Play a round of Dragon Tiger
     * 
     * @param array $betData ['bet' => 'dragon'|'tiger'|'tie']
     * @param float $betAmount Amount wagered
     * @return array Game result
     */
    public function play(array $betData, float $betAmount): array
    {
        $bet = $betData['bet'] ?? null;

        // Validate bet
        if (!in_array($bet, ['dragon', 'tiger', 'tie'])) {
            throw new \InvalidArgumentException('Invalid bet. Must be dragon, tiger, or tie.');
        }

        // Deal two cards
        $dragonCard = $this->dealCard();
        $tigerCard = $this->dealCard();

        // Determine winner
        $result = $this->determineWinner($dragonCard, $tigerCard);

        // Calculate payout
        $payout = 0.0;
        $won = false;

        if ($result['winner'] === $bet) {
            $payout = $betAmount * self::PAYOUTS[$bet];
            $won = true;
        } elseif ($result['winner'] === 'tie' && $bet !== 'tie') {
            // Non-tie bets lose half on tie
            $payout = $betAmount * 0.5;
            $won = false;
        }

        return [
            'dragon_card' => $dragonCard,
            'tiger_card' => $tigerCard,
            'winner' => $result['winner'],
            'player_bet' => $bet,
            'won' => $won,
            'payout' => $payout,
            'multiplier' => $won ? self::PAYOUTS[$bet] : 0.0,
            'profit' => $payout - $betAmount
        ];
    }

    /**
     * Deal a random card
     * 
     * @return array Card with value and suit
     */
    private function dealCard(): array
    {
        $values = array_keys(self::CARD_VALUES);
        $value = $values[RNGService::randInt(0, count($values) - 1)];
        $suit = self::SUITS[RNGService::randInt(0, count(self::SUITS) - 1)];

        return [
            'value' => $value,
            'suit' => $suit,
            'numeric_value' => self::CARD_VALUES[$value]
        ];
    }

    /**
     * Determine the winner between dragon and tiger cards
     * 
     * @param array $dragonCard
     * @param array $tigerCard
     * @return array ['winner' => 'dragon'|'tiger'|'tie', 'is_tie' => bool]
     */
    private function determineWinner(array $dragonCard, array $tigerCard): array
    {
        $dragonValue = $dragonCard['numeric_value'];
        $tigerValue = $tigerCard['numeric_value'];

        if ($dragonValue > $tigerValue) {
            return ['winner' => 'dragon', 'is_tie' => false];
        } elseif ($tigerValue > $dragonValue) {
            return ['winner' => 'tiger', 'is_tie' => false];
        } else {
            return ['winner' => 'tie', 'is_tie' => true];
        }
    }

    /**
     * Get the payout multiplier for a bet type
     * 
     * @param string $betType
     * @return float
     */
    public static function getPayoutMultiplier(string $betType): float
    {
        return self::PAYOUTS[$betType] ?? 0.0;
    }

    /**
     * Get all available bet types
     * 
     * @return array
     */
    public static function getBetTypes(): array
    {
        return [
            'dragon' => ['multiplier' => self::PAYOUTS['dragon'], 'label' => 'Dragon'],
            'tiger' => ['multiplier' => self::PAYOUTS['tiger'], 'label' => 'Tiger'],
            'tie' => ['multiplier' => self::PAYOUTS['tie'], 'label' => 'Tie']
        ];
    }
}

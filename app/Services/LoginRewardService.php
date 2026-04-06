<?php
/**
 * BetVibe - Login Reward Service
 * Daily login rewards with 7-day streak cycle
 */

namespace App\Services;

use App\Core\DB;

class LoginRewardService
{
    private DB $db;

    /** @var int[] Reward tiers by day (Day 1-7) */
    private array $rewards = [20, 30, 50, 75, 100, 150, 300];

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Get daily reward status for a user
     */
    public function getStatus(int $userId): array
    {
        $lastReward = $this->db->first(
            "SELECT * FROM login_rewards WHERE user_id = ? ORDER BY given_at DESC LIMIT 1",
            [$userId]
        );

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Already claimed today?
        if ($lastReward && date('Y-m-d', strtotime($lastReward['given_at'])) === $today) {
            return [
                'can_claim' => false,
                'day_number' => (int)$lastReward['day_number'],
                'reward_coins' => (int)$lastReward['coins_given'],
                'streak' => (int)$lastReward['day_number'],
                'already_claimed' => true,
                'rewards_schedule' => $this->rewards,
            ];
        }

        // Calculate day number
        $dayNum = 1;
        if ($lastReward && date('Y-m-d', strtotime($lastReward['given_at'])) === $yesterday) {
            $dayNum = min(((int)$lastReward['day_number'] % 7) + 1, 7);
        }

        return [
            'can_claim' => true,
            'day_number' => $dayNum,
            'reward_coins' => $this->rewards[$dayNum - 1],
            'streak' => $dayNum,
            'already_claimed' => false,
            'rewards_schedule' => $this->rewards,
        ];
    }

    /**
     * Claim daily login reward
     */
    public function claim(int $userId): array
    {
        $status = $this->getStatus($userId);

        if (!$status['can_claim']) {
            return ['success' => false, 'error' => 'Already claimed today'];
        }

        $dayNum = $status['day_number'];
        $coins = $this->rewards[$dayNum - 1];
        $freeSpin = ($dayNum === 7);

        $this->db->transaction(function (DB $db) use ($userId, $dayNum, $coins) {
            // Record login reward
            $db->insert('login_rewards', [
                'user_id' => $userId,
                'day_number' => $dayNum,
                'coins_given' => $coins,
            ]);

            // Credit bonus coins
            $db->query(
                "UPDATE wallets SET bonus_coins = bonus_coins + ? WHERE user_id = ?",
                [$coins, $userId]
            );

            // Log transaction
            $db->insert('transactions', [
                'user_id' => $userId,
                'type' => 'bonus',
                'amount' => $coins,
                'balance_type' => 'bonus',
                'status' => 'completed',
                'note' => "Daily login reward - Day {$dayNum}",
            ]);

            // Update login streak on user
            $db->query(
                "UPDATE users SET login_streak = ?, last_login = NOW() WHERE id = ?",
                [$dayNum, $userId]
            );
        });

        return [
            'success' => true,
            'data' => [
                'coins_given' => $coins,
                'day_number' => $dayNum,
                'free_spin' => $freeSpin,
            ]
        ];
    }
}

<?php
/**
 * BetVibe - Quest Service
 * Manages daily quests: progress tracking, completion, and rewards
 */

namespace App\Services;

use App\Core\DB;

class QuestService
{
    private DB $db;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Get today's quests with user progress
     */
    public function getTodayQuests(int $userId): array
    {
        $quests = $this->db->all(
            "SELECT dq.*,
                    COALESCE(uqp.progress, 0) as progress,
                    COALESCE(uqp.is_complete, 0) as is_complete
             FROM daily_quests dq
             LEFT JOIN user_quest_progress uqp
                ON uqp.quest_id = dq.id AND uqp.user_id = ? AND uqp.date = CURDATE()
             WHERE dq.active_date = CURDATE()
             ORDER BY FIELD(dq.difficulty, 'easy', 'medium', 'hard')",
            [$userId]
        );

        return array_map(function ($q) {
            $condition = json_decode($q['condition'], true);
            return [
                'quest_id' => (int)$q['id'],
                'title' => $q['title'],
                'description' => $q['description'],
                'difficulty' => $q['difficulty'],
                'xp_reward' => (int)$q['xp_reward'],
                'coin_reward' => (int)$q['coin_reward'],
                'progress' => (int)$q['progress'],
                'total_needed' => (int)($condition['count'] ?? 1),
                'is_complete' => (bool)$q['is_complete'],
            ];
        }, $quests);
    }

    /**
     * Update quest progress after a bet result
     * Called from game controllers after every bet resolves
     */
    public function updateProgress(int $userId, array $betResult): void
    {
        $quests = $this->db->all(
            "SELECT dq.*, COALESCE(uqp.progress, 0) as progress, COALESCE(uqp.is_complete, 0) as done
             FROM daily_quests dq
             LEFT JOIN user_quest_progress uqp
                ON uqp.quest_id = dq.id AND uqp.user_id = ? AND uqp.date = CURDATE()
             WHERE dq.active_date = CURDATE()",
            [$userId]
        );

        foreach ($quests as $quest) {
            if ($quest['done']) {
                continue;
            }

            $condition = json_decode($quest['condition'], true);
            $increment = $this->evaluate($condition, $betResult);

            if ($increment > 0) {
                $newProgress = (int)$quest['progress'] + $increment;
                $totalNeeded = (int)($condition['count'] ?? 1);
                $isComplete = $newProgress >= $totalNeeded;

                $this->db->query(
                    "INSERT INTO user_quest_progress (user_id, quest_id, progress, is_complete, completed_at, date)
                     VALUES (?, ?, ?, ?, ?, CURDATE())
                     ON DUPLICATE KEY UPDATE progress = ?, is_complete = ?, completed_at = ?",
                    [
                        $userId,
                        $quest['id'],
                        $newProgress,
                        $isComplete ? 1 : 0,
                        $isComplete ? date('Y-m-d H:i:s') : null,
                        $newProgress,
                        $isComplete ? 1 : 0,
                        $isComplete ? date('Y-m-d H:i:s') : null,
                    ]
                );

                if ($isComplete) {
                    $this->grantReward($userId, $quest);
                }
            }
        }
    }

    /**
     * Evaluate whether a bet result matches a quest condition
     */
    private function evaluate(array $condition, array $betResult): int
    {
        $type = $condition['type'] ?? '';
        $game = $condition['game'] ?? 'any';
        $betGame = $betResult['game_slug'] ?? '';
        $betResultType = $betResult['result'] ?? '';

        // Game filter
        if ($game !== 'any' && $game !== $betGame) {
            return 0;
        }

        switch ($type) {
            case 'play_rounds':
                return 1; // Every bet counts as a round

            case 'win_bets':
                return $betResultType === 'win' ? 1 : 0;

            case 'try_game':
                return ($betGame === $game) ? 1 : 0;

            case 'win_amount':
                $payout = (float)($betResult['payout'] ?? 0);
                $betAmount = (float)($betResult['bet_amount'] ?? 0);
                $profit = $payout - $betAmount;
                return $profit > 0 ? (int)$profit : 0;

            case 'win_streak':
                $streak = (int)($betResult['streak'] ?? 0);
                $needed = (int)($condition['count'] ?? 3);
                return $streak >= $needed ? 1 : 0;

            case 'play_different_games':
                // This requires checking distinct games played today
                // Return 1 for any play; actual count checked at query time
                return 1;

            case 'win_multiplier':
                $multiplier = (float)($betResult['multiplier'] ?? 0);
                $minMultiplier = (float)($condition['min_multiplier'] ?? 2.0);
                return ($betResultType === 'win' && $multiplier >= $minMultiplier) ? 1 : 0;

            default:
                return 0;
        }
    }

    /**
     * Grant quest completion reward
     */
    private function grantReward(int $userId, array $quest): void
    {
        $xpReward = (int)$quest['xp_reward'];
        $coinReward = (int)$quest['coin_reward'];

        // Grant XP
        if ($xpReward > 0) {
            $this->db->query(
                "UPDATE users SET xp = xp + ? WHERE id = ?",
                [$xpReward, $userId]
            );
        }

        // Grant coins
        if ($coinReward > 0) {
            $this->db->query(
                "UPDATE wallets SET bonus_coins = bonus_coins + ? WHERE user_id = ?",
                [$coinReward, $userId]
            );

            $this->db->insert('transactions', [
                'user_id' => $userId,
                'type' => 'bonus',
                'amount' => $coinReward,
                'balance_type' => 'bonus',
                'status' => 'completed',
                'note' => 'Quest reward: ' . $quest['title'],
            ]);
        }

        // Notify via WebSocket
        try {
            WebSocketService::sendToUser($userId, [
                'type' => 'quest_complete',
                'quest_title' => $quest['title'],
                'xp_reward' => $xpReward,
                'coin_reward' => $coinReward,
            ]);
        } catch (\Throwable $e) {
            // Silent fail
        }
    }
}

<?php
/**
 * BetVibe - Streak Service
 * Tracks win/loss streaks and milestones
 */

namespace App\Services;

use App\Core\DB;

class StreakService
{
    private DB $db;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Update streak after a bet result
     *
     * @return array{streak: int, milestone: ?string}
     */
    public function updateStreak(int $userId, string $result): array
    {
        $streak = $this->db->first(
            "SELECT * FROM win_streaks WHERE user_id = ?",
            [$userId]
        );

        if (!$streak) {
            $this->db->query(
                "INSERT INTO win_streaks (user_id, current_streak, best_streak, last_result) VALUES (?, 0, 0, ?)",
                [$userId, $result]
            );
            $streak = ['current_streak' => 0, 'best_streak' => 0];
        }

        if ($result === 'win') {
            $newStreak = (int)$streak['current_streak'] + 1;
            $best = max($newStreak, (int)$streak['best_streak']);
        } else {
            $newStreak = 0;
            $best = (int)$streak['best_streak'];
        }

        $this->db->query(
            "UPDATE win_streaks SET current_streak = ?, best_streak = ?, last_result = ? WHERE user_id = ?",
            [$newStreak, $best, $result, $userId]
        );

        return [
            'streak' => $newStreak,
            'best_streak' => $best,
            'milestone' => $this->getMilestone($newStreak),
        ];
    }

    /**
     * Get streak milestone based on count
     */
    private function getMilestone(int $streak): ?string
    {
        return match (true) {
            $streak >= 10 => 'legendary',   // red border + auto share card
            $streak >= 5  => 'hot',         // orange border + live feed badge
            $streak >= 3  => 'fire',        // yellow border
            default       => null
        };
    }

    /**
     * Get current streak info for a user
     */
    public function getStreak(int $userId): array
    {
        $streak = $this->db->first(
            "SELECT * FROM win_streaks WHERE user_id = ?",
            [$userId]
        );

        if (!$streak) {
            return ['current_streak' => 0, 'best_streak' => 0, 'milestone' => null];
        }

        return [
            'current_streak' => (int)$streak['current_streak'],
            'best_streak' => (int)$streak['best_streak'],
            'milestone' => $this->getMilestone((int)$streak['current_streak']),
        ];
    }
}

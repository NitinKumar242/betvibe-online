<?php
/**
 * BetVibe - Leaderboard Service
 * Daily/weekly/alltime leaderboards with masked usernames
 */

namespace App\Services;

use App\Core\DB;

class LeaderboardService
{
    private DB $db;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Get leaderboard for a given period
     */
    public function getLeaderboard(string $period = 'daily'): array
    {
        $dateFilter = $this->getDateFilter($period);

        $results = $this->db->all(
            "SELECT u.id, u.username, u.avatar_id, u.level,
                    SUM(b.payout - b.bet_amount) as profit,
                    COUNT(CASE WHEN b.result = 'win' THEN 1 END) as wins,
                    COUNT(b.id) as total_bets,
                    MAX(b.multiplier) as best_multi
             FROM bets b
             JOIN users u ON u.id = b.user_id
             WHERE b.result IN ('win', 'loss')
               AND b.balance_type = 'real'
               {$dateFilter}
             GROUP BY b.user_id
             HAVING profit > 0
             ORDER BY profit DESC
             LIMIT 10"
        );

        return array_map(function ($row, $index) {
            return [
                'rank' => $index + 1,
                'username' => $this->maskUsername($row['username']),
                'avatar_id' => (int)$row['avatar_id'],
                'level' => (int)$row['level'],
                'profit' => round((float)$row['profit'], 2),
                'wins' => (int)$row['wins'],
                'total_bets' => (int)$row['total_bets'],
                'best_multiplier' => round((float)$row['best_multi'], 2),
            ];
        }, $results, array_keys($results));
    }

    /**
     * Get user's own rank for a period
     */
    public function getMyRank(int $userId, string $period = 'daily'): array
    {
        $dateFilter = $this->getDateFilter($period);

        // Get user's profit
        $userStats = $this->db->first(
            "SELECT SUM(b.payout - b.bet_amount) as profit,
                    COUNT(CASE WHEN b.result = 'win' THEN 1 END) as wins,
                    COUNT(b.id) as total_bets,
                    MAX(b.multiplier) as best_multi
             FROM bets b
             WHERE b.user_id = ?
               AND b.result IN ('win', 'loss')
               AND b.balance_type = 'real'
               {$dateFilter}",
            [$userId]
        );

        $userProfit = (float)($userStats['profit'] ?? 0);

        // Count players with higher profit
        $rankResult = $this->db->first(
            "SELECT COUNT(DISTINCT b.user_id) as higher_count
             FROM bets b
             WHERE b.result IN ('win', 'loss')
               AND b.balance_type = 'real'
               {$dateFilter}
             GROUP BY b.user_id
             HAVING SUM(b.payout - b.bet_amount) > ?",
            [$userProfit]
        );

        // Rank is count of higher + 1 (but only if user has bets)
        $betCount = (int)($userStats['total_bets'] ?? 0);
        if ($betCount === 0) {
            $rank = null;
        } else {
            // Count distinct users with higher profit
            $higherCount = $this->db->first(
                "SELECT COUNT(*) as cnt FROM (
                    SELECT b.user_id
                    FROM bets b
                    WHERE b.result IN ('win', 'loss') AND b.balance_type = 'real' {$dateFilter}
                    GROUP BY b.user_id
                    HAVING SUM(b.payout - b.bet_amount) > ?
                ) as higher",
                [$userProfit]
            );
            $rank = (int)($higherCount['cnt'] ?? 0) + 1;
        }

        $user = $this->db->first("SELECT username FROM users WHERE id = ?", [$userId]);

        return [
            'rank' => $rank,
            'username' => $user['username'] ?? 'Unknown',
            'profit' => round($userProfit, 2),
            'wins' => (int)($userStats['wins'] ?? 0),
            'total_bets' => $betCount,
            'best_multiplier' => round((float)($userStats['best_multi'] ?? 0), 2),
        ];
    }

    /**
     * Get SQL date filter for a period
     */
    private function getDateFilter(string $period): string
    {
        return match ($period) {
            'daily' => "AND b.created_at >= CURDATE()",
            'weekly' => "AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'alltime', 'all' => "",
            default => "AND b.created_at >= CURDATE()",
        };
    }

    /**
     * Mask username for privacy (show first 2 chars + ***)
     */
    private function maskUsername(string $username): string
    {
        if (strlen($username) <= 2) {
            return $username . '***';
        }
        return substr($username, 0, 2) . '***';
    }
}

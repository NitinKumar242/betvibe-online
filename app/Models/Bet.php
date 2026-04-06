<?php
/**
 * BetVibe - Bet Model
 * Represents a betting transaction
 */

namespace App\Models;

class Bet extends BaseModel
{
    protected string $table = 'bets';
    protected array $fillable = [
        'user_id',
        'game_slug',
        'round_id',
        'bet_amount',
        'balance_type',
        'bet_data',
        'result',
        'payout',
        'multiplier',
        'created_at',
        'resolved_at'
    ];

    /**
     * Get bets by user ID
     */
    public function getByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get bets by user ID and game slug
     */
    public function getByUserIdAndGame(int $userId, string $gameSlug, int $limit = 50): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND game_slug = :game_slug 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':game_slug', $gameSlug, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get bets by round ID
     */
    public function getByRoundId(int $roundId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE round_id = :round_id 
             ORDER BY created_at ASC"
        );
        $stmt->execute(['round_id' => $roundId]);

        return $stmt->fetchAll();
    }

    /**
     * Get bets by game slug
     */
    public function getByGameSlug(string $gameSlug, int $limit = 100): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE game_slug = :game_slug 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':game_slug', $gameSlug, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get pending bets
     */
    public function getPendingBets(int $limit = 100): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE result = 'pending' 
             ORDER BY created_at ASC 
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get pending bets by user
     */
    public function getPendingBetsByUser(int $userId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND result = 'pending' 
             ORDER BY created_at ASC"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Resolve a bet
     */
    public function resolveBet(int $betId, string $result, float $payout = 0.00, float $multiplier = 0.00): bool
    {
        return $this->update($betId, [
            'result' => $result,
            'payout' => $payout,
            'multiplier' => $multiplier,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get total wagered by user
     */
    public function getTotalWageredByUser(int $userId): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COALESCE(SUM(bet_amount), 0) as total 
             FROM {$this->table} 
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Get total wagered by user and game
     */
    public function getTotalWageredByUserAndGame(int $userId, string $gameSlug): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COALESCE(SUM(bet_amount), 0) as total 
             FROM {$this->table} 
             WHERE user_id = :user_id AND game_slug = :game_slug"
        );
        $stmt->execute([
            'user_id' => $userId,
            'game_slug' => $gameSlug
        ]);

        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Get total wins by user
     */
    public function getTotalWinsByUser(int $userId): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COALESCE(SUM(payout), 0) as total 
             FROM {$this->table} 
             WHERE user_id = :user_id AND result = 'win'"
        );
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Get win/loss ratio for user
     */
    public function getWinLossRatio(int $userId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT 
                COUNT(CASE WHEN result = 'win' THEN 1 END) as wins,
                COUNT(CASE WHEN result = 'loss' THEN 1 END) as losses,
                COUNT(CASE WHEN result = 'pending' THEN 1 END) as pending
             FROM {$this->table} 
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch();
        return $result ?: ['wins' => 0, 'losses' => 0, 'pending' => 0];
    }

    /**
     * Get recent bets for a game
     */
    public function getRecentBetsForGame(string $gameSlug, int $limit = 20): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT b.*, u.username 
             FROM {$this->table} b 
             JOIN users u ON b.user_id = u.id 
             WHERE b.game_slug = :game_slug AND b.result != 'pending'
             ORDER BY b.created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':game_slug', $gameSlug, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

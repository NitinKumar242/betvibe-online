<?php
/**
 * BetVibe - Session Model
 * Represents a user session
 */

namespace App\Models;

class Session extends BaseModel
{
    protected string $table = 'sessions';
    protected array $fillable = [
        'token',
        'user_id',
        'ip',
        'device_fp',
        'expires_at',
        'created_at'
    ];

    /**
     * Find session by token
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE token = :token LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find valid session by token (not expired)
     */
    public function findValidByToken(string $token): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE token = :token AND expires_at > NOW() 
             LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get sessions by user ID
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get active sessions by user ID
     */
    public function getActiveByUserId(int $userId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND expires_at > NOW() 
             ORDER BY created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new session
     */
    public function createSession(int $userId, string $token, string $ip = null, string $deviceFp = null, int $expiresInHours = 24): int|false
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInHours * 3600));

        return $this->create([
            'token' => $token,
            'user_id' => $userId,
            'ip' => $ip,
            'device_fp' => $deviceFp,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Extend session expiration
     */
    public function extendSession(string $token, int $hours = 24): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));

        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET expires_at = :expires_at 
             WHERE token = :token"
        );
        return $stmt->execute([
            'expires_at' => $expiresAt,
            'token' => $token
        ]);
    }

    /**
     * Invalidate a session (delete by token)
     */
    public function invalidate(string $token): bool
    {
        $stmt = $this->getConnection()->prepare(
            "DELETE FROM {$this->table} WHERE token = :token"
        );
        return $stmt->execute(['token' => $token]);
    }

    /**
     * Invalidate all sessions for a user
     */
    public function invalidateAllForUser(int $userId): bool
    {
        $stmt = $this->getConnection()->prepare(
            "DELETE FROM {$this->table} WHERE user_id = :user_id"
        );
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Invalidate all sessions for a user except one
     */
    public function invalidateAllExcept(int $userId, string $keepToken): bool
    {
        $stmt = $this->getConnection()->prepare(
            "DELETE FROM {$this->table} 
             WHERE user_id = :user_id AND token != :keep_token"
        );
        return $stmt->execute([
            'user_id' => $userId,
            'keep_token' => $keepToken
        ]);
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $stmt = $this->getConnection()->prepare(
            "DELETE FROM {$this->table} WHERE expires_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Count active sessions for a user
     */
    public function countActiveSessions(int $userId): int
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE user_id = :user_id AND expires_at > NOW()"
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Check if session is valid
     */
    public function isValid(string $token): bool
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE token = :token AND expires_at > NOW()"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result && $result['count'] > 0;
    }

    /**
     * Get session with user data
     */
    public function getSessionWithUser(string $token): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT s.*, u.username, u.email, u.avatar_id, u.level, u.xp, u.is_banned 
             FROM {$this->table} s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.token = :token AND s.expires_at > NOW() 
             LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}

<?php
/**
 * BetVibe - User Model
 * Represents a user in the system
 */

namespace App\Models;

class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = [
        'username',
        'email',
        'phone',
        'password_hash',
        'avatar_id',
        'xp',
        'level',
        'streak_count',
        'streak_date',
        'login_streak',
        'last_login',
        'last_ip',
        'device_fp',
        'failed_attempts',
        'last_failed_attempt',
        'is_banned',
        'ban_reason',
        'kyc_status',
        'referred_by',
        'ref_code',
        'created_at'
    ];

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE username = :username LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find user by phone
     */
    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE phone = :phone LIMIT 1"
        );
        $stmt->execute(['phone' => $phone]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find user by referral code
     */
    public function findByRefCode(string $refCode): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE ref_code = :ref_code LIMIT 1"
        );
        $stmt->execute(['ref_code' => $refCode]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Increment user XP
     */
    public function addXp(int $userId, int $xp): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} SET xp = xp + :xp WHERE id = :id"
        );
        return $stmt->execute(['xp' => $xp, 'id' => $userId]);
    }

    /**
     * Update user level based on XP
     */
    public function updateLevel(int $userId): bool
    {
        // Level formula: level = floor(sqrt(xp / 100)) + 1
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET level = FLOOR(SQRT(xp / 100)) + 1 
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $userId]);
    }

    /**
     * Update login streak
     */
    public function updateLoginStreak(int $userId): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET login_streak = login_streak + 1,
                 last_login = NOW()
             WHERE id = :id"
        );
        return $stmt->execute(['id' => $userId]);
    }

    /**
     * Ban user
     */
    public function ban(int $userId, string $reason): bool
    {
        return $this->update($userId, [
            'is_banned' => 1,
            'ban_reason' => $reason
        ]);
    }

    /**
     * Unban user
     */
    public function unban(int $userId): bool
    {
        return $this->update($userId, [
            'is_banned' => 0,
            'ban_reason' => null
        ]);
    }

    /**
     * Find user by email or phone
     */
    public function findByEmailOrPhone(string $identifier): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE email = :identifier OR phone = :identifier LIMIT 1"
        );
        $stmt->execute(['identifier' => $identifier]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Update login info (IP and device fingerprint)
     */
    public function updateLoginInfo(int $id, string $ip, string $deviceFp): void
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table}
             SET last_login = NOW(),
                 last_ip = :ip,
                 device_fp = :device_fp
             WHERE id = :id"
        );
        $stmt->execute([
            'ip' => $ip,
            'device_fp' => $deviceFp,
            'id' => $id
        ]);
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts(int $id): void
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table}
             SET failed_attempts = failed_attempts + 1,
                 last_failed_attempt = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts(int $id): void
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table}
             SET failed_attempts = 0,
                 last_failed_attempt = NULL
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Check if user is locked out (5+ failed attempts in last 15 minutes)
     */
    public function isLockedOut(int $id): bool
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT failed_attempts, last_failed_attempt
             FROM {$this->table}
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        if (!$result || $result['failed_attempts'] < 5) {
            return false;
        }

        // Check if last failed attempt was within 15 minutes
        if ($result['last_failed_attempt']) {
            $lastFailed = strtotime($result['last_failed_attempt']);
            $fifteenMinutesAgo = time() - (15 * 60);
            return $lastFailed > $fifteenMinutesAgo;
        }

        return false;
    }
}

<?php
/**
 * BetVibe - Referral Model
 * Represents a referral relationship between users
 */

namespace App\Models;

class Referral extends BaseModel
{
    protected string $table = 'referrals';
    protected array $fillable = [
        'referrer_id',
        'referred_id',
        'status',
        'bonus_paid',
        'converted_at',
        'created_at'
    ];

    /**
     * Find referral by referred user ID
     */
    public function findByReferredId(int $referredId): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE referred_id = :referred_id LIMIT 1"
        );
        $stmt->execute(['referred_id' => $referredId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get referrals by referrer ID
     */
    public function getByReferrerId(int $referrerId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE referrer_id = :referrer_id ORDER BY created_at DESC"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        return $stmt->fetchAll();
    }

    /**
     * Get pending referrals by referrer ID
     */
    public function getPendingByReferrerId(int $referrerId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE referrer_id = :referrer_id AND status = 'pending' 
             ORDER BY created_at DESC"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        return $stmt->fetchAll();
    }

    /**
     * Get converted referrals by referrer ID
     */
    public function getConvertedByReferrerId(int $referrerId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE referrer_id = :referrer_id AND status = 'converted' 
             ORDER BY converted_at DESC"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        return $stmt->fetchAll();
    }

    /**
     * Count referrals by referrer ID
     */
    public function countByReferrerId(int $referrerId): int
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE referrer_id = :referrer_id"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Count converted referrals by referrer ID
     */
    public function countConvertedByReferrerId(int $referrerId): int
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE referrer_id = :referrer_id AND status = 'converted'"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Get total bonus paid to referrer
     */
    public function getTotalBonusPaid(int $referrerId): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COALESCE(SUM(bonus_paid), 0) as total 
             FROM {$this->table} 
             WHERE referrer_id = :referrer_id"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Create a new referral
     */
    public function createReferral(int $referrerId, int $referredId): int|false
    {
        return $this->create([
            'referrer_id' => $referrerId,
            'referred_id' => $referredId,
            'status' => 'pending',
            'bonus_paid' => 0.00
        ]);
    }

    /**
     * Convert a referral (mark as converted and pay bonus)
     */
    public function convertReferral(int $referralId, float $bonusAmount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET status = 'converted',
                 bonus_paid = :bonus_paid,
                 converted_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute([
            'bonus_paid' => $bonusAmount,
            'id' => $referralId
        ]);
    }

    /**
     * Check if user has already been referred
     */
    public function isReferred(int $userId): bool
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE referred_id = :referred_id"
        );
        $stmt->execute(['referred_id' => $userId]);
        $result = $stmt->fetch();
        return $result && $result['count'] > 0;
    }

    /**
     * Get referral stats for a referrer
     */
    public function getReferrerStats(int $referrerId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT 
                COUNT(*) as total_referrals,
                COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_referrals,
                COALESCE(SUM(bonus_paid), 0) as total_bonus_earned
             FROM {$this->table} 
             WHERE referrer_id = :referrer_id"
        );
        $stmt->execute(['referrer_id' => $referrerId]);
        $result = $stmt->fetch();

        return [
            'total_referrals' => $result ? (int) $result['total_referrals'] : 0,
            'converted_referrals' => $result ? (int) $result['converted_referrals'] : 0,
            'total_bonus_earned' => $result ? (float) $result['total_bonus_earned'] : 0.00
        ];
    }
}

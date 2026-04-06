<?php
/**
 * BetVibe - Referral Service
 * Handles referral tracking, conversion on first deposit, and dashboard stats
 */

namespace App\Services;

use App\Core\DB;

class ReferralService
{
    private DB $db;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Check if this deposit triggers a referral conversion
     * Called from WatchPay webhook after every deposit
     */
    public function checkFirstDeposit(int $userId, float $depositAmount): void
    {
        // Min NPR 200 threshold
        if ($depositAmount < 200) {
            return;
        }

        // Check if this user has a pending referral
        $referral = $this->db->first(
            "SELECT * FROM referrals WHERE referred_id = ? AND status = 'pending'",
            [$userId]
        );

        if (!$referral) {
            return;
        }

        // Check daily cap for referrer (NPR 2000/day)
        $todayEarned = $this->db->first(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions
             WHERE user_id = ? AND type = 'referral_bonus' AND DATE(created_at) = CURDATE()",
            [$referral['referrer_id']]
        );

        if ((float)$todayEarned['total'] >= 2000) {
            return; // Daily cap hit
        }

        // Anti-abuse: Check same IP
        $referrer = $this->db->first(
            "SELECT last_ip FROM users WHERE id = ?",
            [$referral['referrer_id']]
        );
        $referred = $this->db->first(
            "SELECT last_ip FROM users WHERE id = ?",
            [$userId]
        );

        if ($referrer && $referred && $referrer['last_ip'] === $referred['last_ip']) {
            return; // Same IP = blocked silently
        }

        // Anti-abuse: Check same device fingerprint
        $referrerFp = $this->db->first(
            "SELECT device_fp FROM users WHERE id = ?",
            [$referral['referrer_id']]
        );
        $referredFp = $this->db->first(
            "SELECT device_fp FROM users WHERE id = ?",
            [$userId]
        );

        if (
            $referrerFp && $referredFp
            && $referrerFp['device_fp']
            && $referredFp['device_fp']
            && $referrerFp['device_fp'] === $referredFp['device_fp']
        ) {
            return; // Same device = blocked silently
        }

        // Calculate bonus (5% of first deposit, capped at remaining daily limit)
        $remainingCap = 2000 - (float)$todayEarned['total'];
        $bonus = min($depositAmount * 0.05, $remainingCap);

        if ($bonus <= 0) {
            return;
        }

        // Execute conversion in a transaction
        $this->db->transaction(function (DB $db) use ($referral, $userId, $bonus) {
            // Mark referral as converted
            $db->query(
                "UPDATE referrals SET status = 'converted', bonus_paid = ?, converted_at = NOW() WHERE id = ?",
                [$bonus, $referral['id']]
            );

            // Credit referrer real balance
            $db->query(
                "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
                [$bonus, $referral['referrer_id']]
            );

            // Credit referred user 50 bonus coins welcome gift
            $db->query(
                "UPDATE wallets SET bonus_coins = bonus_coins + 50 WHERE user_id = ?",
                [$userId]
            );

            // Log referrer bonus transaction
            $db->insert('transactions', [
                'user_id' => $referral['referrer_id'],
                'type' => 'referral_bonus',
                'amount' => $bonus,
                'balance_type' => 'real',
                'status' => 'completed',
                'note' => 'Referral deposit bonus'
            ]);

            // Log referred user bonus coins transaction
            $db->insert('transactions', [
                'user_id' => $userId,
                'type' => 'bonus',
                'amount' => 50,
                'balance_type' => 'bonus',
                'status' => 'completed',
                'note' => 'Welcome referral bonus coins'
            ]);
        });

        // Notify referrer via WebSocket (if service available)
        try {
            WebSocketService::sendToUser($referral['referrer_id'], [
                'type' => 'referral_converted',
                'amount' => $bonus,
                'message' => "Your referral deposited! You earned NPR {$bonus} bonus."
            ]);
        } catch (\Throwable $e) {
            // WebSocket not critical, silently ignore
        }
    }

    /**
     * Get referral dashboard data for a user
     */
    public function getDashboard(int $userId): array
    {
        // Get aggregate stats
        $stats = $this->db->first(
            "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted,
                COALESCE(SUM(bonus_paid), 0) as total_earned
             FROM referrals
             WHERE referrer_id = ?",
            [$userId]
        );

        // Get recent referrals with masked usernames
        $recentReferrals = $this->db->all(
            "SELECT r.*, u.username, r.created_at as referred_at
             FROM referrals r
             JOIN users u ON u.id = r.referred_id
             WHERE r.referrer_id = ?
             ORDER BY r.created_at DESC
             LIMIT 10",
            [$userId]
        );

        // Mask usernames for privacy
        $recent = array_map(function ($ref) {
            $username = $ref['username'];
            $masked = substr($username, 0, 2) . '***';
            return [
                'username' => $masked,
                'status' => $ref['status'],
                'bonus_paid' => (float)$ref['bonus_paid'],
                'referred_at' => $ref['referred_at'],
                'converted_at' => $ref['converted_at'] ?? null,
            ];
        }, $recentReferrals);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'pending' => (int)($stats['pending'] ?? 0),
            'converted' => (int)($stats['converted'] ?? 0),
            'total_earned' => (float)($stats['total_earned'] ?? 0),
            'recent_referrals' => $recent,
        ];
    }

    /**
     * Generate shareable referral link
     */
    public function generateShareLink(array $user): string
    {
        $appUrl = $_ENV['APP_URL'] ?? 'https://betsvibe.online';
        return $appUrl . '/r/' . $user['ref_code'];
    }

    /**
     * Generate WhatsApp share URL
     */
    public function generateWhatsAppLink(array $user): string
    {
        $link = $this->generateShareLink($user);
        $text = "🔥 BetVibe pe khel aur jeet! Sign up with my link and get 50 bonus coins! " . $link;
        return 'https://wa.me/?text=' . urlencode($text);
    }

    /**
     * Look up a referral code and return the referrer user ID
     */
    public function lookupRefCode(string $code): ?int
    {
        $user = $this->db->first(
            "SELECT id FROM users WHERE ref_code = ?",
            [$code]
        );

        return $user ? (int)$user['id'] : null;
    }
}

<?php
/**
 * BetVibe - Fraud Detection Service
 * Automated anti-fraud checks
 */

namespace App\Services;

use App\Core\DB;

class FraudDetectionService
{
    private DB $db;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
    }

    /**
     * Run all fraud checks for a user
     */
    public function runChecks(int $userId): void
    {
        $this->checkMultipleAccountsSameIP($userId);
        $this->checkDepositWithdrawNoWagering($userId);
    }

    /**
     * Check 1: Same IP used by multiple accounts
     */
    private function checkMultipleAccountsSameIP(int $userId): void
    {
        $user = $this->db->first("SELECT last_ip FROM users WHERE id = ?", [$userId]);
        if (!$user || !$user['last_ip']) return;

        $sameIpCount = $this->db->first(
            "SELECT COUNT(*) as c FROM users WHERE last_ip = ? AND id != ?",
            [$user['last_ip'], $userId]
        );

        if ((int)$sameIpCount['c'] >= 2) {
            $this->flag($userId, 'multiple_accounts_same_ip');
        }
    }

    /**
     * Check 2: Deposit then immediate withdraw without wagering
     */
    private function checkDepositWithdrawNoWagering(int $userId): void
    {
        $wallet = $this->db->first(
            "SELECT total_wagered, real_balance FROM wallets WHERE user_id = ?",
            [$userId]
        );
        if (!$wallet) return;

        $totalDeposits = $this->db->first(
            "SELECT COALESCE(SUM(amount), 0) as d FROM transactions
             WHERE user_id = ? AND type = 'deposit' AND status = 'completed'",
            [$userId]
        );

        $deposited = (float)$totalDeposits['d'];
        $wagered = (float)$wallet['total_wagered'];

        // If they deposited > 500 but wagered < 10% — suspicious
        if ($deposited > 500 && $wagered < $deposited * 0.1) {
            $this->flag($userId, 'deposit_withdraw_no_wagering');
        }
    }

    /**
     * Flag a user for fraud
     */
    private function flag(int $userId, string $reason): void
    {
        // Check if already flagged for same reason
        $existing = $this->db->first(
            "SELECT id FROM users WHERE id = ? AND fraud_flag = 1 AND fraud_reason = ?",
            [$userId, $reason]
        );

        if ($existing) return; // Already flagged

        $this->db->query(
            "UPDATE users SET fraud_flag = 1, fraud_reason = ? WHERE id = ?",
            [$reason, $userId]
        );

        // Send Telegram alert
        try {
            TelegramService::sendToAdminGroup(
                "🚨 Fraud Flag — User ID: {$userId} — {$reason}"
            );
        } catch (\Throwable $e) {
            // Silent fail
        }
    }
}

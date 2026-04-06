<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WageringRequirementException;
use App\Exceptions\WithdrawalPendingException;

/**
 * Wallet Service
 * Handles wallet operations and transactions
 */
class WalletService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get user balance
     * 
     * @param int $userId
     * @return array ['real' => float, 'bonus' => float]
     */
    public function getBalance(int $userId): array
    {
        $wallet = $this->db->query(
            "SELECT real_balance, bonus_coins FROM wallets WHERE user_id = ?",
            [$userId]
        )->first();

        if (!$wallet) {
            return ['real' => 0.00, 'bonus' => 0.00];
        }

        return [
            'real' => (float) $wallet->real_balance,
            'bonus' => (float) $wallet->bonus_coins
        ];
    }

    /**
     * Deduct bet amount from user's balance
     * Uses real balance first, then bonus
     * 
     * @param int $userId
     * @param float $amount
     * @return string 'real' or 'bonus'
     * @throws InsufficientBalanceException
     */
    public function deductBet(int $userId, float $amount): string
    {
        $wallet = $this->db->query(
            "SELECT * FROM wallets WHERE user_id = ? FOR UPDATE",
            [$userId]
        )->first();

        if (!$wallet) {
            throw new InsufficientBalanceException("Wallet not found");
        }

        if ($wallet->real_balance >= $amount) {
            $this->db->query(
                "UPDATE wallets SET real_balance = real_balance - ?, total_wagered = total_wagered + ? WHERE user_id = ?",
                [$amount, $amount, $userId]
            );
            return 'real';
        } elseif ($wallet->bonus_coins >= $amount) {
            $this->db->query(
                "UPDATE wallets SET bonus_coins = bonus_coins - ? WHERE user_id = ?",
                [$amount, $userId]
            );
            return 'bonus';
        } else {
            throw new InsufficientBalanceException("Insufficient balance");
        }
    }

    /**
     * Credit win amount to user's real balance
     * Wins always go to real balance regardless of balance type used for bet
     * 
     * @param int $userId
     * @param float $amount
     * @param string $balanceType 'real' or 'bonus'
     * @param int $betId
     * @return void
     */
    public function creditWin(int $userId, float $amount, string $balanceType, int $betId): void
    {
        // Credit to real balance
        $this->db->query(
            "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$amount, $userId]
        );

        // Update total_wagered if bet was placed with real balance
        if ($balanceType === 'real') {
            $this->db->query(
                "UPDATE wallets SET total_wagered = total_wagered + ? WHERE user_id = ?",
                [$amount, $userId]
            );
        }

        // Insert transaction record
        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, note) VALUES (?, 'win', ?, 'real', 'completed', ?)",
            [$userId, $amount, "Bet ID: {$betId}"]
        );
    }

    /**
     * Check if user has met wagering requirement
     * 
     * @param int $userId
     * @return bool
     */
    public function checkWageringRequirement(int $userId): bool
    {
        $result = $this->db->query(
            "SELECT 
                w.total_wagered,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND type = 'deposit') as total_deposited
            FROM wallets w
            WHERE w.user_id = ?",
            [$userId, $userId]
        )->first();

        if (!$result) {
            return false;
        }

        $totalWagered = (float) $result->total_wagered;
        $totalDeposited = (float) $result->total_deposited;

        return $totalWagered >= $totalDeposited;
    }

    /**
     * Check if user has a pending withdrawal
     * 
     * @param int $userId
     * @return bool
     */
    public function hasPendingWithdrawal(int $userId): bool
    {
        $pending = $this->db->query(
            "SELECT id FROM withdrawal_requests WHERE user_id = ? AND status = 'pending'",
            [$userId]
        )->first();

        return $pending !== null;
    }

    /**
     * Get total withdrawals today for a user
     * 
     * @param int $userId
     * @return float
     */
    public function getTodayWithdrawals(int $userId): float
    {
        $result = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM withdrawal_requests 
            WHERE user_id = ? AND DATE(requested_at) = CURDATE()",
            [$userId]
        )->first();

        return (float) $result->total;
    }

    /**
     * Get last deposit timestamp for a user
     * 
     * @param int $userId
     * @return string|null
     */
    public function getLastDepositTime(int $userId): ?string
    {
        $result = $this->db->query(
            "SELECT created_at FROM transactions 
            WHERE user_id = ? AND type = 'deposit' AND status = 'completed'
            ORDER BY created_at DESC LIMIT 1",
            [$userId]
        )->first();

        return $result ? $result->created_at : null;
    }

    /**
     * Credit real balance (for deposits, referral bonuses, etc.)
     * 
     * @param int $userId
     * @param float $amount
     * @param string $type
     * @param string|null $reference
     * @return void
     */
    public function creditRealBalance(int $userId, float $amount, string $type, ?string $reference = null): void
    {
        $this->db->query(
            "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$amount, $userId]
        );

        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id) 
            VALUES (?, ?, ?, 'real', 'completed', ?)",
            [$userId, $type, $amount, $reference]
        );
    }

    /**
     * Deduct from real balance (for withdrawals)
     * 
     * @param int $userId
     * @param float $amount
     * @return void
     */
    public function deductRealBalance(int $userId, float $amount): void
    {
        $this->db->query(
            "UPDATE wallets SET real_balance = real_balance - ? WHERE user_id = ?",
            [$amount, $userId]
        );
    }

    /**
     * Restore real balance (for failed withdrawals)
     * 
     * @param int $userId
     * @param float $amount
     * @return void
     */
    public function restoreRealBalance(int $userId, float $amount): void
    {
        $this->db->query(
            "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$amount, $userId]
        );
    }

    /**
     * Get user transactions with pagination
     * 
     * @param int $userId
     * @param int $page
     * @param int $perPage
     * @param string|null $type
     * @return array
     */
    public function getTransactions(int $userId, int $page = 1, int $perPage = 20, ?string $type = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [$userId];
        $typeFilter = '';

        if ($type) {
            $typeFilter = " AND type = ?";
            $params[] = $type;
        }

        $transactions = $this->db->query(
            "SELECT * FROM transactions 
            WHERE user_id = ?{$typeFilter}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        )->fetchAll();

        // Get total count
        $countParams = $params;
        $countResult = $this->db->query(
            "SELECT COUNT(*) as total FROM transactions 
            WHERE user_id = ?{$typeFilter}",
            $countParams
        )->first();

        return [
            'data' => $transactions,
            'total' => (int) $countResult->total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($countResult->total / $perPage)
        ];
    }
}

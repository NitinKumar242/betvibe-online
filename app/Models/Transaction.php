<?php
/**
 * BetVibe - Transaction Model
 * Represents a financial transaction
 */

namespace App\Models;

class Transaction extends BaseModel
{
    protected string $table = 'transactions';
    protected array $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_type',
        'status',
        'reference_id',
        'gateway',
        'note',
        'created_at'
    ];

    /**
     * Get transactions by user ID
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
     * Get transactions by user ID and type
     */
    public function getByUserIdAndType(int $userId, string $type, int $limit = 50): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND type = :type 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get transactions by status
     */
    public function getByStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = :status 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get transactions by reference ID
     */
    public function getByReferenceId(string $referenceId): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE reference_id = :reference_id 
             LIMIT 1"
        );
        $stmt->execute(['reference_id' => $referenceId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Update transaction status
     */
    public function updateStatus(int $transactionId, string $status): bool
    {
        return $this->update($transactionId, ['status' => $status]);
    }

    /**
     * Get total amount by type for a user
     */
    public function getTotalByType(int $userId, string $type): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM {$this->table} 
             WHERE user_id = :user_id AND type = :type AND status = 'completed'"
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type
        ]);

        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Get pending deposits
     */
    public function getPendingDeposits(): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE type = 'deposit' AND status = 'pending' 
             ORDER BY created_at ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get pending withdrawals
     */
    public function getPendingWithdrawals(): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} 
             WHERE type = 'withdraw' AND status = 'pending' 
             ORDER BY created_at ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Create a deposit transaction
     */
    public function createDeposit(int $userId, float $amount, string $referenceId, string $gateway = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_type' => 'real',
            'status' => 'pending',
            'reference_id' => $referenceId,
            'gateway' => $gateway
        ]);
    }

    /**
     * Create a withdrawal transaction
     */
    public function createWithdrawal(int $userId, float $amount, string $referenceId, string $gateway = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'withdraw',
            'amount' => $amount,
            'balance_type' => 'real',
            'status' => 'pending',
            'reference_id' => $referenceId,
            'gateway' => $gateway
        ]);
    }

    /**
     * Create a win transaction
     */
    public function createWin(int $userId, float $amount, string $balanceType = 'real', string $note = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'win',
            'amount' => $amount,
            'balance_type' => $balanceType,
            'status' => 'completed',
            'note' => $note
        ]);
    }

    /**
     * Create a loss transaction
     */
    public function createLoss(int $userId, float $amount, string $balanceType = 'real', string $note = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'loss',
            'amount' => $amount,
            'balance_type' => $balanceType,
            'status' => 'completed',
            'note' => $note
        ]);
    }

    /**
     * Create a bonus transaction
     */
    public function createBonus(int $userId, float $amount, string $note = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'bonus',
            'amount' => $amount,
            'balance_type' => 'bonus',
            'status' => 'completed',
            'note' => $note
        ]);
    }

    /**
     * Create a referral bonus transaction
     */
    public function createReferralBonus(int $userId, float $amount, string $note = null): int|false
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'referral_bonus',
            'amount' => $amount,
            'balance_type' => 'bonus',
            'status' => 'completed',
            'note' => $note
        ]);
    }
}

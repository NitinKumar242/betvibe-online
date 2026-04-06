<?php
/**
 * BetVibe - Wallet Model
 * Represents a user's wallet
 */

namespace App\Models;

class Wallet extends BaseModel
{
    protected string $table = 'wallets';
    protected array $fillable = [
        'user_id',
        'real_balance',
        'bonus_coins',
        'total_wagered',
        'updated_at'
    ];

    /**
     * Find wallet by user ID
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get or create wallet for user
     */
    public function getOrCreate(int $userId): array
    {
        $wallet = $this->findByUserId($userId);

        if ($wallet === null) {
            $walletId = $this->create([
                'user_id' => $userId,
                'real_balance' => 0.00,
                'bonus_coins' => 0.00,
                'total_wagered' => 0.00
            ]);
            $wallet = $this->find($walletId);
        }

        return $wallet;
    }

    /**
     * Add to real balance
     */
    public function addRealBalance(int $userId, float $amount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET real_balance = real_balance + :amount,
                 updated_at = NOW()
             WHERE user_id = :user_id"
        );
        return $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);
    }

    /**
     * Subtract from real balance
     */
    public function subtractRealBalance(int $userId, float $amount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET real_balance = real_balance - :amount,
                 updated_at = NOW()
             WHERE user_id = :user_id AND real_balance >= :amount"
        );
        return $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);
    }

    /**
     * Add to bonus coins
     */
    public function addBonusCoins(int $userId, float $amount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET bonus_coins = bonus_coins + :amount,
                 updated_at = NOW()
             WHERE user_id = :user_id"
        );
        return $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);
    }

    /**
     * Subtract from bonus coins
     */
    public function subtractBonusCoins(int $userId, float $amount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET bonus_coins = bonus_coins - :amount,
                 updated_at = NOW()
             WHERE user_id = :user_id AND bonus_coins >= :amount"
        );
        return $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);
    }

    /**
     * Add to total wagered
     */
    public function addWagered(int $userId, float $amount): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET total_wagered = total_wagered + :amount,
                 updated_at = NOW()
             WHERE user_id = :user_id"
        );
        return $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);
    }

    /**
     * Check if user has sufficient balance
     */
    public function hasSufficientBalance(int $userId, float $amount, string $balanceType = 'real'): bool
    {
        $column = $balanceType === 'bonus' ? 'bonus_coins' : 'real_balance';

        $stmt = $this->getConnection()->prepare(
            "SELECT {$column} >= :amount as sufficient FROM {$this->table} WHERE user_id = :user_id"
        );
        $stmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);

        $result = $stmt->fetch();
        return $result && $result['sufficient'];
    }

    /**
     * Get total balance (real + bonus)
     */
    public function getTotalBalance(int $userId): float
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT (real_balance + bonus_coins) as total FROM {$this->table} WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch();
        return $result ? (float) $result['total'] : 0.00;
    }

    /**
     * Get balance array [real, bonus]
     */
    public function getBalance(int $userId): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT real_balance, bonus_coins FROM {$this->table} WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch();
        if ($result) {
            return [
                'real' => (float) $result['real_balance'],
                'bonus' => (float) $result['bonus_coins']
            ];
        }
        return ['real' => 0.00, 'bonus' => 0.00];
    }

    /**
     * Deduct bet amount from wallet
     * Returns 'real' or 'bonus' indicating which balance was used
     */
    public function deductBet(int $userId, float $amount): string
    {
        $wallet = $this->findByUserId($userId);

        if (!$wallet) {
            throw new \RuntimeException("Wallet not found for user ID: {$userId}");
        }

        // Use real balance first, then bonus
        if ($wallet['real_balance'] >= $amount) {
            $this->subtractRealBalance($userId, $amount);
            return 'real';
        } elseif ($wallet['bonus_coins'] >= $amount) {
            $this->subtractBonusCoins($userId, $amount);
            return 'bonus';
        }

        throw new \RuntimeException("Insufficient balance for user ID: {$userId}");
    }

    /**
     * Credit amount to wallet
     */
    public function creditAmount(int $userId, float $amount, string $type): void
    {
        if ($type === 'real') {
            $this->addRealBalance($userId, $amount);
        } elseif ($type === 'bonus') {
            $this->addBonusCoins($userId, $amount);
        } else {
            throw new \InvalidArgumentException("Invalid balance type: {$type}. Must be 'real' or 'bonus'");
        }
    }

    /**
     * Check if wagering requirement is met
     * Returns true if total_wagered >= bonus_coins * 3 (3x wagering requirement)
     */
    public function checkWageringRequirement(int $userId): bool
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT total_wagered, bonus_coins
             FROM {$this->table}
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        if (!$result || $result['bonus_coins'] <= 0) {
            return true; // No bonus to wager
        }

        $wageringRequirement = $result['bonus_coins'] * 3;
        return $result['total_wagered'] >= $wageringRequirement;
    }
}

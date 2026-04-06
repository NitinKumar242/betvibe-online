<?php

namespace App\Services;

use App\Core\DB;
use App\Games\GameFactory;
use App\Models\GameConfig;
use App\Models\User;
use App\Exceptions\InsufficientBalanceException;

/**
 * Bet Service
 * Central bet processing class - all games go through it
 */
class BetService
{
    private $db;
    private $walletService;
    private $streakService;
    private $questService;
    private $xpService;
    private $winFeedService;
    private $winCardService;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->walletService = new WalletService($this->db);
        // These services will be created in Phase 6+
        // $this->streakService = new StreakService();
        // $this->questService = new QuestService();
        // $this->xpService = new XPService();
        // $this->winFeedService = new WinFeedService();
        // $this->winCardService = new WinCardService();
    }

    /**
     * Place a bet and process the game result
     * 
     * @param int $userId User ID
     * @param string $gameSlug Game slug
     * @param float $amount Bet amount
     * @param array $betData Game-specific bet data
     * @return array Full result object
     * @throws \Exception
     */
    public function placeBet(int $userId, string $gameSlug, float $amount, array $betData): array
    {
        // Step 1: Load game config
        $gameConfigModel = new GameConfig();
        $gameConfig = $gameConfigModel->getBySlug($gameSlug);

        if (!$gameConfig) {
            throw new \Exception("Game not found");
        }

        if (!$gameConfig['is_enabled']) {
            throw new \Exception("Game is disabled");
        }

        // Step 2: Validate amount within limits
        $minBet = (float) $gameConfig['min_bet'];
        $maxBet = (float) $gameConfig['max_bet'];

        if ($amount < $minBet) {
            throw new \Exception("Minimum bet is {$minBet}");
        }

        if ($amount > $maxBet) {
            throw new \Exception("Maximum bet is {$maxBet}");
        }

        // Step 3: Load user, check not banned
        $userModel = new User();
        $user = $userModel->find($userId);

        if (!$user) {
            throw new \Exception("User not found");
        }

        if ($user['is_banned']) {
            throw new \Exception("User is banned");
        }

        // Step 4: Deduct bet from wallet
        try {
            $balanceType = $this->walletService->deductBet($userId, $amount);
        } catch (InsufficientBalanceException $e) {
            throw new \Exception("Insufficient balance");
        }

        // Step 5: INSERT bets row
        $this->db->query(
            "INSERT INTO bets (user_id, game_slug, bet_amount, balance_type, bet_data, result, created_at) 
             VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            [$userId, $gameSlug, $amount, $balanceType, json_encode($betData)]
        );

        $betId = $this->db->lastInsertId();

        // Step 6: Call the specific game handler
        try {
            $game = GameFactory::resolve($gameSlug);
            $result = $game->play($betData, $amount);
        } catch (\Exception $e) {
            // If game fails, refund the bet
            $this->walletService->creditWin($userId, $amount, $balanceType, $betId);
            $this->db->query(
                "UPDATE bets SET result = 'loss', payout = ?, multiplier = 0.00, resolved_at = NOW() WHERE id = ?",
                [$amount, $betId]
            );
            throw new \Exception("Game error: " . $e->getMessage());
        }

        // Step 7: Get result
        $gameResult = $result['result']; // 'win' or 'loss'
        $multiplier = $result['multiplier'];
        $payout = $result['payout'];
        $gameData = $result['game_data'];

        // Step 8: If win, credit the win
        if ($gameResult === 'win' && $payout > 0) {
            $this->walletService->creditWin($userId, $payout, $balanceType, $betId);
        }

        // Step 9: UPDATE bets
        $this->db->query(
            "UPDATE bets SET result = ?, payout = ?, multiplier = ?, resolved_at = NOW() WHERE id = ?",
            [$gameResult, $payout, $multiplier, $betId]
        );

        // Step 10: INSERT transaction
        $transactionType = ($gameResult === 'win') ? 'win' : 'loss';
        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id, created_at) 
             VALUES (?, ?, ?, ?, 'completed', ?, NOW())",
            [$userId, $transactionType, $payout, $balanceType, "bet_{$betId}"]
        );

        // Step 11: Call StreakService (will be implemented in Phase 6+)
        // if ($this->streakService) {
        //     $this->streakService->updateStreak($userId, $gameResult);
        // }

        // Step 12: Call QuestService (will be implemented in Phase 6+)
        // if ($this->questService) {
        //     $this->questService->updateProgress($userId, [
        //         'result' => $gameResult,
        //         'amount' => $amount,
        //         'game_slug' => $gameSlug
        //     ]);
        // }

        // Step 13: Call XPService (will be implemented in Phase 6+)
        // if ($this->xpService) {
        //     $xpResult = $this->xpService->award($userId, $amount, $gameResult, $multiplier);
        // }

        // Step 14: If win, broadcast and check for win card (will be implemented in Phase 6+)
        // if ($gameResult === 'win') {
        //     if ($this->winFeedService) {
        //         $this->winFeedService->broadcast($userId, $gameSlug, $payout);
        //     }
        //     if ($this->winCardService) {
        //         $this->winCardService->shouldGenerate($userId, $payout);
        //     }
        // }

        // Get updated balance
        $newBalance = $this->walletService->getBalance($userId);

        // Step 15: Return full result object
        return [
            'bet_id' => $betId,
            'result' => $gameResult,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'game_data' => $gameData,
            'new_balance' => $newBalance,
            'balance_type' => $balanceType,
            'xp_gained' => 0, // Will be populated when XPService is implemented
            'level_up' => false // Will be populated when XPService is implemented
        ];
    }

    /**
     * Get bet history for a user on a specific game
     * 
     * @param int $userId User ID
     * @param string $gameSlug Game slug
     * @param int $limit Number of bets to return
     * @return array Array of bets
     */
    public function getHistory(int $userId, string $gameSlug, int $limit = 20): array
    {
        $bets = $this->db->query(
            "SELECT id, result, bet_amount, payout, multiplier, created_at, game_data 
             FROM bets 
             WHERE user_id = ? AND game_slug = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$userId, $gameSlug, $limit]
        )->fetchAll();

        return array_map(function ($bet) {
            return [
                'id' => $bet['id'],
                'result' => $bet['result'],
                'amount' => (float) $bet['bet_amount'],
                'payout' => (float) $bet['payout'],
                'multiplier' => (float) $bet['multiplier'],
                'created_at' => $bet['created_at'],
                'game_data' => json_decode($bet['game_data'], true)
            ];
        }, $bets);
    }

    /**
     * Get bet by ID
     * 
     * @param int $betId Bet ID
     * @return array|null Bet data or null if not found
     */
    public function getBet(int $betId): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM bets WHERE id = ?",
            [$betId]
        );
        $bet = $stmt->fetch();

        if (!$bet) {
            return null;
        }

        return [
            'id' => $bet['id'],
            'user_id' => $bet['user_id'],
            'game_slug' => $bet['game_slug'],
            'bet_amount' => (float) $bet['bet_amount'],
            'balance_type' => $bet['balance_type'],
            'bet_data' => json_decode($bet['bet_data'], true),
            'result' => $bet['result'],
            'payout' => (float) $bet['payout'],
            'multiplier' => (float) $bet['multiplier'],
            'created_at' => $bet['created_at'],
            'resolved_at' => $bet['resolved_at']
        ];
    }
}

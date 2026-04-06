<?php

namespace WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Games\CrashGame;
use App\Services\RNGService;

/**
 * Crash Game WebSocket Handler
 * Manages real-time crash game with multiplier ticks and cashouts
 */
class CrashWebSocketHandler implements MessageComponentInterface
{
    private $clients;
    private $activeBets = [];
    private $currentMultiplier = 1.00;
    private $crashPoint = 0;
    private $gameState = 'waiting'; // waiting, in-progress, crashed
    private $roundId = 0;
    private $tickInterval = null;
    private $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->db = \App\Core\DB::getInstance();
    }

    /**
     * Handle new connection
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        // Send current game state to new client
        $this->sendGameState($conn);

        echo "New connection! (" . spl_object_hash($conn) . ")\n";
    }

    /**
     * Handle incoming messages
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['action'])) {
            return;
        }

        switch ($data['action']) {
            case 'bet':
                $this->handleBet($from, $data);
                break;

            case 'cashout':
                $this->handleCashout($from, $data);
                break;

            case 'ping':
                // Keep connection alive
                $from->send(json_encode(['action' => 'pong']));
                break;
        }
    }

    /**
     * Handle connection close
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection " . spl_object_hash($conn) . " has disconnected\n";
    }

    /**
     * Handle errors
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Handle bet placement
     */
    private function handleBet(ConnectionInterface $conn, array $data)
    {
        if ($this->gameState !== 'waiting') {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Betting is closed'
            ]));
            return;
        }

        $userId = $data['user_id'] ?? null;
        $amount = (float) ($data['amount'] ?? 0);
        $autoCashout = isset($data['auto_cashout']) ? (float) $data['auto_cashout'] : null;

        if (!$userId || $amount <= 0) {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Invalid bet'
            ]));
            return;
        }

        // Store bet
        $betId = uniqid('bet_');
        $this->activeBets[$betId] = [
            'user_id' => $userId,
            'amount' => $amount,
            'auto_cashout' => $autoCashout,
            'connection' => $conn,
            'cashout_multiplier' => null,
            'cashout_time' => null
        ];

        $conn->send(json_encode([
            'action' => 'bet_accepted',
            'bet_id' => $betId,
            'amount' => $amount,
            'auto_cashout' => $autoCashout
        ]));

        // Broadcast bet to all clients
        $this->broadcast([
            'action' => 'new_bet',
            'bet_id' => $betId,
            'amount' => $amount
        ]);
    }

    /**
     * Handle cashout request
     */
    private function handleCashout(ConnectionInterface $conn, array $data)
    {
        if ($this->gameState !== 'in-progress') {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Game is not in progress'
            ]));
            return;
        }

        $betId = $data['bet_id'] ?? null;

        if (!$betId || !isset($this->activeBets[$betId])) {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Bet not found'
            ]));
            return;
        }

        $bet = $this->activeBets[$betId];

        // Check if already cashed out
        if ($bet['cashout_multiplier'] !== null) {
            $conn->send(json_encode([
                'action' => 'error',
                'message' => 'Already cashed out'
            ]));
            return;
        }

        // Process cashout
        $cashoutMultiplier = $this->currentMultiplier;
        $payout = CrashGame::calculatePayout($bet['amount'], $cashoutMultiplier);

        $this->activeBets[$betId]['cashout_multiplier'] = $cashoutMultiplier;
        $this->activeBets[$betId]['cashout_time'] = microtime(true);

        // Credit win to user
        $this->creditWin($bet['user_id'], $payout, $betId);

        $conn->send(json_encode([
            'action' => 'cashout_accepted',
            'bet_id' => $betId,
            'multiplier' => $cashoutMultiplier,
            'payout' => $payout
        ]));

        // Broadcast cashout
        $this->broadcast([
            'action' => 'player_cashout',
            'bet_id' => $betId,
            'multiplier' => $cashoutMultiplier,
            'payout' => $payout
        ]);
    }

    /**
     * Start a new round
     */
    public function startRound()
    {
        $this->roundId++;
        $this->gameState = 'in-progress';
        $this->currentMultiplier = 1.00;
        $this->crashPoint = CrashGame::generateCrashPoint();

        echo "Round {$this->roundId} started. Crash point: {$this->crashPoint}\n";

        $this->broadcast([
            'action' => 'round_start',
            'round_id' => $this->roundId,
            'crash_point' => $this->crashPoint // Hidden from clients, used for verification
        ]);

        // Start multiplier ticks
        $this->startMultiplierTicks();
    }

    /**
     * Start multiplier ticks
     */
    private function startMultiplierTicks()
    {
        $startTime = microtime(true);
        $this->tickInterval = function () use ($startTime) {
            $elapsed = microtime(true) - $startTime;

            // Calculate multiplier based on elapsed time (exponential growth)
            $this->currentMultiplier = min(
                round(1.00 + pow($elapsed / 1000, 1.5), 2),
                $this->crashPoint
            );

            // Check if crashed
            if ($this->currentMultiplier >= $this->crashPoint) {
                $this->crash();
                return;
            }

            // Check auto cashouts
            $this->checkAutoCashouts();

            // Broadcast tick
            $this->broadcast([
                'action' => 'tick',
                'multiplier' => $this->currentMultiplier,
                'elapsed' => round($elapsed * 1000)
            ]);
        };

        // Simulate ticks (in production, use a proper timer)
        $this->simulateTicks();
    }

    /**
     * Simulate ticks (for development)
     */
    private function simulateTicks()
    {
        $tickCount = 0;
        $maxTicks = 1000; // Safety limit

        while ($this->gameState === 'in-progress' && $tickCount < $maxTicks) {
            usleep(100000); // 100ms
            call_user_func($this->tickInterval);
            $tickCount++;
        }
    }

    /**
     * Check auto cashouts
     */
    private function checkAutoCashouts()
    {
        foreach ($this->activeBets as $betId => $bet) {
            if ($bet['cashout_multiplier'] !== null) {
                continue; // Already cashed out
            }

            if ($bet['auto_cashout'] !== null && $this->currentMultiplier >= $bet['auto_cashout']) {
                // Auto cashout
                $cashoutMultiplier = $bet['auto_cashout'];
                $payout = CrashGame::calculatePayout($bet['amount'], $cashoutMultiplier);

                $this->activeBets[$betId]['cashout_multiplier'] = $cashoutMultiplier;
                $this->activeBets[$betId]['cashout_time'] = microtime(true);

                $this->creditWin($bet['user_id'], $payout, $betId);

                $bet['connection']->send(json_encode([
                    'action' => 'auto_cashout',
                    'bet_id' => $betId,
                    'multiplier' => $cashoutMultiplier,
                    'payout' => $payout
                ]));

                $this->broadcast([
                    'action' => 'player_cashout',
                    'bet_id' => $betId,
                    'multiplier' => $cashoutMultiplier,
                    'payout' => $payout,
                    'auto' => true
                ]);
            }
        }
    }

    /**
     * Crash game
     */
    private function crash()
    {
        $this->gameState = 'crashed';
        $this->currentMultiplier = $this->crashPoint;

        echo "Round {$this->roundId} crashed at {$this->crashPoint}x\n";

        // Process losses for uncashed bets
        foreach ($this->activeBets as $betId => $bet) {
            if ($bet['cashout_multiplier'] === null) {
                // Player lost
                $this->recordLoss($bet['user_id'], $bet['amount'], $betId);
            }
        }

        $this->broadcast([
            'action' => 'round_end',
            'round_id' => $this->roundId,
            'crash_point' => $this->crashPoint
        ]);

        // Clear bets
        $this->activeBets = [];

        // Start waiting phase
        $this->gameState = 'waiting';

        // Start new round after 5 seconds
        $this->scheduleNextRound();
    }

    /**
     * Schedule next round
     */
    private function scheduleNextRound()
    {
        echo "Waiting 5 seconds before next round...\n";

        // In production, use a proper timer
        sleep(5);
        $this->startRound();
    }

    /**
     * Credit win to user
     */
    private function creditWin(int $userId, float $amount, string $betId)
    {
        $this->db->query(
            "UPDATE wallets SET balance = balance + ? WHERE user_id = ?",
            [$amount, $userId]
        );

        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id, created_at) 
             VALUES (?, 'win', ?, 'deposit', 'completed', ?, NOW())",
            [$userId, $amount, "crash_{$betId}"]
        );
    }

    /**
     * Record loss
     */
    private function recordLoss(int $userId, float $amount, string $betId)
    {
        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id, created_at) 
             VALUES (?, 'loss', ?, 'deposit', 'completed', ?, NOW())",
            [$userId, $amount, "crash_{$betId}"]
        );
    }

    /**
     * Send game state to a specific client
     */
    private function sendGameState(ConnectionInterface $conn)
    {
        $conn->send(json_encode([
            'action' => 'game_state',
            'state' => $this->gameState,
            'current_multiplier' => $this->currentMultiplier,
            'round_id' => $this->roundId
        ]));
    }

    /**
     * Broadcast message to all clients
     */
    private function broadcast(array $message)
    {
        $data = json_encode($message);

        foreach ($this->clients as $client) {
            $client->send($data);
        }
    }
}

<?php

namespace App\Controllers;

use App\Core\DB;
use App\Services\BetService;
use App\Services\SessionService;
use App\Models\GameConfig;
use App\Games\GameFactory;
use App\Middleware\AuthMiddleware;

/**
 * Game Controller
 * Handles game requests and betting operations
 */
class GameController
{
    private $betService;
    private $sessionService;
    private $db;

    public function __construct()
    {
        $this->betService = new BetService();
        $this->sessionService = new SessionService();
        $this->db = DB::getInstance();
    }

    /**
     * Display game index page
     */
    public function index()
    {
        // Display game listing page
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Games</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            margin: 0;
            padding: 2rem;
        }
        h1 {
            text-align: center;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .games {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 2rem auto;
        }
        .game-card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s;
        }
        .game-card:hover {
            transform: scale(1.05);
        }
        a {
            color: #fff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <h1>BetVibe Games</h1>
    <div class="games">
        <div class="game-card">
            <h2>Crash</h2>
            <p>Watch the multiplier grow and cash out before it crashes!</p>
            <a href="/game/crash">Play Now</a>
        </div>
        <div class="game-card">
            <h2>Mines</h2>
            <p>Find the safe spots and avoid the mines!</p>
            <a href="/game/mines">Play Now</a>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Play a specific game (HTML view)
     */
    public function play($gameId = null)
    {
        $gameId = $gameId ?? 'crash';
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - ' . htmlspecialchars($gameId) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            margin: 0;
            padding: 2rem;
            text-align: center;
        }
        h1 {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .game-container {
            max-width: 800px;
            margin: 2rem auto;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 2rem;
        }
        a {
            color: #feca57;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <h1>BetVibe - ' . htmlspecialchars($gameId) . '</h1>
    <div class="game-container">
        <p>Game interface for ' . htmlspecialchars($gameId) . '</p>
        <p><a href="/games">← Back to Games</a></p>
    </div>
</body>
</html>';
    }

    /**
     * API: Get all enabled games (public)
     * GET /api/games
     */
    public function getGames()
    {
        header('Content-Type: application/json');

        $gameConfigModel = new GameConfig();
        $games = $gameConfigModel->getEnabledGames();

        $response = array_map(function ($game) {
            return [
                'slug' => $game['game_slug'],
                'display_name' => $game['display_name'],
                'min_bet' => (float) $game['min_bet'],
                'max_bet' => (float) $game['max_bet'],
                'round_duration' => (int) $game['round_duration']
            ];
        }, $games);

        echo json_encode([
            'success' => true,
            'games' => $response
        ]);
    }

    /**
     * API: Play an instant game
     * POST /api/games/{slug}/play
     */
    public function apiPlay($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Validate game exists and is enabled
        $gameConfigModel = new GameConfig();
        $gameConfig = $gameConfigModel->getBySlug($slug);

        if (!$gameConfig) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            return;
        }

        if (!$gameConfig['is_enabled']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Game is disabled']);
            return;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            return;
        }

        $amount = (float) ($input['bet_amount'] ?? 0);
        $betData = $input['bet_data'] ?? [];

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid bet amount']);
            return;
        }

        try {
            $result = $this->betService->placeBet($userId, $slug, $amount, $betData);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Start a multi-step game (mines, tower, hilo)
     * POST /api/games/{slug}/start
     */
    public function apiStart($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Validate game exists and is enabled
        $gameConfigModel = new GameConfig();
        $gameConfig = $gameConfigModel->getBySlug($slug);

        if (!$gameConfig || !$gameConfig['is_enabled']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Game not found or disabled']);
            return;
        }

        // Check if user already has an active session for this game
        $stmt = $this->db->query(
            "SELECT * FROM active_game_sessions WHERE user_id = ? AND game_slug = ?",
            [$userId, $slug]
        );
        $existingSession = $stmt->fetch();

        if ($existingSession) {
            // Return existing session if still valid
            if (strtotime($existingSession['expires_at']) > time()) {
                echo json_encode([
                    'success' => true,
                    'session_id' => $existingSession['id'],
                    'state' => json_decode($existingSession['state'], true)
                ]);
                return;
            } else {
                // Delete expired session
                $this->db->query(
                    "DELETE FROM active_game_sessions WHERE id = ?",
                    [$existingSession['id']]
                );
            }
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            return;
        }

        $amount = (float) ($input['bet_amount'] ?? 0);
        $betData = $input['bet_data'] ?? [];

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid bet amount']);
            return;
        }

        try {
            // Place bet
            $betResult = $this->betService->placeBet($userId, $slug, $amount, $betData);
            $betId = $betResult['bet_id'];

            // Create game session
            $game = GameFactory::resolve($slug);
            $initialState = $game->play($betData, $amount);

            // Store session
            $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
            $this->db->query(
                "INSERT INTO active_game_sessions (user_id, game_slug, bet_id, state, expires_at, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$userId, $slug, $betId, json_encode($initialState), $expiresAt]
            );

            $sessionId = $this->db->lastInsertId();

            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'bet_id' => $betId,
                'state' => $initialState,
                'expires_at' => $expiresAt
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Perform action in multi-step game (reveal tile, pick direction, climb floor)
     * POST /api/games/{slug}/action
     */
    public function apiAction($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            return;
        }

        $sessionId = (int) ($input['session_id'] ?? 0);
        $action = $input['action'] ?? '';

        if (!$sessionId || !$action) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing session_id or action']);
            return;
        }

        // Get session
        $stmt = $this->db->query(
            "SELECT * FROM active_game_sessions WHERE id = ? AND user_id = ? AND game_slug = ?",
            [$sessionId, $userId, $slug]
        );
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Session not found']);
            return;
        }

        // Check if session expired
        if (strtotime($session['expires_at']) <= time()) {
            $this->db->query("DELETE FROM active_game_sessions WHERE id = ?", [$sessionId]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Session expired']);
            return;
        }

        try {
            $game = GameFactory::resolve($slug);
            $currentState = json_decode($session['state'], true);

            // Process action (this will be implemented in individual game classes)
            // For now, return current state
            $newState = $currentState;

            // Update session state
            $this->db->query(
                "UPDATE active_game_sessions SET state = ? WHERE id = ?",
                [json_encode($newState), $sessionId]
            );

            echo json_encode([
                'success' => true,
                'state' => $newState
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Cash out from multi-step game
     * POST /api/games/{slug}/cashout
     */
    public function apiCashout($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            return;
        }

        $sessionId = (int) ($input['session_id'] ?? 0);

        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing session_id']);
            return;
        }

        // Get session
        $stmt = $this->db->query(
            "SELECT * FROM active_game_sessions WHERE id = ? AND user_id = ? AND game_slug = ?",
            [$sessionId, $userId, $slug]
        );
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Session not found']);
            return;
        }

        try {
            $currentState = json_decode($session['state'], true);
            $payout = $currentState['payout'] ?? 0;
            $multiplier = $currentState['multiplier'] ?? 1.0;

            // Update bet as win
            $this->db->query(
                "UPDATE bets SET result = 'win', payout = ?, multiplier = ?, resolved_at = NOW() WHERE id = ?",
                [$payout, $multiplier, $session['bet_id']]
            );

            // Delete session
            $this->db->query("DELETE FROM active_game_sessions WHERE id = ?", [$sessionId]);

            echo json_encode([
                'success' => true,
                'payout' => $payout,
                'multiplier' => $multiplier
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get bet history for a user on a specific game
     * GET /api/games/{slug}/history
     */
    public function apiHistory($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            $history = $this->betService->getHistory($userId, $slug, 20);
            echo json_encode(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Place bet on timer-based game round (color-predict, fast-parity)
     * POST /api/games/{slug}/bet
     */
    public function apiBet($slug)
    {
        header('Content-Type: application/json');

        // Check authentication
        $authMiddleware = new AuthMiddleware();
        $userId = $authMiddleware->getUserId();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Validate game exists and is enabled
        $gameConfigModel = new GameConfig();
        $gameConfig = $gameConfigModel->getBySlug($slug);

        if (!$gameConfig || !$gameConfig['is_enabled']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Game not found or disabled']);
            return;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            return;
        }

        $roundId = (int) ($input['round_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $betData = $input['bet_data'] ?? [];

        if (!$roundId || $amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid round_id or amount']);
            return;
        }

        // Get round and validate it's in betting phase
        $stmt = $this->db->query(
            "SELECT * FROM game_rounds WHERE id = ? AND game_slug = ?",
            [$roundId, $slug]
        );
        $round = $stmt->fetch();

        if (!$round) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Round not found']);
            return;
        }

        if ($round['status'] !== 'betting') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Round is not in betting phase']);
            return;
        }

        try {
            // Place bet (will be resolved when round ends)
            $result = $this->betService->placeBet($userId, $slug, $amount, $betData);

            // Update bet with round_id
            $this->db->query(
                "UPDATE bets SET round_id = ?, result = 'pending' WHERE id = ?",
                [$roundId, $result['bet_id']]
            );

            echo json_encode([
                'success' => true,
                'bet_id' => $result['bet_id'],
                'round_id' => $roundId,
                'round_ends_at' => $round['ends_at'],
                'new_balance' => $result['new_balance']
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get current round for timer-based game
     * GET /api/games/{slug}/current-round
     */
    public function apiCurrentRound($slug)
    {
        header('Content-Type: application/json');

        // Validate game exists
        $gameConfigModel = new GameConfig();
        $gameConfig = $gameConfigModel->getBySlug($slug);

        if (!$gameConfig) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            return;
        }

        // Get current active round
        $round = $this->db->first(
            "SELECT * FROM game_rounds
                 WHERE game_slug = ? AND status IN ('betting', 'locked')
                 ORDER BY id DESC LIMIT 1",
            [$slug]
        );

        if (!$round) {
            // No active round
            echo json_encode([
                'success' => true,
                'round' => null,
                'history' => $this->getRoundHistory($slug, 10)
            ]);
            return;
        }

        // Calculate time remaining
        $now = new \DateTime();
        $endsAt = new \DateTime($round['ends_at']);
        $timeRemaining = max(0, $endsAt->getTimestamp() - $now->getTimestamp());

        echo json_encode([
            'success' => true,
            'round' => [
                'id' => $round['id'],
                'status' => $round['status'],
                'started_at' => $round['started_at'],
                'ends_at' => $round['ends_at'],
                'time_remaining' => $timeRemaining,
                'result' => $round['result'] ? json_decode($round['result'], true) : null
            ],
            'history' => $this->getRoundHistory($slug, 10)
        ]);
    }

    /**
     * Get round history for a game
     */
    private function getRoundHistory(string $gameSlug, int $limit): array
    {
        $rounds = $this->db->query(
            "SELECT result FROM game_rounds
                 WHERE game_slug = ? AND status = 'ended'
                 ORDER BY id DESC LIMIT ?",
            [$gameSlug, $limit]
        )->fetchAll();

        return array_map(function ($round) {
            return json_decode($round['result'], true);
        }, $rounds);
    }
}

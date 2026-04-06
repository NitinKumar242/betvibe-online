<?php
/**
 * BetVibe WebSocket Server — Ratchet Implementation
 * Handles real-time communication for games, live feeds, and personal notifications
 *
 * Run with: php websocket/server.php
 * Supervisor manages this process in production
 */

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class BetVibeWebSocket implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected array $userConnections = []; // userId => ConnectionInterface
    protected array $gameRooms = [];       // gameName => [ConnectionInterface]

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        echo "[WS] BetVibe WebSocket server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $conn->userData = null; // Not authenticated yet
        echo "[WS] New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($conn, $data['token'] ?? '');
                break;
            case 'subscribe':
                $this->handleSubscribe($conn, $data['game'] ?? '');
                break;
            case 'unsubscribe':
                $this->handleUnsubscribe($conn, $data['game'] ?? '');
                break;
            case 'ping':
                $conn->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);

        // Remove from user connections
        if ($conn->userData) {
            unset($this->userConnections[$conn->userData->id]);
        }

        // Remove from game rooms
        foreach ($this->gameRooms as $game => &$room) {
            $room = array_filter($room, fn($c) => $c !== $conn);
        }

        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Authenticate connection via session token
     */
    private function handleAuth(ConnectionInterface $conn, string $token): void
    {
        if (empty($token)) {
            $conn->send(json_encode(['type' => 'auth_failed', 'reason' => 'No token']));
            return;
        }

        try {
            $pdo = $this->getDatabaseConnection();
            $stmt = $pdo->prepare(
                "SELECT u.id, u.username FROM sessions s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.token = ? AND s.expires_at > NOW() AND u.is_banned = 0"
            );
            $stmt->execute([$token]);
            $user = $stmt->fetch(\PDO::FETCH_OBJ);

            if ($user) {
                $conn->userData = $user;
                $this->userConnections[$user->id] = $conn;
                $conn->send(json_encode(['type' => 'auth_success', 'username' => $user->username]));
                echo "[WS] Authenticated: {$user->username} (#{$user->id})\n";
            } else {
                $conn->send(json_encode(['type' => 'auth_failed', 'reason' => 'Invalid token']));
            }
        } catch (\Exception $e) {
            $conn->send(json_encode(['type' => 'auth_failed', 'reason' => 'Server error']));
            echo "[WS] Auth error: {$e->getMessage()}\n";
        }
    }

    /**
     * Subscribe to a game room
     */
    private function handleSubscribe(ConnectionInterface $conn, string $game): void
    {
        if (empty($game)) return;

        if (!isset($this->gameRooms[$game])) {
            $this->gameRooms[$game] = [];
        }

        $this->gameRooms[$game][] = $conn;
        $conn->send(json_encode(['type' => 'subscribed', 'game' => $game]));
    }

    /**
     * Unsubscribe from a game room
     */
    private function handleUnsubscribe(ConnectionInterface $conn, string $game): void
    {
        if (isset($this->gameRooms[$game])) {
            $this->gameRooms[$game] = array_filter(
                $this->gameRooms[$game],
                fn($c) => $c !== $conn
            );
        }
    }

    /**
     * Broadcast to all connected clients
     */
    public function broadcastToAll(array $message): void
    {
        $encoded = json_encode($message);
        foreach ($this->clients as $client) {
            $client->send($encoded);
        }
    }

    /**
     * Broadcast to a specific game room
     */
    public function broadcastToGame(string $game, array $message): void
    {
        if (!isset($this->gameRooms[$game])) return;

        $encoded = json_encode($message);
        foreach ($this->gameRooms[$game] as $client) {
            $client->send($encoded);
        }
    }

    /**
     * Send to a specific user
     */
    public function sendToUser(int $userId, array $message): void
    {
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send(json_encode($message));
        }
    }

    /**
     * Process IPC queue files (called from periodic timer)
     */
    public function processQueue(): void
    {
        $queueDir = dirname(__DIR__) . '/storage/ws_queue';
        if (!is_dir($queueDir)) return;

        $files = glob($queueDir . '/ws_queue_*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) {
                @unlink($file);
                continue;
            }

            $target = $data['target'] ?? 'all';
            $message = $data['message'] ?? [];

            switch ($target) {
                case 'all':
                    $this->broadcastToAll($message);
                    break;
                case 'user':
                    $this->sendToUser((int)($data['user_id'] ?? 0), $message);
                    break;
                case 'game':
                    $this->broadcastToGame($data['game'] ?? '', $message);
                    break;
            }

            @unlink($file);
        }
    }

    /**
     * Get a PDO database connection
     */
    private function getDatabaseConnection(): \PDO
    {
        return new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'betvibe_db'
            ),
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Get count of connected clients
     */
    public function getClientCount(): int
    {
        return $this->clients->count();
    }
}

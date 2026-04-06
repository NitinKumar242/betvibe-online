# WebSocket Server Architecture

## Overview
Ratchet PHP WebSocket server runs on port 8080.
Nginx proxies wss://betvibe.com/ws → localhost:8080.

## Connection Flow
```
Client connects to wss://betvibe.com/ws
→ Client sends auth: { type:'auth', token:'SESSION_TOKEN' }
→ Server validates session token from DB
→ If valid: store connection in $connections[$userId]
→ If invalid: send {type:'auth_failed'} and close

Client subscribes to game:
→ Client sends { type:'subscribe', game:'crash' }
→ Server adds client to $gameRooms['crash']

Server broadcasts:
→ To all: WebSocketServer::broadcast($msg)
→ To game room: WebSocketServer::broadcastToGame('crash', $msg)
→ To user: WebSocketServer::sendToUser($userId, $msg)
```

## Message Types (Server → Client)
```json
// Crash game tick
{ "type": "crash_tick", "multiplier": 2.54, "elapsed_ms": 3200 }

// Crash game end
{ "type": "crash_end", "crash_point": 2.54, "your_result": "loss|win|cashout" }

// Round start (Color Predict / Fast Parity)
{ "type": "round_start", "game": "color_predict", "round_id": 1234, "ends_at": 1720000000 }

// Round locked (30s before end)
{ "type": "round_locked", "game": "color_predict", "round_id": 1234 }

// Round result
{ "type": "round_result", "game": "color_predict", "result": "green", "round_id": 1234 }

// Win feed
{ "type": "win_feed", "username": "Ra***", "game": "Crash", "payout": 840, "multiplier": 4.2 }

// Personal: balance update
{ "type": "balance_update", "real": 1340.00, "bonus": 50 }

// Personal: level up
{ "type": "level_up", "new_level": 5, "xp": 2000 }

// Personal: quest complete
{ "type": "quest_complete", "quest_title": "Win 2 Color bets", "xp_reward": 150 }
```

## Ratchet Server Implementation
```php
// websocket/server.php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new BetVibeWebSocket()
        )
    ),
    8080
);

$server->run();
```

```php
// app/WebSocket/BetVibeWebSocket.php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class BetVibeWebSocket implements MessageComponentInterface {
    protected \SplObjectStorage $clients;
    protected array $userConnections = []; // userId => Connection
    protected array $gameRooms = [];       // gameName => [Connection]

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        $conn->userData = null; // Not authenticated yet
    }

    public function onMessage(ConnectionInterface $conn, $msg): void {
        $data = json_decode($msg, true);

        switch ($data['type'] ?? '') {
            case 'auth':
                $this->handleAuth($conn, $data['token']);
                break;
            case 'subscribe':
                $this->handleSubscribe($conn, $data['game']);
                break;
            case 'crash_cashout':
                $this->handleCrashCashout($conn);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void {
        $this->clients->detach($conn);
        if ($conn->userData) {
            unset($this->userConnections[$conn->userData->id]);
        }
        foreach ($this->gameRooms as $game => &$room) {
            $room = array_filter($room, fn($c) => $c !== $conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void {
        $conn->close();
    }

    private function handleAuth(ConnectionInterface $conn, string $token): void {
        // Query DB to validate session
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username FROM sessions s
             JOIN users u ON u.id=s.user_id
             WHERE s.token=? AND s.expires_at > NOW() AND u.is_banned=0"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if ($user) {
            $conn->userData = $user;
            $this->userConnections[$user->id] = $conn;
            $conn->send(json_encode(['type' => 'auth_success', 'username' => $user->username]));
        } else {
            $conn->send(json_encode(['type' => 'auth_failed']));
        }
    }

    // Static methods for other PHP classes to call
    public static function broadcastToAll(array $message): void {
        // Write to a shared message queue (Redis or file-based IPC)
        // Simple approach: write to /tmp/ws_broadcast.json
        file_put_contents('/tmp/ws_broadcast_' . time() . '.json', json_encode($message));
    }
}
```

## Internal IPC (PHP → WebSocket)
Since PHP-FPM and Ratchet are separate processes, use file-based IPC:

```php
// WebSocketService.php (called from game logic)
class WebSocketService {
    public static function broadcast(array $message): void {
        $file = '/tmp/ws_queue_' . uniqid() . '.json';
        file_put_contents($file, json_encode(['target'=>'all', 'message'=>$message]));
    }

    public static function sendToUser(int $userId, array $message): void {
        $file = '/tmp/ws_queue_' . uniqid() . '.json';
        file_put_contents($file, json_encode(['target'=>'user', 'user_id'=>$userId, 'message'=>$message]));
    }
}

// Ratchet server polls /tmp/ws_queue_*.json every 100ms
// Processes and deletes queue files
// Alternative: use Redis pub/sub if available
```

## Nginx WebSocket Proxy
```nginx
location /ws {
    proxy_pass http://localhost:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 3600s;  # Keep WS connections alive 1hr
    proxy_send_timeout 3600s;
}
```

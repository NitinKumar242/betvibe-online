<?php
/**
 * BetVibe WebSocket Server Entry Point
 * Ratchet PHP WebSocket server running on port 8080
 *
 * Run: php websocket/server.php
 * Managed by Supervisor in production
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/WebSocket/BetVibeWebSocket.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$port = (int)($_ENV['WS_PORT'] ?? 8080);
$host = $_ENV['WS_HOST'] ?? '0.0.0.0';

echo "=== BetVibe WebSocket Server ===\n";
echo "Starting on {$host}:{$port}...\n";

$wsApp = new BetVibeWebSocket();

$server = IoServer::factory(
    new HttpServer(
        new WsServer($wsApp)
    ),
    $port,
    $host
);

// Add periodic timer to process IPC queue files (every 100ms)
$server->loop->addPeriodicTimer(0.1, function () use ($wsApp) {
    $wsApp->processQueue();
});

// Log stats every 30 seconds
$server->loop->addPeriodicTimer(30, function () use ($wsApp) {
    $count = $wsApp->getClientCount();
    echo "[WS] Connected clients: {$count}\n";
});

echo "WebSocket server running on {$host}:{$port}\n";
$server->run();

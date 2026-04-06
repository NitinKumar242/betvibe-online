<?php
/**
 * BetVibe - WebSocket Service
 * Static methods for PHP-FPM processes to communicate with the Ratchet WebSocket server
 * Uses file-based IPC (storage/ws_queue/)
 */

namespace App\Services;

class WebSocketService
{
    private static string $queueDir = '';

    /**
     * Get the queue directory path
     */
    private static function getQueueDir(): string
    {
        if (self::$queueDir === '') {
            self::$queueDir = dirname(__DIR__, 2) . '/storage/ws_queue';
            if (!is_dir(self::$queueDir)) {
                mkdir(self::$queueDir, 0755, true);
            }
        }
        return self::$queueDir;
    }

    /**
     * Broadcast a message to all connected WebSocket clients
     */
    public static function broadcast(array $message): void
    {
        $file = self::getQueueDir() . '/ws_queue_' . uniqid('', true) . '.json';
        file_put_contents($file, json_encode([
            'target' => 'all',
            'message' => $message,
            'timestamp' => microtime(true),
        ]));
    }

    /**
     * Send a message to a specific user via WebSocket
     */
    public static function sendToUser(int $userId, array $message): void
    {
        $file = self::getQueueDir() . '/ws_queue_' . uniqid('', true) . '.json';
        file_put_contents($file, json_encode([
            'target' => 'user',
            'user_id' => $userId,
            'message' => $message,
            'timestamp' => microtime(true),
        ]));
    }

    /**
     * Broadcast to a specific game room
     */
    public static function broadcastToGame(string $game, array $message): void
    {
        $file = self::getQueueDir() . '/ws_queue_' . uniqid('', true) . '.json';
        file_put_contents($file, json_encode([
            'target' => 'game',
            'game' => $game,
            'message' => $message,
            'timestamp' => microtime(true),
        ]));
    }
}

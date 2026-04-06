<?php
/**
 * BetVibe - Push Notification Service
 * VAPID-based Web Push Notifications
 */

namespace App\Services;

use App\Core\DB;

class PushNotificationService
{
    private DB $db;
    private string $publicKey;
    private string $privateKey;
    private string $subject;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
        $this->publicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
        $this->privateKey = $_ENV['VAPID_PRIVATE_KEY'] ?? '';
        $this->subject = $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@betsvibe.online';
    }

    /**
     * Send push notification to a specific user
     */
    public function sendToUser(int $userId, string $title, string $body, ?string $url = null): int
    {
        $subscriptions = $this->db->all(
            "SELECT * FROM push_subscriptions WHERE user_id = ?",
            [$userId]
        );

        $sent = 0;
        foreach ($subscriptions as $sub) {
            if ($this->send($sub, $title, $body, $url)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Send push notification to all subscribed users
     */
    public function broadcast(string $title, string $body, ?string $url = null): int
    {
        $subscriptions = $this->db->all("SELECT * FROM push_subscriptions");

        $sent = 0;
        foreach ($subscriptions as $sub) {
            if ($this->send($sub, $title, $body, $url)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Send a push notification via Web Push protocol
     */
    private function send(array $subscription, string $title, string $body, ?string $url = null): bool
    {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/images/icon-192.png',
            'badge' => '/assets/images/icon-72.png',
            'url' => $url ?? '/',
            'timestamp' => time(),
        ]);

        $endpoint = $subscription['endpoint'];
        $p256dh = $subscription['p256dh'];
        $auth = $subscription['auth'];

        // For production, use a proper Web Push library
        // This is a simplified version using raw curl
        $headers = [
            'Content-Type: application/json',
            'TTL: 86400',
        ];

        // If VAPID keys are configured, add authorization header
        if ($this->publicKey && $this->privateKey) {
            $headers[] = "Authorization: vapid t={$this->generateJWT()}, k={$this->publicKey}";
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If 410 Gone, subscription has expired - remove it
        if ($httpCode === 410 || $httpCode === 404) {
            $this->db->query(
                "DELETE FROM push_subscriptions WHERE endpoint = ?",
                [$endpoint]
            );
        }

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Generate a simple JWT for VAPID
     */
    private function generateJWT(): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
        $payload = json_encode([
            'aud' => 'https://fcm.googleapis.com',
            'exp' => time() + 3600,
            'sub' => $this->subject,
        ]);

        return base64_encode($header) . '.' . base64_encode($payload);
    }

    /**
     * Get the VAPID public key for client-side subscription
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}

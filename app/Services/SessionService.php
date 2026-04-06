<?php

namespace App\Services;

use App\Core\DB;

/**
 * Session Service
 * Handles session creation, validation, and destruction
 */
class SessionService
{
    /**
     * Create a new session for a user
     *
     * @param int $userId User ID
     * @param bool $remember Whether to remember the user (30 days vs 24 hours)
     * @return string The session token
     */
    public function create(int $userId, bool $remember = false): string
    {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));

        // Get client IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Get device fingerprint (simplified: MD5 of User-Agent + IP)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceFp = md5($userAgent . $ip);

        // Calculate expiration time
        $expiresAt = $remember
            ? date('Y-m-d H:i:s', strtotime('+30 days'))
            : date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Insert session into database
        $db = DB::getInstance();
        $db->query(
            "INSERT INTO sessions (token, user_id, ip, device_fp, expires_at) 
             VALUES (?, ?, ?, ?, ?)",
            [$token, $userId, $ip, $deviceFp, $expiresAt]
        );

        // Set cookie
        $expiresTimestamp = $remember
            ? strtotime('+30 days')
            : strtotime('+24 hours');

        setcookie('session_token', $token, [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'samesite' => 'Strict'
        ]);

        return $token;
    }

    /**
     * Validate current session and return user data
     *
     * @return array|null User data or null if invalid
     */
    public function validate(): ?array
    {
        // Read cookie
        $token = $_COOKIE['session_token'] ?? null;

        if (!$token) {
            return null;
        }

        // Query session with user data
        $db = DB::getInstance();
        $result = $db->first(
            "SELECT s.*, u.*
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = ?
             AND s.expires_at > NOW()
             AND u.is_banned = 0",
            [$token]
        );

        if (!$result) {
            return null;
        }

        // IP change check - log warning but don't invalidate
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($result['ip'] !== $currentIp) {
            // Log IP change warning (could be VPN, mobile network, etc.)
            error_log("Session IP change for user {$result['id']}: {$result['ip']} -> {$currentIp}");
        }

        return $result;
    }

    /**
     * Destroy a session by token
     *
     * @param string $token Session token
     * @return void
     */
    public function destroy(string $token): void
    {
        // Delete from database
        $db = DB::getInstance();
        $db->query("DELETE FROM sessions WHERE token = ?", [$token]);

        // Expire cookie
        setcookie('session_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'samesite' => 'Strict'
        ]);
    }

    /**
     * Clean up expired sessions (called by cron daily)
     *
     * @return void
     */
    public function cleanExpired(): void
    {
        $db = DB::getInstance();
        $db->query("DELETE FROM sessions WHERE expires_at < NOW()");
    }
}

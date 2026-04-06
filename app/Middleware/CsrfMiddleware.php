<?php

namespace App\Middleware;

/**
 * CSRF Middleware
 * Validates CSRF tokens on state-changing requests
 */
class CsrfMiddleware
{
    /**
     * Handle CSRF validation
     * Exempts webhook routes which use signature verification instead
     *
     * @return void
     */
    public function handle(): void
    {
        // Only check POST, PUT, DELETE requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) {
            return;
        }

        // Exempt webhook routes
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/webhooks/') === 0) {
            return;
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Get token from header or form field
        $token = $this->getRequestToken();

        if (!$token) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token required']);
            exit;
        }

        // Validate token
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }

    /**
     * Get CSRF token from request
     *
     * @return string|null
     */
    private function getRequestToken(): ?string
    {
        // Check header first
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;

        if ($token) {
            return $token;
        }

        // Check form field
        $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? null;

        return $token;
    }

    /**
     * Get current CSRF token (for use in forms)
     *
     * @return string
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Regenerate CSRF token (after login, etc.)
     *
     * @return string
     */
    public static function regenerate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['csrf_token'];
    }
}

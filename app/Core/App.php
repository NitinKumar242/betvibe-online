<?php
/**
 * BetVibe - Application Bootstrap Class
 * Main application class that handles initialization, routing, and error handling
 */

namespace App\Core;

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class App
{
    private static ?App $instance = null;
    private ?DB $db = null;
    private array $routes = [];
    private array $middleware = [];
    private Logger $logger;
    private bool $dbInitialized = false;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->loadEnvironment();
        $this->initializeLogger();
        $this->startSession();
        $this->registerErrorHandler();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load environment variables
     */
    private function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();
    }

    /**
     * Initialize Monolog logger
     */
    private function initializeLogger(): void
    {
        $this->logger = new Logger('betvibe');
        $logPath = dirname(__DIR__, 2) . '/storage/logs/error.log';

        $this->logger->pushHandler(new StreamHandler($logPath, Logger::ERROR));
        $this->logger->pushHandler(new StreamHandler($logPath, Logger::WARNING));
    }

    /**
     * Start session with secure settings
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = $_ENV['APP_ENV'] === 'production';

            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $secure ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', '3600');

            session_name('BVSESSID');
            session_start();
        }
    }

    /**
     * Initialize database connection (lazy loading)
     */
    private function initializeDatabase(): void
    {
        if (!$this->dbInitialized) {
            $this->db = DB::getInstance();
            $this->dbInitialized = true;
        }
    }

    /**
     * Get database instance (lazy loading)
     */
    public function getDB(): DB
    {
        $this->initializeDatabase();
        return $this->db;
    }

    /**
     * Register global error handler
     */
    private function registerErrorHandler(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorTypes = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];

        $type = $errorTypes[$errno] ?? 'Unknown Error';

        $this->logger->error("$type: $errstr in $errfile on line $errline");

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $this->logger->error('Uncaught Exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->sendErrorResponse($exception->getMessage(), 500);
    }

    /**
     * Handle fatal errors
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->error('Fatal Error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }

    /**
     * Add a route
     */
    public function addRoute(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Add middleware
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $this->getRequestPath();

        // Set timezone
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

        // Set error reporting based on environment
        if ($_ENV['APP_ENV'] === 'production') {
            error_reporting(0);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        // Validate CSRF for POST requests (except API routes)
        if ($method === 'POST' && !$this->isApiRoute($path)) {
            $this->validateCsrfToken();
        }

        // Apply middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware($method, $path);
            if ($result !== null) {
                $this->sendResponse($result);
                return;
            }
        }

        // Find matching route
        $handler = $this->findRoute($method, $path);

        if ($handler !== null) {
            try {
                $result = $handler();
                $this->sendResponse($result);
            } catch (\Throwable $e) {
                $this->handleException($e);
            }
        } else {
            $this->handleNotFound($path);
        }
    }

    /**
     * Get the request path
     */
    private function getRequestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path if present
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Find matching route with parameter support
     */
    private function findRoute(string $method, string $path): ?callable
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        // Exact match first
        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        }

        // Pattern matching for routes with parameters
        foreach ($this->routes[$method] as $route => $handler) {
            $pattern = $this->convertRouteToRegex($route);
            if (preg_match($pattern, $path, $matches)) {
                // Extract parameters and store in $_GET for controller access
                array_shift($matches); // Remove full match
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $_GET[$key] = $value;
                    }
                }
                return $handler;
            }
        }

        return null;
    }

    /**
     * Convert route pattern to regex
     */
    private function convertRouteToRegex(string $route): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $route);
        return '#^' . $pattern . '$#';
    }

    /**
     * Check if route is an API route
     */
    private function isApiRoute(string $path): bool
    {
        return strpos($path, '/api/') === 0;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->sendErrorResponse('CSRF token validation failed', 403);
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(string $path): void
    {
        if ($this->isApiRoute($path)) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Not Found',
                'path' => $path
            ], 404);
        } else {
            http_response_code(404);
            echo $this->renderErrorPage(404, 'Page Not Found');
        }
    }

    /**
     * Send JSON response
     */
    public static function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(string $message, int $statusCode = 500): void
    {
        $path = $this->getRequestPath();

        if ($this->isApiRoute($path)) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => $message
            ], $statusCode);
        } else {
            http_response_code($statusCode);
            echo $this->renderErrorPage($statusCode, $message);
        }
    }

    /**
     * Send response
     */
    private function sendResponse($data): void
    {
        if (is_array($data) || is_object($data)) {
            $this->sendJsonResponse((array) $data);
        } else {
            echo $data;
        }
    }

    /**
     * Render error page
     */
    private function renderErrorPage(int $code, string $message): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error ' . $code . ' - BetVibe</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 4rem;
            margin: 0;
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            font-size: 1.2rem;
            color: #a0a0a0;
        }
        a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.75rem 2rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>' . $code . '</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>';
    }

    /**
     * Get environment variable
     */
    public static function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

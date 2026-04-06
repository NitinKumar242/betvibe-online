<?php

namespace App\Controllers;

use App\Services\WalletService;
use App\Services\WatchPayService;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WageringRequirementException;
use App\Exceptions\WithdrawalPendingException;

/**
 * Wallet Controller
 * Handles wallet operations, deposits, and withdrawals
 */
class WalletController
{
    private $db;
    private $walletService;
    private $watchPayService;

    public function __construct()
    {
        $this->db = \App\Core\DB::getInstance();
        $this->walletService = new WalletService($this->db);
        $this->watchPayService = new WatchPayService($this->db);
    }

    /**
     * Display wallet index page
     */
    public function index()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Wallet</title>
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
        .balance {
            font-size: 3rem;
            margin: 2rem 0;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        a {
            display: inline-block;
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
    <h1>Your Wallet</h1>
    <div class="balance">₹1,000.00</div>
    <div class="actions">
        <a href="/wallet/deposit">Deposit</a>
        <a href="/wallet/withdraw">Withdraw</a>
        <a href="/wallet/history">History</a>
    </div>
</body>
</html>';
    }

    /**
     * Display deposit page
     */
    public function deposit()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Deposit</title>
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
    <h1>Deposit Funds</h1>
    <p>Deposit interface will be loaded here...</p>
    <a href="/wallet">Back to Wallet</a>
</body>
</html>';
    }

    /**
     * Display withdraw page
     */
    public function withdraw()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Withdraw</title>
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
    <h1>Withdraw Funds</h1>
    <p>Withdrawal interface will be loaded here...</p>
    <a href="/wallet">Back to Wallet</a>
</body>
</html>';
    }

    /**
     * Display transaction history
     */
    public function history()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Transaction History</title>
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
    <h1>Transaction History</h1>
    <p>Transaction history will be displayed here...</p>
    <a href="/wallet">Back to Wallet</a>
</body>
</html>';
    }

    /**
     * POST /api/wallet/deposit
     * Create a deposit payment request
     */
    public function createDeposit()
    {
        // Check authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0;

        // Validate amount
        if ($amount < 100) {
            return $this->json(['error' => 'Minimum deposit amount is NPR 100'], 400);
        }

        try {
            $result = $this->watchPayService->createPayment($userId, $amount);
            return $this->json([
                'success' => true,
                'checkout_url' => $result['checkout_url'],
                'transaction_id' => $result['transaction_id']
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/webhooks/watchpay
     * Handle WatchPay webhook (no auth required)
     */
    public function handleWatchPayWebhook()
    {
        // Get raw body
        $rawBody = file_get_contents('php://input');

        try {
            $this->watchPayService->handleWebhook($rawBody);
            echo "success";
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            echo "error: " . $e->getMessage();
            exit;
        }
    }

    /**
     * GET /api/wallet/balance
     * Get user's wallet balance
     */
    public function getBalance()
    {
        // Check authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $balance = $this->walletService->getBalance($userId);

        return $this->json([
            'success' => true,
            'real_balance' => $balance['real'],
            'bonus_coins' => $balance['bonus']
        ]);
    }

    /**
     * GET /api/wallet/transactions
     * Get user's transaction history
     */
    public function getTransactions()
    {
        // Check authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Get query parameters
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $type = isset($_GET['type']) ? $_GET['type'] : null;

        $result = $this->walletService->getTransactions($userId, $page, 20, $type);

        return $this->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages']
            ]
        ]);
    }

    /**
     * POST /api/wallet/withdraw
     * Create a withdrawal request
     */
    public function createWithdrawal()
    {
        // Check authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);
        $amount = isset($input['amount']) ? (float) $input['amount'] : 0;
        $watchpayAccount = $input['watchpay_account'] ?? '';

        // Validate amount
        if ($amount < 500) {
            return $this->json(['error' => 'Minimum withdrawal amount is NPR 500'], 400);
        }

        if ($amount > 50000) {
            return $this->json(['error' => 'Maximum withdrawal amount is NPR 50,000'], 400);
        }

        // Get user balance
        $balance = $this->walletService->getBalance($userId);

        if ($amount > $balance['real']) {
            return $this->json(['error' => 'Insufficient real balance'], 400);
        }

        // Check wagering requirement
        if (!$this->walletService->checkWageringRequirement($userId)) {
            return $this->json(['error' => 'Wagering requirement not met. You must wager your deposit amount before withdrawing.'], 400);
        }

        // Check for pending withdrawal
        if ($this->walletService->hasPendingWithdrawal($userId)) {
            return $this->json(['error' => 'You already have a pending withdrawal request'], 400);
        }

        // Check daily withdrawal limit
        $todayWithdrawals = $this->walletService->getTodayWithdrawals($userId);
        if (($todayWithdrawals + $amount) > 50000) {
            return $this->json(['error' => 'Daily withdrawal limit exceeded. Maximum NPR 50,000 per day.'], 400);
        }

        // Check last deposit cooldown (24 hours)
        $lastDepositTime = $this->walletService->getLastDepositTime($userId);
        if ($lastDepositTime) {
            $lastDeposit = strtotime($lastDepositTime);
            $now = time();
            $hoursSinceDeposit = ($now - $lastDeposit) / 3600;

            if ($hoursSinceDeposit < 24) {
                $remainingHours = ceil(24 - $hoursSinceDeposit);
                return $this->json(['error' => "Withdrawal cooldown active. Please wait {$remainingHours} hours after your first deposit."], 400);
            }
        }

        // Check KYC for amounts above 5000
        if ($amount > 5000) {
            $stmt = $this->db->query("SELECT kyc_status FROM users WHERE id = ?", [$userId]);
            $user = $stmt->fetch();
            if (!$user || $user['kyc_status'] !== 'verified') {
                return $this->json(['error' => 'KYC verification required for withdrawals above NPR 5,000'], 400);
            }
        }

        // Get user details for notification
        $stmt = $this->db->query("SELECT username FROM users WHERE id = ?", [$userId]);
        $user = $stmt->fetch();

        // DB transaction to create withdrawal
        $this->db->beginTransaction();

        try {
            // Deduct from real balance immediately (hold)
            $this->walletService->deductRealBalance($userId, $amount);

            // Create withdrawal request
            $this->db->query(
                "INSERT INTO withdrawal_requests (user_id, amount, watchpay_account, status) 
                VALUES (?, ?, ?, 'pending')",
                [$userId, $amount, $watchpayAccount]
            );

            $withdrawalId = $this->db->lastInsertId();

            // Create transaction record
            $this->db->query(
                "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id) 
                VALUES (?, 'withdraw', ?, 'real', 'pending', ?)",
                [$userId, $amount, "withdrawal_{$withdrawalId}"]
            );

            $this->db->commit();

            // Send Telegram alert to admin group
            $this->sendAdminTelegramAlert($user['username'], $amount, $watchpayAccount, $withdrawalId);

            // Check if auto-approve eligible
            $autoApprove = $amount < 1000;

            return $this->json([
                'success' => true,
                'message' => 'Request submitted, processing within 2-24 hours',
                'withdrawal_id' => $withdrawalId,
                'auto_approve' => $autoApprove
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->json(['error' => 'Failed to create withdrawal request'], 500);
        }
    }

    /**
     * Get authenticated user ID from session
     */
    private function getAuthenticatedUserId()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Send Telegram alert to admin group
     */
    private function sendAdminTelegramAlert($username, $amount, $watchpayAccount, $withdrawalId)
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';

        if (!$botToken || !$adminChatId) {
            return;
        }

        $message = "💸 New Withdrawal Request\nUser: {$username}\nAmount: NPR {$amount}\nWatchPay: {$watchpayAccount}\nID: {$withdrawalId}";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id' => $adminChatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Return JSON response
     */
    private function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

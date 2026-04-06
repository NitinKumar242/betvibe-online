<?php

namespace App\Services;

use App\Services\WalletService;

/**
 * WatchPay Service
 * Handles WatchPay payment gateway integration
 */
class WatchPayService
{
    private $db;
    private $walletService;
    private $merchantId;
    private $apiKey;
    private $webhookSecret;
    private $apiUrl;

    public function __construct($db)
    {
        $this->db = $db;
        $this->walletService = new WalletService($db);
        $this->merchantId = $_ENV['WATCHPAY_MERCHANT_ID'] ?? '100555001';
        $this->apiKey = $_ENV['WATCHPAY_API_KEY'] ?? '';
        $this->webhookSecret = $_ENV['WATCHPAY_WEBHOOK_SECRET'] ?? '';
        $this->apiUrl = 'https://api.watchpays.com/v1';
    }

    /**
     * Create a payment request
     * 
     * @param int $userId
     * @param float $amount
     * @return array ['checkout_url' => string, 'transaction_id' => int]
     * @throws \Exception
     */
    public function createPayment(int $userId, float $amount): array
    {
        // Validate minimum deposit
        if ($amount < 100) {
            throw new \Exception("Minimum deposit amount is NPR 100");
        }

        $merchantOrderNo = 'DEP_' . time() . '_' . $userId;

        // Create pending transaction in DB
        $this->db->query(
            "INSERT INTO transactions (user_id, type, amount, balance_type, status, gateway, reference_id) 
            VALUES (?, 'deposit', ?, 'real', 'pending', 'watchpay', ?)",
            [$userId, $amount, $merchantOrderNo]
        );

        $transactionId = $this->db->lastInsertId();

        $callbackUrl = ($_ENV['APP_URL'] ?? 'https://betsvibe.online') . '/api/webhooks/watchpay';
        $formattedAmount = number_format($amount, 2, '.', '');
        
        $params = [
            "merchant_id" => $this->merchantId,
            "amount" => $formattedAmount,
            "merchant_order_no" => $merchantOrderNo,
            "callback_url" => $callbackUrl
        ];

        // Generate MD5 signature strictly following specs
        $filtered = array_filter($params, function($v) { return $v !== '' && $v !== null; });
        ksort($filtered);

        $signStr = "";
        foreach($filtered as $k => $v){
            $signStr .= $k . "=" . $v . "&";
        }
        $signStr .= "key=" . $this->apiKey;
        $signature = md5($signStr);

        $payload = [
            'merchant_id' => $this->merchantId,
            'api_key' => $this->apiKey,
            'amount' => $formattedAmount,
            'merchant_order_no' => $merchantOrderNo,
            'callback_url' => $callbackUrl,
            'extra' => 'user_' . $userId,
            'signature' => $signature
        ];

        $response = $this->makeApiRequest('/create', 'POST', $payload);

        if (!isset($response['success']) || !$response['success'] || !isset($response['payment_url'])) {
            throw new \Exception("Failed to create payment with WatchPays: " . json_encode($response));
        }

        // Keep order_no if returned
        if (isset($response['order_no'])) {
            $this->db->query(
                "UPDATE transactions SET note = ? WHERE id = ?",
                ['Gateway Order: ' . $response['order_no'], $transactionId]
            );
        }

        return [
            'checkout_url' => $response['payment_url'],
            'transaction_id' => $transactionId
        ];
    }

    /**
     * Handle WatchPay webhook
     * 
     * @param string $rawBody
     * @param string $signature
     * @return bool
     * @throws \Exception
     */
    public function handleWebhook(string $rawBody, string $signature = ''): bool
    {
        $data = json_decode($rawBody, true);
        if (!$data || !isset($data['merchantOrder']) || !isset($data['orderNo'])) {
            throw new \Exception("Invalid webhook payload");
        }
        
        if (isset($data['status']) && strtolower($data['status']) !== 'success') {
            throw new \Exception("Status not success");
        }

        $merchantOrder = $data['merchantOrder'];
        $amountReceived = floatval($data['amount']);
        $gatewayOrder = $data['orderNo'];

        $transaction = $this->db->query(
            "SELECT * FROM transactions WHERE reference_id = ? AND type = 'deposit'",
            [$merchantOrder]
        )->first();

        if (!$transaction) {
            throw new \Exception("Order not found");
        }

        if ($transaction->status === 'completed') {
            return true; // Avoid duplicate processing
        }

        if (floatval($transaction->amount) != $amountReceived) {
            throw new \Exception("Amount mismatch");
        }

        // Credit Wallet using a DB transaction
        $this->db->beginTransaction();

        try {
            // Update wallet balance
            $this->db->query(
                "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
                [$amountReceived, $transaction->user_id]
            );

            // Update transaction status
            $this->db->query(
                "UPDATE transactions SET status = 'completed', note = CONCAT(IFNULL(note, ''), ' | Webhook Gateway Order: ', ?) WHERE id = ?",
                [$gatewayOrder, $transaction->id]
            );

            $this->db->commit();

            // Trigger referral check (outside db transaction)
            $this->checkFirstDeposit($transaction->user_id, $amountReceived);

            // Send WebSocket event to user
            $this->sendWebSocketEvent($transaction->user_id, [
                'type' => 'deposit_success',
                'amount' => $amountReceived,
                'transaction_id' => $transaction->id
            ]);

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process a payout (withdrawal)
     * 
     * @param int $withdrawalId
     * @return bool
     */
    public function processPayout(int $withdrawalId): bool
    {
        // Get withdrawal details
        $withdrawal = $this->db->query(
            "SELECT wr.*, u.username, u.telegram_chat_id 
            FROM withdrawal_requests wr
            JOIN users u ON wr.user_id = u.id
            WHERE wr.id = ? AND wr.status = 'pending'",
            [$withdrawalId]
        )->first();

        if (!$withdrawal) {
            return false;
        }

        // Call WatchPay payout API
        $payload = [
            'amount' => $withdrawal->amount,
            'currency' => 'NPR',
            'account' => $withdrawal->watchpay_account,
            'reference' => "withdrawal_{$withdrawalId}"
        ];

        try {
            $response = $this->makeApiRequest('/payouts', 'POST', $payload);

            if (isset($response['success']) && $response['success']) {
                // Update withdrawal status to approved
                $this->db->query(
                    "UPDATE withdrawal_requests SET status = 'approved', reviewed_at = NOW() WHERE id = ?",
                    [$withdrawalId]
                );

                // Update transaction status
                $this->db->query(
                    "UPDATE transactions SET status = 'completed' 
                    WHERE user_id = ? AND type = 'withdraw' AND reference_id = ?",
                    [$withdrawal->user_id, "withdrawal_{$withdrawalId}"]
                );

                // Notify user via Telegram
                $payoutId = isset($response['payout_id']) ? $response['payout_id'] : 'N/A';
                $this->sendTelegramNotification(
                    $withdrawal->telegram_chat_id,
                    "✅ Withdrawal Approved\n\nAmount: NPR {$withdrawal->amount}\nReference: {$payoutId}"
                );

                return true;
            } else {
                throw new \Exception("WatchPay payout failed");
            }
        } catch (\Exception $e) {
            // Restore balance
            $this->walletService->restoreRealBalance($withdrawal->user_id, $withdrawal->amount);

            // Update withdrawal status to failed
            $this->db->query(
                "UPDATE withdrawal_requests SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?",
                [$e->getMessage(), $withdrawalId]
            );

            // Update transaction status
            $this->db->query(
                "UPDATE transactions SET status = 'failed', note = ? 
                WHERE user_id = ? AND type = 'withdraw' AND reference_id = ?",
                [$e->getMessage(), $withdrawal->user_id, "withdrawal_{$withdrawalId}"]
            );

            // Notify user via Telegram
            $this->sendTelegramNotification(
                $withdrawal->telegram_chat_id,
                "❌ Withdrawal Failed\n\nAmount: NPR {$withdrawal->amount}\nReason: {$e->getMessage()}\nBalance has been restored."
            );

            return false;
        }
    }

    /**
     * Make API request to WatchPay
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return array
     * @throws \Exception
     */
    private function makeApiRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("WatchPay API error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("WatchPay API returned HTTP {$httpCode}: {$response}");
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new \Exception("Invalid WatchPay API response");
        }

        return $decoded;
    }

    /**
     * Check first deposit and trigger referral bonus
     * 
     * @param int $userId
     * @param float $amount
     * @return void
     */
    private function checkFirstDeposit(int $userId, float $amount): void
    {
        // Check if this is first deposit
        $depositCount = $this->db->query(
            "SELECT COUNT(*) as count FROM transactions 
            WHERE user_id = ? AND type = 'deposit' AND status = 'completed'",
            [$userId]
        )->first();

        if ($depositCount->count == 1 && $amount >= 200) {
            // Trigger referral bonus
            $this->db->query(
                "INSERT INTO referral_bonus_queue (user_id, amount, status) 
                VALUES (?, ?, 'pending')",
                [$userId, $amount]
            );
        }
    }

    /**
     * Send WebSocket event to user
     * 
     * @param int $userId
     * @param array $data
     * @return void
     */
    private function sendWebSocketEvent(int $userId, array $data): void
    {
        // This would integrate with the WebSocket server
        // For now, we'll store in a queue for the WebSocket server to process
        $this->db->query(
            "INSERT INTO websocket_events (user_id, event_data, created_at) 
            VALUES (?, ?, NOW())",
            [$userId, json_encode($data)]
        );
    }

    /**
     * Send Telegram notification to user
     * 
     * @param string|null $chatId
     * @param string $message
     * @return void
     */
    private function sendTelegramNotification(?string $chatId, string $message): void
    {
        if (!$chatId) {
            return;
        }

        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        if (!$botToken) {
            return;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
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
}

<?php
/**
 * BetVibe - Telegram Service
 * Handles sending messages and managing Telegram bot interactions
 */

namespace App\Services;

class TelegramService
{
    private static ?string $botToken = null;
    private static ?string $adminChatId = null;

    private static function init(): void
    {
        if (self::$botToken === null) {
            self::$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
            self::$adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';
        }
    }

    /**
     * Send a message to a specific chat
     */
    public static function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): array
    {
        self::init();
        if (!self::$botToken) return ['ok' => false, 'error' => 'No bot token'];

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        return self::apiPost('sendMessage', $payload);
    }

    /**
     * Send to admin group
     */
    public static function sendToAdminGroup(string $text): array
    {
        self::init();
        if (!self::$adminChatId) return ['ok' => false, 'error' => 'No admin chat ID'];

        return self::sendMessage(self::$adminChatId, $text);
    }

    /**
     * Answer callback query
     */
    public static function answerCallbackQuery(string $callbackId, string $text = ''): array
    {
        self::init();
        return self::apiPost('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }

    /**
     * Edit message text
     */
    public static function editMessage(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    {
        self::init();
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        return self::apiPost('editMessageText', $payload);
    }

    /**
     * Make a Telegram Bot API call
     */
    private static function apiPost(string $method, array $params): array
    {
        $url = "https://api.telegram.org/bot" . self::$botToken . "/{$method}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true) ?? [];
        $result['http_code'] = $httpCode;

        return $result;
    }
}

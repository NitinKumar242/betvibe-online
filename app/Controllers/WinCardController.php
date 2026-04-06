<?php
/**
 * BetVibe - Win Card Controller
 * API endpoint for generating/retrieving win card images
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\WinCardService;

class WinCardController
{
    /**
     * GET /api/win-card/{betId}
     * Generate or retrieve a win card image
     */
    public function getWinCard($betId)
    {
        $auth = new AuthMiddleware();
        $user = $auth->require();

        $betId = (int)$betId;
        if (!$betId) {
            return ['success' => false, 'error' => 'Invalid bet ID'];
        }

        $service = new WinCardService();
        $url = $service->getOrGenerate($betId, (int)$user['id']);

        if (!$url) {
            return ['success' => false, 'error' => 'Win card not available for this bet'];
        }

        $appUrl = $_ENV['APP_URL'] ?? 'https://betsvibe.online';
        $fullUrl = $appUrl . $url;

        return [
            'success' => true,
            'data' => [
                'image_url' => $url,
                'full_url' => $fullUrl,
                'whatsapp_url' => 'https://wa.me/?text=' . urlencode("I just won on BetVibe! 🔥 " . $fullUrl),
            ]
        ];
    }
}

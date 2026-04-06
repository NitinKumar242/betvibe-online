<?php
/**
 * BetVibe - Referral Controller
 * Handles referral link redirects and API endpoints
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ReferralService;

class ReferralController
{
    private $db;
    private ReferralService $referralService;

    public function __construct()
    {
        $this->db = \App\Core\DB::getInstance();
        $this->referralService = new ReferralService($this->db);
    }

    /**
     * GET /r/{code}
     * Redirect referral link to registration page
     */
    public function handleRefLink(): void
    {
        $code = $_GET['code'] ?? '';

        if ($code) {
            $referrerId = $this->referralService->lookupRefCode($code);
            if ($referrerId) {
                header('Location: /register?ref=' . urlencode($code));
                exit;
            }
        }

        // Code not found or empty — redirect to register without error
        header('Location: /register');
        exit;
    }

    /**
     * GET /api/referral/dashboard
     * Returns referral dashboard data (auth required)
     */
    public function getDashboard()
    {
        $auth = new AuthMiddleware();
        $user = $auth->require();

        $dashboard = $this->referralService->getDashboard((int)$user['id']);

        return [
            'success' => true,
            'data' => $dashboard
        ];
    }

    /**
     * GET /api/referral/share-link
     * Returns referral share link and WhatsApp URL (auth required)
     */
    public function getShareLink()
    {
        $auth = new AuthMiddleware();
        $user = $auth->require();

        return [
            'success' => true,
            'data' => [
                'link' => $this->referralService->generateShareLink($user),
                'whatsapp_url' => $this->referralService->generateWhatsAppLink($user),
                'ref_code' => $user['ref_code'],
            ]
        ];
    }

    /**
     * GET /referral
     * Render the referral page
     */
    public function index(): string
    {
        ob_start();
        include __DIR__ . '/../../public/referral.php';
        return ob_get_clean();
    }
}

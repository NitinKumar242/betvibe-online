<?php
/**
 * BetVibe - Win Card Service
 * Generates shareable win card images using PHP GD
 */

namespace App\Services;

use App\Core\DB;

class WinCardService
{
    private DB $db;
    private string $storageDir;
    private string $fontPath;

    public function __construct(?DB $db = null)
    {
        $this->db = $db ?? DB::getInstance();
        $this->storageDir = dirname(__DIR__, 2) . '/storage/win_cards';
        $this->fontPath = dirname(__DIR__, 2) . '/storage/fonts/Roboto-Bold.ttf';

        // Ensure directories exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check if a win card should be generated
     */
    public function shouldGenerate(float $multiplier, float $payout): bool
    {
        return $multiplier >= 3.0 || $payout >= 500;
    }

    /**
     * Generate a win card image
     *
     * @return string Public URL path to the generated image
     */
    public function generate(array $user, string $game, float $multiplier, float $payout): string
    {
        $width = 800;
        $height = 420;

        $img = imagecreatetruecolor($width, $height);

        // Enable anti-aliasing
        imageantialias($img, true);

        // Dark background
        $bg = imagecolorallocate($img, 13, 13, 13);
        imagefill($img, 0, 0, $bg);

        // Gradient overlay (simulate with rectangles)
        for ($i = 0; $i < 100; $i++) {
            $alpha = (int)(127 * ($i / 100));
            $gradColor = imagecolorallocatealpha($img, 127, 119, 221, max(90, $alpha));
            imagefilledrectangle($img, 0, $height - $i, $width, $height - $i + 1, $gradColor);
        }

        // Accent border
        $accent = imagecolorallocate($img, 127, 119, 221);
        imagerectangle($img, 3, 3, $width - 4, $height - 4, $accent);
        imagerectangle($img, 4, 4, $width - 5, $height - 5, $accent);

        // Colors
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 160, 160, 160);
        $green = imagecolorallocate($img, 29, 158, 117);
        $yellow = imagecolorallocate($img, 239, 159, 39);

        // Check if font exists, use built-in font as fallback
        $fontExists = file_exists($this->fontPath);

        if ($fontExists) {
            // Username
            imagettftext($img, 28, 0, 60, 100, $white, $this->fontPath, $user['username'] ?? 'Player');

            // Game name
            imagettftext($img, 18, 0, 60, 145, $gray, $this->fontPath, 'played ' . $game);

            // Won amount (big)
            $payoutFormatted = 'NPR ' . number_format($payout, 0);
            imagettftext($img, 72, 0, 60, 270, $green, $this->fontPath, $payoutFormatted);

            // Multiplier
            imagettftext($img, 24, 0, 60, 320, $yellow, $this->fontPath, $multiplier . 'x multiplier');

            // Footer
            $refCode = $user['ref_code'] ?? '';
            $footer = 'betsvibe.online' . ($refCode ? ' | ref: ' . $refCode : '');
            imagettftext($img, 14, 0, 60, 385, $gray, $this->fontPath, $footer);

            // "BetVibe" branding top-right
            imagettftext($img, 22, 0, $width - 200, 50, $accent, $this->fontPath, '🎰 BetVibe');
        } else {
            // Fallback: use built-in fonts (font size 5)
            $bigFont = 5;
            imagestring($img, $bigFont, 60, 70, $user['username'] ?? 'Player', $white);
            imagestring($img, 4, 60, 110, 'played ' . $game, $gray);
            imagestring($img, $bigFont, 60, 180, 'Won NPR ' . number_format($payout, 0), $green);
            imagestring($img, $bigFont, 60, 230, $multiplier . 'x multiplier', $yellow);
            imagestring($img, 3, 60, 370, 'betsvibe.online', $gray);
        }

        // Save
        $filename = 'win_' . ($user['id'] ?? 0) . '_' . time() . '.png';
        $path = $this->storageDir . '/' . $filename;
        imagepng($img, $path);
        imagedestroy($img);

        return '/storage/win_cards/' . $filename;
    }

    /**
     * Get or generate win card for a bet
     */
    public function getOrGenerate(int $betId, int $userId): ?string
    {
        // Get bet details
        $bet = $this->db->first(
            "SELECT b.*, gc.display_name as game_name
             FROM bets b
             JOIN game_config gc ON gc.game_slug = b.game_slug
             WHERE b.id = ? AND b.user_id = ? AND b.result = 'win'",
            [$betId, $userId]
        );

        if (!$bet) {
            return null;
        }

        // Check if card should be generated
        if (!$this->shouldGenerate((float)$bet['multiplier'], (float)$bet['payout'])) {
            return null;
        }

        // Check if win card already exists (matching filename pattern)
        $pattern = $this->storageDir . '/win_' . $userId . '_' . $betId . '_*.png';
        $existing = glob($pattern);
        if (!empty($existing)) {
            $filename = basename($existing[0]);
            return '/storage/win_cards/' . $filename;
        }

        // Get user info
        $user = $this->db->first("SELECT id, username, ref_code FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return null;
        }

        // Generate the card (use betId in filename for caching)
        $url = $this->generate($user, $bet['game_name'], (float)$bet['multiplier'], (float)$bet['payout']);

        // Rename with betId for caching
        $oldPath = $this->storageDir . '/' . basename($url);
        $newFilename = 'win_' . $userId . '_' . $betId . '_' . time() . '.png';
        $newPath = $this->storageDir . '/' . $newFilename;
        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        return '/storage/win_cards/' . $newFilename;
    }
}

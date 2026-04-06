<?php
/**
 * Auto-approve withdrawals cron job
 * Runs every 30 minutes to process small withdrawals (< NPR 1000)
 * that have been pending for at least 2 hours
 */

// Load environment
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/App.php';

use App\Core\DB;
use App\Services\WatchPayService;

// Initialize database
$db = DB::getInstance();
$watchPayService = new WatchPayService($db);

// Find pending withdrawals eligible for auto-approval
// Criteria: status='pending', amount < 1000, requested_at > 2 hours ago
$withdrawals = $db->query(
    "SELECT * FROM withdrawal_requests 
    WHERE status = 'pending' 
    AND amount < 1000 
    AND requested_at < NOW() - INTERVAL 2 HOUR"
)->fetchAll();

if (empty($withdrawals)) {
    echo "No withdrawals to process.\n";
    exit(0);
}

echo "Found " . count($withdrawals) . " withdrawal(s) to process.\n";

$processed = 0;
$failed = 0;

foreach ($withdrawals as $withdrawal) {
    echo "Processing withdrawal ID: {$withdrawal->id} (NPR {$withdrawal->amount})...\n";

    try {
        $result = $watchPayService->processPayout($withdrawal->id);

        if ($result) {
            echo "✓ Withdrawal {$withdrawal->id} approved successfully.\n";
            $processed++;
        } else {
            echo "✗ Withdrawal {$withdrawal->id} failed.\n";
            $failed++;
        }
    } catch (\Exception $e) {
        echo "✗ Error processing withdrawal {$withdrawal->id}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nSummary:\n";
echo "Processed: {$processed}\n";
echo "Failed: {$failed}\n";
echo "Total: " . count($withdrawals) . "\n";

exit(0);

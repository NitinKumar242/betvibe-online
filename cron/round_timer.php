<?php
/**
 * Round Timer Cron Job
 * Manages game round timing and state transitions for timer-based games
 * Usage: php cron/round_timer.php {game_slug}
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Core\DB;
use App\Games\ColorPredictGame;
use App\Games\FastParityGame;

// Get game slug from command line argument
$gameSlug = $argv[1] ?? null;

if (!$gameSlug) {
    echo "Usage: php cron/round_timer.php {game_slug}\n";
    echo "Available games: color-predict, fast-parity\n";
    exit(1);
}

// Validate game slug
$validGames = ['color-predict', 'fast-parity'];
if (!in_array($gameSlug, $validGames)) {
    echo "Invalid game slug. Available games: " . implode(', ', $validGames) . "\n";
    exit(1);
}

// Connect to database
$db = DB::getInstance();

// Get game configuration
$gameConfig = getGameConfig($gameSlug);

// Process rounds
processRounds($db, $gameSlug, $gameConfig);

/**
 * Get game configuration
 */
function getGameConfig(string $gameSlug): array
{
    switch ($gameSlug) {
        case 'color-predict':
            return [
                'round_duration' => ColorPredictGame::getRoundDuration(),
                'betting_duration' => ColorPredictGame::getBettingDuration(),
                'lock_duration' => ColorPredictGame::getRoundDuration() - ColorPredictGame::getBettingDuration()
            ];
        case 'fast-parity':
            return [
                'round_duration' => FastParityGame::getRoundDuration(),
                'betting_duration' => FastParityGame::getBettingDuration(),
                'lock_duration' => FastParityGame::getRoundDuration() - FastParityGame::getBettingDuration()
            ];
        default:
            throw new \Exception("Unknown game: {$gameSlug}");
    }
}

/**
 * Process rounds for the game
 */
function processRounds($db, string $gameSlug, array $gameConfig)
{
    $now = new \DateTime();
    $nowStr = $now->format('Y-m-d H:i:s');

    // Check if there's an active round
    $activeRound = $db->query(
        "SELECT * FROM game_rounds 
         WHERE game_slug = ? AND status IN ('betting', 'locked') 
         ORDER BY id DESC LIMIT 1",
        [$gameSlug]
    )->first();

    if (!$activeRound) {
        // No active round, create a new one
        createNewRound($db, $gameSlug, $gameConfig, $now);
        echo "Created new round for {$gameSlug}\n";
        return;
    }

    // Check round status and time
    $roundStart = new \DateTime($activeRound['started_at']);
    $elapsed = $now->getTimestamp() - $roundStart->getTimestamp();

    if ($activeRound['status'] === 'betting' && $elapsed >= $gameConfig['betting_duration']) {
        // Lock the round
        lockRound($db, $activeRound['id'], $now);
        echo "Locked round {$activeRound['id']} for {$gameSlug}\n";
        broadcastRoundLocked($gameSlug, $activeRound['id']);
    } elseif ($activeRound['status'] === 'locked' && $elapsed >= $gameConfig['round_duration']) {
        // End the round and generate result
        endRound($db, $activeRound['id'], $gameSlug, $now);
        echo "Ended round {$activeRound['id']} for {$gameSlug}\n";
        broadcastRoundResult($gameSlug, $activeRound['id']);

        // Create new round
        createNewRound($db, $gameSlug, $gameConfig, $now);
        echo "Created new round for {$gameSlug}\n";
    }
}

/**
 * Create a new round
 */
function createNewRound($db, string $gameSlug, array $gameConfig, \DateTime $now)
{
    $roundEnd = clone $now;
    $roundEnd->add(new \DateInterval('PT' . $gameConfig['round_duration'] . 'S'));

    $db->query(
        "INSERT INTO game_rounds (game_slug, status, started_at, ends_at, result, created_at) 
         VALUES (?, 'betting', ?, ?, NULL, NOW())",
        [$gameSlug, $now->format('Y-m-d H:i:s'), $roundEnd->format('Y-m-d H:i:s')]
    );
}

/**
 * Lock a round (stop accepting bets)
 */
function lockRound($db, int $roundId, \DateTime $now)
{
    $db->query(
        "UPDATE game_rounds SET status = 'locked', locked_at = ? WHERE id = ?",
        [$now->format('Y-m-d H:i:s'), $roundId]
    );
}

/**
 * End a round and generate result
 */
function endRound($db, int $roundId, string $gameSlug, \DateTime $now)
{
    // Generate result based on game type
    $result = null;
    switch ($gameSlug) {
        case 'color-predict':
            $result = ColorPredictGame::generateResult();
            break;
        case 'fast-parity':
            $result = FastParityGame::generateResult();
            break;
    }

    // Update round with result
    $db->query(
        "UPDATE game_rounds 
         SET status = 'ended', result = ?, resolved_at = ? 
         WHERE id = ?",
        [json_encode($result), $now->format('Y-m-d H:i:s'), $roundId]
    );

    // Resolve all bets for this round
    resolveBets($db, $roundId, $result, $gameSlug);
}

/**
 * Resolve all bets for a round
 */
function resolveBets($db, int $roundId, array $result, string $gameSlug)
{
    // Get all pending bets for this round
    $bets = $db->query(
        "SELECT * FROM bets WHERE round_id = ? AND result = 'pending'",
        [$roundId]
    )->fetchAll();

    foreach ($bets as $bet) {
        $betData = json_decode($bet['bet_data'], true);
        $won = false;
        $multiplier = 0;
        $payout = 0;

        // Determine win based on game type and result
        switch ($gameSlug) {
            case 'color-predict':
                $won = ($betData['color'] === $result['color']);
                $multiplier = $won ? $result['multiplier'] : 0;
                $payout = $won ? $bet['bet_amount'] * $multiplier : 0;
                break;

            case 'fast-parity':
                $betType = $betData['type'];
                $resultNumber = $result['number'];

                if ($betType === 'number') {
                    $won = ($betData['value'] === $resultNumber);
                    $multiplier = $won ? 8.5 : 0;
                } else {
                    // Even or odd bet
                    if ($resultNumber === 0 || $resultNumber === 5) {
                        $won = false; // House wins
                    } else {
                        $isEven = ($resultNumber % 2 === 0);
                        $won = ($betType === 'even' && $isEven) || ($betType === 'odd' && !$isEven);
                    }
                    $multiplier = $won ? 1.9 : 0;
                }
                $payout = $won ? $bet['bet_amount'] * $multiplier : 0;
                break;
        }

        // Update bet
        $db->query(
            "UPDATE bets 
             SET result = ?, payout = ?, multiplier = ?, resolved_at = NOW() 
             WHERE id = ?",
            [$won ? 'win' : 'loss', $payout, $multiplier, $bet['id']]
        );

        // Credit win if applicable
        if ($won && $payout > 0) {
            $db->query(
                "UPDATE wallets SET balance = balance + ? WHERE user_id = ?",
                [$payout, $bet['user_id']]
            );

            // Insert transaction
            $db->query(
                "INSERT INTO transactions (user_id, type, amount, balance_type, status, reference_id, created_at) 
                 VALUES (?, 'win', ?, ?, 'completed', ?, NOW())",
                [$bet['user_id'], $payout, $bet['balance_type'], "bet_{$bet['id']}"]
            );
        }
    }
}

/**
 * Broadcast round locked event via WebSocket
 */
function broadcastRoundLocked(string $gameSlug, int $roundId)
{
    // This would integrate with the WebSocket server
    // For now, we'll just log it
    echo "Broadcasting round_locked for {$gameSlug} round {$roundId}\n";
}

/**
 * Broadcast round result via WebSocket
 */
function broadcastRoundResult(string $gameSlug, int $roundId)
{
    // This would integrate with the WebSocket server
    // For now, we'll just log it
    echo "Broadcasting round_result for {$gameSlug} round {$roundId}\n";
}

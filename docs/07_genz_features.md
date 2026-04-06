# Gen Z Retention Features — Implementation

## Win Streak System
```php
class StreakService {
    public function updateStreak(int $userId, string $result): array {
        $streak = DB::query("SELECT * FROM win_streaks WHERE user_id = ?", [$userId])->first();
        if (!$streak) {
            DB::query("INSERT INTO win_streaks (user_id) VALUES (?)", [$userId]);
            $streak = (object)['current_streak'=>0,'best_streak'=>0];
        }

        if ($result === 'win') {
            $newStreak = $streak->current_streak + 1;
            $best = max($newStreak, $streak->best_streak);
        } else {
            $newStreak = 0;
            $best = $streak->best_streak;
        }

        DB::query("UPDATE win_streaks SET current_streak=?, best_streak=?,
            last_result=? WHERE user_id=?", [$newStreak, $best, $result, $userId]);

        return ['streak' => $newStreak, 'milestone' => $this->getMilestone($newStreak)];
    }

    private function getMilestone(int $streak): ?string {
        return match(true) {
            $streak >= 10 => 'legendary',  // red border + auto share card
            $streak >= 5  => 'hot',        // orange border + live feed badge
            $streak >= 3  => 'fire',       // yellow border
            default       => null
        };
    }
}
```

## Win Share Card Generation
```php
class WinCardService {
    public function shouldGenerate(float $multiplier, float $payout): bool {
        return $multiplier >= 3.0 || $payout >= 500;
    }

    public function generate(User $user, string $game, float $multiplier, float $payout): string {
        $img = imagecreatetruecolor(800, 420);

        // Dark background
        $bg = imagecolorallocate($img, 13, 13, 13);
        imagefill($img, 0, 0, $bg);

        // Accent border
        $accent = imagecolorallocate($img, 127, 119, 221); // purple
        imagerectangle($img, 2, 2, 797, 417, $accent);

        // Text
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray  = imagecolorallocate($img, 160, 160, 160);
        $green = imagecolorallocate($img, 29, 158, 117);

        // Font path (load Roboto or similar)
        $font = __DIR__ . '/fonts/Roboto-Bold.ttf';

        imagettftext($img, 28, 0, 60, 100, $white, $font, $user->username);
        imagettftext($img, 18, 0, 60, 145, $gray, $font, "played " . $game);
        imagettftext($img, 72, 0, 60, 260, $green, $font, "NPR " . number_format($payout, 0));
        imagettftext($img, 24, 0, 60, 310, $white, $font, $multiplier . "x multiplier");
        imagettftext($img, 14, 0, 60, 380, $gray, $font, "betvibe.com | ref: " . $user->ref_code);

        // Save
        $filename = 'win_' . $user->id . '_' . time() . '.png';
        $path = storage_path('win_cards/' . $filename);
        imagepng($img, $path);
        imagedestroy($img);

        return '/storage/win_cards/' . $filename;
    }
}
```

## Daily Quests Engine
```php
// Quest conditions stored as JSON in daily_quests table
// Example conditions:
{ "type": "play_rounds", "game": "any", "count": 3 }
{ "type": "win_bets", "game": "color_predict", "count": 2 }
{ "type": "win_amount", "amount": 100 }
{ "type": "try_game", "game": "plinko" }
{ "type": "win_streak", "count": 5 }

// Progress update (called after every bet result)
class QuestService {
    public function updateProgress(int $userId, array $betResult): void {
        $quests = DB::query(
            "SELECT dq.*, COALESCE(uqp.progress,0) as progress, COALESCE(uqp.is_complete,0) as done
             FROM daily_quests dq
             LEFT JOIN user_quest_progress uqp ON uqp.quest_id=dq.id
             AND uqp.user_id=? AND uqp.date=CURDATE()
             WHERE dq.active_date=CURDATE()", [$userId]
        )->all();

        foreach ($quests as $quest) {
            if ($quest->done) continue;
            $condition = json_decode($quest->condition);
            $increment = $this->evaluate($condition, $betResult);
            if ($increment > 0) {
                $newProgress = $quest->progress + $increment;
                $isComplete = $newProgress >= $condition->count;
                DB::query("INSERT INTO user_quest_progress
                    (user_id,quest_id,progress,is_complete,completed_at,date)
                    VALUES (?,?,?,?,?,CURDATE())
                    ON DUPLICATE KEY UPDATE progress=?, is_complete=?, completed_at=?",
                    [$userId, $quest->id, $newProgress, $isComplete,
                     $isComplete ? date('Y-m-d H:i:s') : null,
                     $newProgress, $isComplete, $isComplete ? date('Y-m-d H:i:s') : null]
                );
                if ($isComplete) {
                    $this->grantReward($userId, $quest);
                }
            }
        }
    }
}
```

## Daily Login Reward
```php
// Reward tiers: Day 1=20, 2=30, 3=50, 4=75, 5=100, 6=150, 7=300coins+freespin
$rewards = [20, 30, 50, 75, 100, 150, 300];

function claimLoginReward(int $userId): array {
    // Get current streak
    $lastReward = DB::query(
        "SELECT * FROM login_rewards WHERE user_id=? ORDER BY given_at DESC LIMIT 1",
        [$userId])->first();

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($lastReward && $lastReward->given_at >= $today) {
        return ['already_claimed' => true];
    }

    $dayNum = 1;
    if ($lastReward && date('Y-m-d', strtotime($lastReward->given_at)) === $yesterday) {
        $dayNum = min(($lastReward->day_number % 7) + 1, 7);
    }

    $coins = $rewards[$dayNum - 1];
    DB::query("INSERT INTO login_rewards (user_id, day_number, coins_given) VALUES (?,?,?)",
        [$userId, $dayNum, $coins]);
    DB::query("UPDATE wallets SET bonus_coins = bonus_coins + ? WHERE user_id=?",
        [$coins, $userId]);

    return ['day' => $dayNum, 'coins' => $coins, 'free_spin' => $dayNum === 7];
}
```

## Live Win Feed
```php
// WebSocket broadcasts to all connected clients
// WinFeedService called after every win > NPR 50
class WinFeedService {
    public function broadcast(User $user, string $game, float $payout, float $multiplier): void {
        $maskedName = substr($user->username, 0, 2) . '***';
        $message = [
            'type'       => 'win_feed',
            'username'   => $maskedName,
            'game'       => $game,
            'payout'     => number_format($payout, 0),
            'multiplier' => $multiplier,
            'timestamp'  => time(),
        ];
        WebSocketServer::broadcast($message);
    }
}
```

## Leaderboard (Weekly Reset)
```sql
-- Daily leaderboard query
SELECT u.username, SUM(b.payout - b.bet_amount) as profit,
       COUNT(CASE WHEN b.result='win' THEN 1 END) as wins,
       MAX(b.multiplier) as best_multi
FROM bets b JOIN users u ON u.id = b.user_id
WHERE b.created_at >= CURDATE() AND b.result IN ('win','loss')
  AND b.balance_type = 'real'
GROUP BY b.user_id
ORDER BY profit DESC
LIMIT 10;

-- Weekly cron: reward top 3 real balance bonus
-- 1st: NPR 500, 2nd: NPR 200, 3rd: NPR 100
```

# Admin Panel + Telegram Bot

## Admin Panel Routes (separate subdomain: admin.betvibe.com)
```
GET  /admin/dashboard         — Revenue overview + live stats
GET  /admin/users             — User list with search/filter
GET  /admin/users/{id}        — User detail + bet history
POST /admin/users/{id}/ban    — Ban user
POST /admin/users/{id}/reset-password — Generate temp password
GET  /admin/withdrawals       — Pending queue
POST /admin/withdrawals/{id}/approve
POST /admin/withdrawals/{id}/reject
GET  /admin/games             — Game config list
POST /admin/games/{slug}/config — Update win_ratio, limits
GET  /admin/fraud             — Flagged users list
GET  /admin/audit             — Admin action log
GET  /admin/finance           — Revenue charts
```

## Dashboard Live Stats (auto-refresh every 30s)
```sql
-- Today stats
SELECT
  (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND DATE(created_at)=CURDATE()) as deposits_today,
  (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdraw' AND DATE(created_at)=CURDATE() AND status='completed') as withdrawals_today,
  (SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()) as new_users_today,
  (SELECT COUNT(*) FROM bets WHERE DATE(created_at)=CURDATE()) as bets_today,
  -- House profit = sum of losses - sum of wins (real bets only)
  (SELECT COALESCE(SUM(CASE WHEN result='loss' THEN bet_amount ELSE -payout END),0)
   FROM bets WHERE DATE(created_at)=CURDATE() AND balance_type='real') as house_profit_today
```

## Game Config Control
```php
// Admin updates win ratio
public function updateGameConfig(string $slug, array $data): void {
    DB::query("UPDATE game_config SET
        win_ratio = ?,
        min_bet = ?,
        max_bet = ?,
        is_enabled = ?
        WHERE game_slug = ?",
        [$data['win_ratio'], $data['min_bet'], $data['max_bet'],
         $data['is_enabled'], $slug]);

    // Log admin action
    AuditLog::write($adminId, 'update_game_config', 'game', $slug,
        "win_ratio changed to {$data['win_ratio']}");
}
```

## Password Reset by Admin
```php
public function resetUserPassword(int $userId): string {
    $tempPassword = bin2hex(random_bytes(4)); // 8 char hex
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    DB::query("UPDATE users SET password_hash=? WHERE id=?", [$hash, $userId]);
    AuditLog::write($adminId, 'reset_password', 'user', $userId, 'Password reset by admin');

    // Send via Telegram bot to user
    $user = DB::query("SELECT * FROM telegram_support_tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 1", [$userId])->first();
    if ($user && $user->telegram_id) {
        TelegramService::sendMessage($user->telegram_id,
            "🔑 Your temporary password: *{$tempPassword}*\n\nLogin at betvibe.com and change it immediately.");
    }

    return $tempPassword;
}
```

## Telegram Bot Setup
### Bot Commands (User-facing)
- `/start` — Welcome message + site link
- `/recover` — Start password recovery flow
- `/balance` — Check wallet (requires linked account)
- `/support` — Open support ticket

### Bot Commands (Admin, from admin group only)
- `/approve {withdrawal_id}` — Approve withdrawal
- `/reject {withdrawal_id} {reason}` — Reject withdrawal
- `/user {username}` — Quick user lookup
- `/stats` — Today's revenue summary

### Telegram Webhook Handler
```php
class TelegramWebhook {
    public function handle(array $update): void {
        $chatId   = $update['message']['chat']['id'];
        $text     = $update['message']['text'] ?? '';
        $username = $update['message']['from']['username'] ?? '';

        // Admin group commands
        if ($chatId == env('TELEGRAM_ADMIN_CHAT_ID')) {
            $this->handleAdminCommand($text, $chatId);
            return;
        }

        // User commands
        match(true) {
            str_starts_with($text, '/start')    => $this->cmdStart($chatId),
            str_starts_with($text, '/recover')  => $this->cmdRecover($chatId),
            str_starts_with($text, '/balance')  => $this->cmdBalance($chatId),
            str_starts_with($text, '/support')  => $this->cmdSupport($chatId),
            default => $this->handleConversation($chatId, $text),
        };
    }

    private function cmdRecover(string $chatId): void {
        // Set conversation state
        $this->setState($chatId, 'awaiting_username');
        TelegramService::sendMessage($chatId,
            "Please send your registered username on BetVibe:");
    }

    private function handleConversation(string $chatId, string $text): void {
        $state = $this->getState($chatId);
        if ($state === 'awaiting_username') {
            // Log ticket, notify admin
            DB::query("INSERT INTO telegram_support_tickets
                (telegram_id, type, message) VALUES (?,?,?)",
                [$chatId, 'password_recovery', $text]);

            TelegramService::sendToAdminGroup(
                "🔑 Password Recovery Request\nUsername: {$text}\nTelegram: {$chatId}"
            );

            TelegramService::sendMessage($chatId,
                "✅ Request submitted! Admin will reset your password within 24 hours.");
            $this->clearState($chatId);
        }
    }
}
```

### Admin Notifications Sent Automatically
| Event | Message |
|---|---|
| New withdrawal request | "💸 New withdrawal: NPR {amount} — User: {username}" |
| Fraud flag | "🚨 Fraud Alert: {username} — Reason: {reason}" |
| New user registered | Sent in bulk (daily summary, not per-user) |
| Leaderboard reset | "🏆 Weekly leaderboard reset. Top 3 rewarded." |
| Daily summary (11pm) | Revenue, deposits, withdrawals, new users, house profit |

## Anti-Fraud Auto-Detection
```php
class FraudDetectionService {
    public function runChecks(int $userId): void {
        // Check 1: Same IP multiple accounts
        $user = DB::query("SELECT last_ip FROM users WHERE id=?", [$userId])->first();
        $sameIpCount = DB::query(
            "SELECT COUNT(*) as c FROM users WHERE last_ip=? AND id!=?",
            [$user->last_ip, $userId])->first()->c;
        if ($sameIpCount >= 2) $this->flag($userId, 'multiple_accounts_same_ip');

        // Check 2: Deposit then immediate withdraw (no wagering)
        $wagered = DB::query(
            "SELECT total_wagered, real_balance FROM wallets WHERE user_id=?",
            [$userId])->first();
        // If they have balance but wagered < 10% of deposits — suspicious
        $totalDeposits = DB::query(
            "SELECT COALESCE(SUM(amount),0) as d FROM transactions
             WHERE user_id=? AND type='deposit'", [$userId])->first()->d;
        if ($totalDeposits > 500 && $wagered->total_wagered < $totalDeposits * 0.1) {
            $this->flag($userId, 'deposit_withdraw_no_wagering');
        }
    }

    private function flag(int $userId, string $reason): void {
        DB::query("UPDATE users SET fraud_flag=1, fraud_reason=? WHERE id=?",
            [$reason, $userId]);
        TelegramService::sendToAdminGroup(
            "🚨 Fraud Flag — User ID: {$userId} — {$reason}");
    }
}
```

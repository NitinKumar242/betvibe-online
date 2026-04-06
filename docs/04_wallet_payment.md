# Wallet + Payment System

## Two Balance Types (Always Separate)
| Type | Source | Withdrawable | Used First |
|---|---|---|---|
| Real Balance | Deposits + referral bonus + win on real bet | YES (after wagering req) | YES |
| Bonus Coins | Quests + daily spin + welcome bonus | NO | Only if real = 0 |

## Wagering Requirement
- User must wager 1x their deposit total before withdrawing
- Example: Deposit NPR 500 → must place NPR 500 in bets total → then can withdraw
- Tracked in wallets.total_wagered column
- Wager counts only on real balance bets, not bonus coin bets

## Deposit Flow (WatchPay)
```
User enters amount
→ POST /api/wallet/deposit { amount }
→ Server creates pending transaction record
→ Server calls WatchPay API: create_payment_intent(amount, currency=NPR, callback_url)
→ WatchPay returns checkout_url
→ Server returns checkout_url to frontend
→ Frontend redirects user to WatchPay checkout
→ User pays on WatchPay
→ WatchPay sends POST webhook to /api/webhooks/watchpay
→ Server verifies HMAC signature (WATCHPAY_WEBHOOK_SECRET)
→ Server updates transaction status=completed
→ Server credits wallets.real_balance += amount
→ Server checks referral: if user has pending referral → trigger referral_bonus
→ Server sends WebSocket event to user: { type: 'deposit_success', amount }
→ User sees balance update in real-time
```

### WatchPay Webhook Handler
```php
public function handleWebhook(Request $request): Response {
    $signature = $request->header('X-WatchPay-Signature');
    $payload = $request->rawBody();

    // Verify HMAC
    $expected = hash_hmac('sha256', $payload, env('WATCHPAY_WEBHOOK_SECRET'));
    if (!hash_equals($expected, $signature)) {
        return Response::json(['error' => 'Invalid signature'], 401);
    }

    $data = json_decode($payload);

    // Idempotency: check if already processed
    $existing = DB::query("SELECT id FROM transactions WHERE reference_id = ?",
        [$data->payment_id])->first();
    if ($existing) return Response::json(['ok' => true]);

    // Credit balance
    DB::transaction(function() use ($data) {
        DB::query("UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$data->amount, $data->user_id]);
        DB::query("UPDATE transactions SET status='completed', reference_id=? WHERE id=?",
            [$data->payment_id, $data->transaction_id]);
    });

    // Trigger referral check
    ReferralService::checkFirstDeposit($data->user_id, $data->amount);

    return Response::json(['ok' => true]);
}
```

## Withdrawal Flow
```
User submits: amount + WatchPay account number
→ Server validates:
   - amount >= NPR 500
   - amount <= real_balance
   - total_wagered >= deposit (wagering requirement met)
   - no other pending withdrawal exists
   - last deposit was >24 hours ago (cooldown)
   - withdrawal amount <= NPR 50,000/day
→ Server creates withdrawal_requests row (status=pending)
→ Server deducts amount from real_balance immediately (hold)
→ Server sends Telegram alert to admin group
→ If amount < NPR 1000: auto-approve queue (processed after 2hr)
→ If amount >= NPR 1000: wait for admin manual approval

Auto-approve job (runs every 30 min):
→ Find withdrawal_requests where status=pending AND amount < 1000
   AND requested_at < NOW() - INTERVAL 2 HOUR
→ Call WatchPay payout API
→ If WatchPay success: status=approved, notify user via Telegram
→ If WatchPay fail: status=failed, refund real_balance, notify user
```

## Bet Deduction / Payout Logic
```php
class WalletService {
    public function deductBet(int $userId, float $amount): string {
        $wallet = DB::query("SELECT * FROM wallets WHERE user_id = ? FOR UPDATE", [$userId])->first();

        if ($wallet->real_balance >= $amount) {
            DB::query("UPDATE wallets SET real_balance = real_balance - ?,
                total_wagered = total_wagered + ? WHERE user_id = ?",
                [$amount, $amount, $userId]);
            return 'real';
        } elseif ($wallet->bonus_coins >= $amount) {
            DB::query("UPDATE wallets SET bonus_coins = bonus_coins - ? WHERE user_id = ?",
                [$amount, $userId]);
            return 'bonus';
        } else {
            throw new InsufficientBalanceException();
        }
    }

    public function creditWin(int $userId, float $amount, string $balanceType): void {
        // Wins always go to real_balance regardless of balance_type used for bet
        DB::query("UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$amount, $userId]);
    }
}
```

## Transaction Types Reference
| Type | balance_type | Effect |
|---|---|---|
| deposit | real | +real_balance |
| withdraw | real | -real_balance |
| win | real | +real_balance |
| loss | real | (already deducted at bet time) |
| bonus | bonus | +bonus_coins |
| referral_bonus | real | +real_balance (5% of referred deposit) |

## Limits Summary
| Rule | Value |
|---|---|
| Min deposit | NPR 100 |
| Min withdrawal | NPR 500 |
| Max withdrawal/day | NPR 50,000 |
| Withdrawal cooldown | 24hr after first deposit |
| Auto-approve threshold | Under NPR 1,000 after 2hr |
| KYC required | Withdrawals above NPR 5,000 |
| Referral min deposit | NPR 200 (to trigger referral credit) |
| Referral bonus | 5% of referred user's first deposit |
| Wagering requirement | 1x deposit total |

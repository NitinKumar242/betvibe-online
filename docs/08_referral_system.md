# Referral System — Implementation

## Rules
- Credit triggers ONLY on referred user's FIRST deposit (min NPR 200)
- Signup alone = zero credit to referrer
- Referrer gets 5% of first deposit as REAL BALANCE (withdrawable)
- Referred user gets NPR 50 bonus coins as welcome gift
- Max referral earnings per day: NPR 2000 cap
- Anti-abuse: same IP / same device = blocked

## Database Flow
```sql
-- On registration with ref code
INSERT INTO referrals (referrer_id, referred_id, status) VALUES (?, ?, 'pending');

-- On first deposit of NPR 200+
UPDATE referrals SET status='converted', bonus_paid=?, converted_at=NOW()
WHERE referred_id=? AND status='pending';

UPDATE wallets SET real_balance = real_balance + ?
WHERE user_id = (SELECT referrer_id FROM referrals WHERE referred_id=?);

INSERT INTO transactions (user_id, type, amount, balance_type, note)
VALUES (referrer_id, 'referral_bonus', bonus_amount, 'real', 'Referral: username_of_referred');
```

## ReferralService.php
```php
class ReferralService {
    public static function checkFirstDeposit(int $userId, float $depositAmount): void {
        if ($depositAmount < 200) return; // min NPR 200 threshold

        $referral = DB::query(
            "SELECT * FROM referrals WHERE referred_id=? AND status='pending'",
            [$userId])->first();
        if (!$referral) return;

        // Check daily cap for referrer
        $todayEarned = DB::query(
            "SELECT COALESCE(SUM(amount),0) as total FROM transactions
             WHERE user_id=? AND type='referral_bonus' AND DATE(created_at)=CURDATE()",
            [$referral->referrer_id])->first()->total;
        if ($todayEarned >= 2000) return; // daily cap hit

        // Anti-abuse: check IPs
        $referrer = DB::query("SELECT last_ip FROM users WHERE id=?", [$referral->referrer_id])->first();
        $referred  = DB::query("SELECT last_ip FROM users WHERE id=?", [$userId])->first();
        if ($referrer->last_ip === $referred->last_ip) return; // same IP = blocked

        $bonus = min($depositAmount * 0.05, 2000 - $todayEarned);

        DB::transaction(function() use ($referral, $userId, $bonus, $depositAmount) {
            DB::query("UPDATE referrals SET status='converted', bonus_paid=?, converted_at=NOW()
                WHERE id=?", [$bonus, $referral->id]);
            DB::query("UPDATE wallets SET real_balance=real_balance+? WHERE user_id=?",
                [$bonus, $referral->referrer_id]);
            DB::query("UPDATE wallets SET bonus_coins=bonus_coins+50 WHERE user_id=?", [$userId]);
            DB::query("INSERT INTO transactions (user_id,type,amount,balance_type,note)
                VALUES (?,?,?,?,?)",
                [$referral->referrer_id,'referral_bonus',$bonus,'real','Referral deposit bonus']);
        });

        // Notify referrer via WebSocket
        WebSocketServer::sendToUser($referral->referrer_id, [
            'type' => 'referral_converted',
            'amount' => $bonus,
            'message' => "Your referral deposited! You earned NPR {$bonus} bonus."
        ]);
    }
}
```

## User Dashboard — Referral Section
```
Your referral link: betvibe.com/r/nitin99
[Copy Link] [Share on WhatsApp]

Stats:
Total referred:    12
Pending (no deposit): 4
Converted:         8
Total earned:      NPR 340

Recent referrals:
Ra*** — Converted — NPR 42 earned
Su*** — Pending — joined 2 days ago
```

## Anti-Abuse Checks Summary
| Check | Action |
|---|---|
| Same IP as referrer | Block referral credit silently |
| Same device fingerprint | Block referral credit silently |
| Deposit < NPR 200 | No credit triggered |
| Referrer daily cap NPR 2000 | Stop credit for day |
| Self-referral (own account) | Blocked at registration (can't use own ref_code) |
| Already converted referral | Ignore subsequent deposits |

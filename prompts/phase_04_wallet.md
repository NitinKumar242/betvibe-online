# Phase 4 — Wallet + WatchPay Integration

## Agent Instructions
Read these docs before starting:
- docs/04_wallet_payment.md
- docs/02_database_schema.md (wallets, transactions, withdrawal_requests)

---

## Prompt 4.1 — WalletService

```
Create /var/www/betvibe/app/Services/WalletService.php

Implement ALL methods from docs/04_wallet_payment.md:

getBalance(int $userId): array
- Returns ['real' => float, 'bonus' => float]
- Use SELECT ... FOR UPDATE if inside transaction

deductBet(int $userId, float $amount): string
- DB transaction with FOR UPDATE lock
- Use real first, then bonus
- Returns 'real' or 'bonus'
- Throws InsufficientBalanceException if neither enough
- Does NOT log transaction (bet logging is BetService's job)

creditWin(int $userId, float $amount, string $balanceType, int $betId): void
- Always credits real_balance (wins go to real)
- INSERT transaction row (type=win)
- Update total_wagered if balanceType was 'real'

checkWageringRequirement(int $userId): bool
- SELECT total_wagered, (SELECT SUM(amount) FROM transactions WHERE user_id=? AND type='deposit') as total_deposited
- Return total_wagered >= total_deposited

Create custom exceptions:
- app/Exceptions/InsufficientBalanceException.php
- app/Exceptions/WageringRequirementException.php
- app/Exceptions/WithdrawalPendingException.php
```

---

## Prompt 4.2 — WatchPay Integration

```
Create /var/www/betvibe/app/Services/WatchPayService.php

Implement:

createPayment(int $userId, float $amount): array
- Validate: amount >= 100 (min deposit)
- Create pending transaction in DB: INSERT transactions(user_id, type='deposit', amount, status='pending', gateway='watchpay')
- Call WatchPay API: POST https://api.watchpay.com/v1/payments (check WatchPay docs for exact endpoint)
  Headers: Authorization: Bearer {WATCHPAY_API_KEY}
  Body: { amount, currency: 'NPR', callback_url: APP_URL.'/api/webhooks/watchpay', metadata: {user_id, transaction_id} }
- Return: { checkout_url, transaction_id }

handleWebhook(string $rawBody, string $signature): bool
- Verify: hash_hmac('sha256', rawBody, WATCHPAY_WEBHOOK_SECRET) === signature
- Decode JSON body
- Idempotency: check transaction not already completed
- DB transaction:
  UPDATE wallets SET real_balance += amount WHERE user_id = ?
  UPDATE transactions SET status='completed', reference_id=payment_id WHERE id=?
- Call ReferralService::checkFirstDeposit(userId, amount)
- Send WebSocket event to user
- Return true

processPayout(int $withdrawalId): bool
- Get withdrawal details
- Call WatchPay payout API
- On success: update withdrawal status=approved
- On fail: restore balance, status=failed
- Notify user via Telegram bot

Create /var/www/betvibe/app/Controllers/WalletController.php:

POST /api/wallet/deposit
- Auth required
- Validate amount
- Call WatchPayService::createPayment()
- Return checkout_url

POST /api/webhooks/watchpay (NO auth — webhook from WatchPay)
- Raw body reading
- Call WatchPayService::handleWebhook()

GET /api/wallet/balance
- Auth required
- Return real + bonus balance

GET /api/wallet/transactions
- Auth required
- Paginated list (20 per page), filter by type
```

---

## Prompt 4.3 — Withdrawal System

```
Add to WalletController.php:

POST /api/wallet/withdraw
- Auth required
- Validate:
  amount >= 500 (min)
  amount <= 50000 (max/day: check today's withdrawals)
  amount <= real_balance
  checkWageringRequirement() === true → else 400 with wagering error
  no pending withdrawal exists (check withdrawal_requests table) → else 400
  last deposit > 24 hours ago → else 400 with cooldown message
  KYC verified if amount > 5000
- Deduct from real_balance immediately (hold)
- INSERT withdrawal_requests(user_id, amount, watchpay_account, status=pending)
- INSERT transaction(type=withdraw, status=pending)
- Send Telegram alert to admin group:
  "💸 New Withdrawal Request\nUser: {username}\nAmount: NPR {amount}\nWatchPay: {account}\nID: {withdrawal_id}"
- If amount < 1000: mark for auto-approval (scheduled job handles it)
- Return: {success:true, message:'Request submitted, processing within 2-24 hours'}

Create cron/auto_approve_withdrawals.php:
- Runs every 30 minutes
- Find: SELECT * FROM withdrawal_requests WHERE status='pending' AND amount < 1000 AND requested_at < NOW() - INTERVAL 2 HOUR
- For each: call WatchPayService::processPayout()

Add to AdminController.php:
GET /admin/withdrawals — list all pending
POST /admin/withdrawals/{id}/approve — manual approve + payout
POST /admin/withdrawals/{id}/reject — reject + restore balance
```

---

## Prompt 4.4 — Wallet UI

```
Create the Wallet page at /wallet (auth required):

Sections:

1. Balance Display (top of page):
   Real Balance: NPR 1,240
   Bonus Coins: 50 (not withdrawable)

2. Deposit Tab:
   Amount input with quick buttons: [NPR 100] [NPR 500] [NPR 1000] [NPR 2000]
   Custom amount input
   "Deposit Karo" button → POST /api/wallet/deposit → redirect to WatchPay checkout

3. Withdraw Tab:
   Amount input
   WatchPay account input
   Balance check display: "Available: NPR X | Wagering met: Yes/No"
   "Withdraw Request Bhejo" button
   Warning: "Minimum NPR 500 | Processing 2-24 hours"

4. Transaction History:
   Filter tabs: All | Deposits | Withdrawals | Wins | Losses | Bonuses
   Table: Date | Type | Amount | Status | Note
   Paginated, 20 per page

Style: dark theme. Real balance in green, bonus in purple, pending in amber, failed in red.
Mobile: tabs for deposit/withdraw/history are swipeable.
```

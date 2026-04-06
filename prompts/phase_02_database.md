# Phase 2 — Database Setup + Models

## Agent Instructions
Read these docs before starting:
- docs/02_database_schema.md
- docs/00_project_overview.md

---

## Prompt 2.1 — Create schema.sql

```
Create /var/www/betvibe/db/schema.sql with ALL tables from docs/02_database_schema.md.

Tables required (in this order to respect foreign keys):
1. admins
2. users
3. wallets
4. sessions
5. transactions
6. withdrawal_requests
7. game_config
8. game_rounds
9. bets
10. referrals
11. daily_quests
12. user_quest_progress
13. login_rewards
14. win_streaks
15. admin_audit_log
16. ip_blacklist
17. telegram_support_tickets
18. push_subscriptions

Include all indexes as specified in the schema doc.
Include DROP TABLE IF EXISTS before each CREATE TABLE.

Run the schema against betvibe_db:
mysql -u betvibe_user -p betvibe_db < /var/www/betvibe/db/schema.sql

Verify all 18 tables created successfully.
```

---

## Prompt 2.2 — Seed Game Config

```
Create /var/www/betvibe/db/seed_games.php

This script inserts all 16 games into game_config table:
- color_predict, fast_parity, crash, limbo, mines, plinko, dice_duel, keno,
  hilo, tower_climb, dragon_tiger, spin_wheel, coin_flip, roulette_lite,
  lucky_slots, number_guess

Use the exact values from docs/02_database_schema.md seed section.

Also seed:
- 1 super_admin account in admins table (username: admin, password: Admin@12345 bcrypt hashed)
- 1 test user account (username: testuser, real_balance: 1000 NPR for testing)

Run: php /var/www/betvibe/db/seed_games.php
Verify 16 rows in game_config, 1 admin, 1 test user.
```

---

## Prompt 2.3 — Base Model Class

```
Create /var/www/betvibe/app/Models/BaseModel.php:

Requirements:
- Uses app/Core/DB.php
- Methods: find($id), where($conditions), create($data), update($id, $data), delete($id)
- All methods use prepared statements
- Timestamps: created_at auto-set on create, updated_at on update

Create these Model classes (extend BaseModel):
- app/Models/User.php — users table
- app/Models/Wallet.php — wallets table
- app/Models/Transaction.php — transactions table
- app/Models/Bet.php — bets table
- app/Models/GameConfig.php — game_config table + getBySlug($slug) method
- app/Models/Referral.php — referrals table
- app/Models/Session.php — sessions table + cleanup expired sessions method

Each model must specify $table property and $fillable array.
```

---

## Prompt 2.4 — DB Helper Methods

```
Add these specific methods needed by the application:

In User.php:
- findByEmailOrPhone(string $identifier): ?User
- findByRefCode(string $code): ?User
- updateLoginInfo(int $id, string $ip, string $deviceFp): void
- incrementFailedAttempts(int $id): void
- resetFailedAttempts(int $id): void
- isLockedOut(int $id): bool  — check if failed_attempts >= 5 in last 15 min

In Wallet.php:
- getBalance(int $userId): array  — returns [real, bonus]
- deductBet(int $userId, float $amount): string  — returns 'real' or 'bonus'
- creditAmount(int $userId, float $amount, string $type): void
- addBonusCoins(int $userId, float $amount): void
- checkWageringRequirement(int $userId): bool

In GameConfig.php:
- getAllEnabled(): array
- updateConfig(string $slug, array $data): void

Test each method with a simple test script.
```

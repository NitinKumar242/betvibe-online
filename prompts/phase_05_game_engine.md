# Phase 5 — RNG Engine + Game Base Architecture

## Agent Instructions
Read these docs before starting:
- docs/05_rng_engine.md
- docs/06_all_games_mechanics.md
- docs/02_database_schema.md (bets table)

---

## Prompt 5.1 — RNGService

```
Create /var/www/betvibe/app/Services/RNGService.php

Implement all methods from docs/05_rng_engine.md:

randInt(int $min, int $max): int
- Uses PHP random_int() — cryptographically secure
- NEVER use rand() or mt_rand()

randFloat(): float
- Returns float between 0.0 and 1.0
- Uses random_int(0, PHP_INT_MAX) / PHP_INT_MAX

weightedRandom(array $weights): string
- Input: ['red'=>45, 'green'=>45, 'violet'=>10]
- Uses randInt on total sum, walks cumulative
- Returns the winning key

crashMultiplier(float $houseEdge = 0.04): float
- 4% chance of instant 1.00 (immediate crash)
- Otherwise: inverse CDF distribution
- Returns capped at 1000.00
- Rounded to 2 decimal places

generateMines(int $gridSize, int $mineCount): array
- Generates array of mine positions (0 to gridSize-1)
- Uses Fisher-Yates shuffle with random_int
- Returns array of $mineCount unique positions

generateSlotReel(array $weights): string
- Same as weightedRandom
- Returns symbol name

Add phpUnit test cases:
- Test weightedRandom distribution (run 10000 times, check within 2% of expected)
- Test crashMultiplier never returns < 1.00
- Test generateMines returns exactly $mineCount unique values within range
```

---

## Prompt 5.2 — BetService (Core Bet Engine)

```
Create /var/www/betvibe/app/Services/BetService.php

This is the central bet processing class — all games go through it.

placeBet(int $userId, string $gameSlug, float $amount, array $betData): array
Steps:
1. Load game config: GameConfig::getBySlug($gameSlug) — check is_enabled, min_bet, max_bet
2. Validate amount within limits
3. Load user, check not banned
4. Call WalletService::deductBet(userId, amount) — get balanceType
5. INSERT bets row: status=pending, bet_data=JSON
6. Call the specific game handler: GameFactory::resolve($gameSlug)->play($betData, $amount)
7. Get result: ['result'=>'win|loss', 'multiplier'=>float, 'payout'=>float, 'game_data'=>array]
8. If win: call WalletService::creditWin(userId, payout, balanceType)
9. UPDATE bets: result, payout, multiplier, resolved_at
10. INSERT transaction (win or loss)
11. Call StreakService::updateStreak(userId, result)
12. Call QuestService::updateProgress(userId, betResult)
13. Call XPService::award(userId, amount, result)
14. If win: call WinFeedService::broadcast() + WinCardService::shouldGenerate()
15. Return full result object to controller

Create /var/www/betvibe/app/Games/GameFactory.php:
- static resolve(string $slug): GameInterface
- Returns correct game class instance
- Maps all 16 slugs to classes

Create /var/www/betvibe/app/Games/GameInterface.php:
interface GameInterface {
    public function play(array $betData, float $betAmount): array;
    // Must return: ['result'=>'win|loss', 'multiplier'=>float, 'payout'=>float, 'game_data'=>array]
}
```

---

## Prompt 5.3 — Games Base Controller

```
Create /var/www/betvibe/app/Controllers/GameController.php

POST /api/games/{slug}/play (all instant games)
- Auth required
- Parse slug from URL
- Validate game exists and is enabled
- Call BetService::placeBet(userId, slug, amount, betData)
- Return result JSON

POST /api/games/{slug}/start (multi-step games: mines, tower, hilo)
- Creates game session, returns initial state

POST /api/games/{slug}/action (multi-step: reveal tile, pick direction, climb floor)
- Validates active session belongs to this user
- Processes action, returns updated state

POST /api/games/{slug}/cashout (multi-step: cash out current multiplier)
- Finalizes multi-step game, credits win

GET /api/games/{slug}/history
- Auth required
- Last 20 bets for this user on this game
- Returns: [{result, amount, payout, multiplier, created_at, game_data}]

GET /api/games (public)
- Returns list of all enabled games with display_name, min_bet, max_bet

Create active_game_sessions table (add to schema):
CREATE TABLE active_game_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED UNIQUE,
  game_slug   VARCHAR(30),
  bet_id      BIGINT UNSIGNED,
  state       JSON,
  expires_at  DATETIME,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- expires_at = NOW() + 10 minutes (auto-cashout on expire)
```

---

## Prompt 5.4 — XP + Level System

```
Create /var/www/betvibe/app/Services/XPService.php

XP Award Rules:
- Every bet placed: +5 XP
- Win: +10 XP
- Win with multiplier >= 5x: +25 XP
- Win streak milestone (3/5/10): +50/100/200 XP
- Complete daily quest: (already handled by QuestService)

Level thresholds (linear scaling):
Level 1: 0 XP | Level 2: 200 | Level 3: 500 | Level 4: 900
Level 5: 1400 | Level 6: 2000 | Level 7: 2800 | Level 8: 3800
Level 9: 5000 | Level 10: 6500 | ...continues to Level 20

award(int $userId, float $betAmount, string $result, float $multiplier = 0, int $streakMilestone = 0): array
- Calculate XP to add
- UPDATE users SET xp = xp + ?, level = calculateLevel(new_xp) WHERE id=?
- If level changed: return level_up event to broadcast
- Return: {xp_gained, new_xp, new_level, level_up: bool}

calculateLevel(int $xp): int
- Binary search through thresholds array
- Returns level 1-20

Avatar IDs to unlock at levels:
Level 1: avatars 1-3 (default)
Level 5: avatars 4-6
Level 10: avatars 7-10
Level 15: avatars 11-15
Level 20: avatar 16 (legendary)

Store avatar list in config/avatars.php
```

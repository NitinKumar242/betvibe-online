# Phase 6 — All 16 Games Implementation

## Agent Instructions
Read these docs before starting:
- docs/06_all_games_mechanics.md
- docs/05_rng_engine.md
- prompts/phase_05_game_engine.md (BetService must be done first)

---

## Prompt 6.1 — Batch 1: Color Predict + Fast Parity (Timer-Based)

```
These are timer-based games managed by cron, not instant games.

Create /var/www/betvibe/app/Games/ColorPredict.php:

ROUND LIFECYCLE:
1. Cron runs every 3 min: creates new round in game_rounds table
2. Round has 3 states: betting (first 2.5 min), locked (last 30 sec), ended
3. During betting: players place bets via POST /api/games/color-predict/bet
4. At 30s remaining: WebSocket broadcasts round_locked event
5. At 0:00: Server calls ColorPredict::generateResult() → weightedRandom
6. Result saved to game_rounds.result
7. All pending bets for this round resolved (win/loss credited)
8. WebSocket broadcasts round_result to all

Create cron/round_timer.php:
- Accepts game slug as argument
- For color_predict: 180 second rounds
- For fast_parity: 60 second rounds
- Manages round creation, locking, result generation
- Resolves all bets after result

API endpoints:
POST /api/games/color-predict/bet { round_id, color, amount }
- Validate: current round is in betting phase, not locked
- Standard bet deduction
- INSERT bet with round_id, bet_data={color}
- Return: {success, bet_id, round_ends_at}

GET /api/games/color-predict/current-round
- Returns active round data: id, started_at, ends_at, phase (betting/locked/ended)
- Returns last 10 round results for history display

Same pattern for Fast Parity — options: even/odd/specific_number (0-9).
Fast Parity hidden rule: 0 and 5 result = house wins all even/odd bets.
```

---

## Prompt 6.2 — Batch 2: Crash + Limbo (Multiplier Games)

```
Create /var/www/betvibe/app/Games/Crash.php:

Crash is WebSocket-driven. No round_timer cron needed.

CRASH ROUND:
1. Server starts round: generates crash_point using RNGService::crashMultiplier()
2. WebSocket server manages round: broadcasts multiplier every 100ms
3. Players can bet during waiting phase (between rounds)
4. Once round starts, bets locked
5. Players click cashout → server validates they're in time → credit win
6. If player doesn't cashout before crash_point → loss
7. Round ends → result broadcast → 5 second pause → new round

Create /var/www/betvibe/websocket/CrashGame.php:
- Extends Ratchet\MessageComponentInterface
- Manages active connections, active bets, current multiplier
- Broadcasts: tick (every 100ms), cashout_accepted, round_start, round_end

POST /api/games/crash/bet { amount, auto_cashout: float|null }
- Only during waiting phase
- auto_cashout = automatic cashout at X multiplier (optional)

POST /api/games/crash/cashout
- User manually cashes out
- Server validates: round in progress, user has active bet, hasn't already cashed out
- Credit win: amount * current_multiplier

Create /var/www/betvibe/app/Games/Limbo.php (implements GameInterface):
play(array $betData, float $betAmount): array
- betData = { target_multiplier: 2.5 }
- Validate: target_multiplier >= 1.01 and <= 1000
- result = RNGService::crashMultiplier(0.03)  // 3% house edge
- win = result >= target_multiplier
- payout = win ? betAmount * target_multiplier : 0
- Return standard result array
```

---

## Prompt 6.3 — Batch 3: Mines + Tower Climb (Multi-Step Grid Games)

```
Create /var/www/betvibe/app/Games/Mines.php:

POST /api/games/mines/start { bet_amount, mine_count }
- Validate mine_count: 1-24
- Deduct bet from wallet
- Generate mine positions: RNGService::generateMines(25, mine_count)
- Store in active_game_sessions: { mine_positions, mines_revealed:[], safe_revealed:[], current_multiplier:1.0, mine_count }
- Return: { session_id, grid_size:25, mine_count } — DO NOT reveal positions

POST /api/games/mines/reveal { tile_index }
- Load active session, validate ownership
- Check tile_index not already revealed
- If mine: end game, loss, reveal all mines in response, clear session
- If safe: calculate new multiplier using formula from docs/06_all_games_mechanics.md
- Update session: add to safe_revealed, update multiplier
- Return: { is_mine:false, multiplier:1.24, can_cashout:true, revealed_positions:[] }

POST /api/games/mines/cashout
- Load session, validate has at least 1 safe tile revealed
- Credit win: bet_amount * current_multiplier
- Reveal all mine positions
- Clear session
- Return: { payout, multiplier, mine_positions }

Create /var/www/betvibe/app/Games/TowerClimb.php (same pattern):
- Floors 1-12, each floor has increasing tile count (1 safe among N)
- Floor 1: 1/2, Floor 2: 1/3, Floor 3: 1/4... Floor 6+: 1/5
- Multiplier table pre-calculated per floor
- API: /start, /pick-tile { floor, tile_index }, /cashout
```

---

## Prompt 6.4 — Batch 4: Dice, HiLo, Dragon Tiger, Keno (Card/Number Games)

```
Create /var/www/betvibe/app/Games/DiceDuel.php (implements GameInterface):
play(array $betData, float $betAmount): array
- betData = { direction:'over'|'under', threshold:50 } (threshold 2-98)
- Validate threshold range
- roll = RNGService::randInt(1, 100)
- win = (direction=over AND roll > threshold) OR (direction=under AND roll < threshold)
- multiplier = direction=over ? (99/(100-threshold)) : (99/threshold)
- payout = win ? betAmount * multiplier : 0
- game_data = { roll, direction, threshold }

Create /var/www/betvibe/app/Games/HiLo.php (multi-step):
POST /api/games/hilo/start { bet_amount }
- Generate virtual deck: array 2-14 (14=Ace), shuffled
- Store in session: deck, position:0, multiplier:1.0, history:[]
- Return first card (deck[0])

POST /api/games/hilo/pick { direction:'higher'|'lower' }
- Current card = deck[position], next = deck[position+1]
- win = (higher AND next > current) OR (lower AND next < current)
- If equal: push (keep bet, reveal, pick again)
- win: multiplier *= payout_factor(current, direction) — see docs for formula
- lose: full loss, reveal result, clear session
- Return: { won, next_card, current_multiplier, can_cashout }

POST /api/games/hilo/cashout → credit win

Create /var/www/betvibe/app/Games/DragonTiger.php (implements GameInterface):
play(array $betData, float $betAmount): array
- betData = { pick: 'dragon'|'tiger'|'tie' }
- dragon_card = randInt(2,14), tiger_card = randInt(2,14)
- result = dragon > tiger ? 'dragon' : (tiger > dragon ? 'tiger' : 'tie')
- win = betData.pick === result
- multiplier = pick=tie ? 8 : 1.95 (tie bet also wins 8x if picked)
- Special: if dragon===tiger AND pick!=='tie': return half bet (push)
- game_data = { dragon_card, tiger_card, result }

Create /var/www/betvibe/app/Games/Keno.php (implements GameInterface):
play(array $betData, float $betAmount): array
- betData = { picks: [3,7,12,22,35] } (1-5 picks, values 1-40)
- Validate picks array (1-10 values, each 1-40, no duplicates)
- Generate 20 draw numbers from 1-40 (shuffle, take first 20)
- matches = count(intersection of picks and drawn)
- Look up payout table (stored in game config JSON)
- game_data = { drawn_numbers, picks, matches }
```

---

## Prompt 6.5 — Batch 5: Spin Wheel, Coin Flip, Roulette, Slots, Number Guess, Plinko

```
Create these 6 simple instant games (all implement GameInterface):

/app/Games/SpinWheel.php:
- 52 segments array as defined in docs/05_rng_engine.md
- resultIndex = RNGService::randInt(0, 51)
- multiplier = segments[resultIndex]
- win = multiplier > 0 (always win something, but <1x = net loss)
- payout = betAmount * multiplier
- game_data = { result_index, multiplier } — frontend animates to this segment

/app/Games/CoinFlip.php:
- result = RNGService::randInt(0,1) — 0=heads, 1=tails
- win = betData.pick === (result ? 'tails' : 'heads')
- multiplier = 1.9 (win) or 0 (loss)
- game_data = { result: 'heads'|'tails' }

/app/Games/RouletteLite.php:
- number = RNGService::randInt(0, 36)
- color = number===0 ? 'green' : (redNumbers.includes(number) ? 'red' : 'black')
- Evaluate bet type: red/black/odd/even/high/low/zero
- Zero: all even-money bets lose
- Multipliers: red/black/odd/even/high/low = 1.9x, zero = 17x
- game_data = { number, color }

/app/Games/LuckySlots.php:
- 3 reels, each picks symbol via weightedRandom
- Near-miss: if reels 1&2 match on 'seven' or 'diamond', reel 3 picks adjacent symbol (not matching)
- Evaluate 5 paylines (3 rows + 2 diagonals — simplified: just middle row for v1)
- Payout table lookup from config/games.php
- game_data = { reels: ['seven','cherry','lemon'] }

/app/Games/NumberGuess.php:
- betData = { type: 'exact'|'range', value: 5 | range_id: 'low'|'mid'|'high' }
- result = RNGService::randInt(1,10)
- Exact match: payout 8x; Range 1-3: 2.5x; 4-7: 1.8x; 8-10: 2.5x
- game_data = { result }

/app/Games/Plinko.php:
- betData = { risk: 'low'|'medium'|'high' }
- multiplier distributions from docs/06_all_games_mechanics.md
- slot = RNGService::randInt(0, 12)
- multiplier = riskMultipliers[betData.risk][slot]
- game_data = { slot, animation_path: generatePath(slot) } — path for ball animation
```

---

## Prompt 6.6 — Game Pages Frontend (All 16)

```
Create frontend game pages. Each game at /games/{slug}.

Common layout for ALL games:
- Dark theme (#0D0D0D bg)
- Header: back button, game name, balance display
- Game visual area (game-specific, see below)
- Bet panel: amount input + quick buttons + game options + BET button
- Recent bets table (last 10, auto-refreshes)

Game-specific visuals using GSAP:

Color Predict / Fast Parity:
- Countdown timer (large, center)
- 3 colored buttons (Red=red, Green=green, Violet=purple)
- Result history bar (last 10 results as colored circles)
- Multiplier shown on each button

Crash:
- SVG graph: x-axis=time, y-axis=multiplier, growing green line
- Multiplier counter (large number, changes color at 5x=green, 20x=amber)
- Cashout button (red, prominent)
- Connected players list showing their bet amounts

Mines:
- 5x5 CSS grid
- Each tile: default=dark card, revealed safe=green gem, mine=red explosion
- Current multiplier and potential win shown
- Mine count selector (slider 1-24 before game starts)

Spin Wheel:
- Canvas-drawn wheel (52 segments, colored by value range)
- GSAP rotation animation to result segment
- Multiplier display after spin

Coin Flip:
- Large 3D CSS coin (CSS transform rotationY animation)
- Heads/Tails buttons
- Flip animation on bet

Crash graph, Mines grid, and Plinko board are the most complex — implement with Canvas API or SVG.

All bet submissions: fetch POST → show loading state → animate result → show win/loss toast.
Win toast: green, "+NPR 240", confetti particles.
Loss toast: red, "-NPR 100", screen shake 0.3s.
```

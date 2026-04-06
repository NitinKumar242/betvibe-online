# All 16 Games — Mechanics Reference

## Game 1: Color Predict
- **Type:** Timer-based round (3 min)
- **Bet Options:** Red (1.95x) | Green (1.95x) | Violet (4.5x)
- **RNG:** weightedRandom(['red'=>45,'green'=>45,'violet'=>10])
- **API:** POST /api/games/color-predict/bet { color, amount }
- **Round lifecycle:** cron starts round → 30s before end betting locked → result announced → bets resolved

## Game 2: Fast Parity
- **Type:** Timer-based round (1 min)
- **Bet Options:** Even (1.9x) | Odd (1.9x) | Specific Number 0-9 (8.5x)
- **Hidden Rule:** 0 and 5 = house wins all even/odd bets
- **RNG:** random_int(0,9) — house wins on 0 and 5 for even/odd

## Game 3: Crash
- **Type:** Instant (player-controlled cashout)
- **Mechanic:** Growing multiplier, player clicks cashout, server has pre-set crash point
- **RNG:** crashMultiplier() — see RNG engine doc
- **API:** WS connection required for real-time multiplier ticks
- **States:** waiting → in-progress → crashed

## Game 4: Limbo
- **Type:** Instant
- **Mechanic:** Player sets target multiplier → server generates result → win if result >= target
- **Payout:** exactly target multiplier (if win)
- **RNG:** same crash distribution, player sets own risk
- **Win prob:** 1/target * 0.97 (3% house edge)

## Game 5: Mines
- **Type:** Instant (multi-step)
- **Grid:** 5x5 = 25 tiles
- **Setup:** Player picks mine count (1-24), places bet
- **Mechanic:** Server pre-generates mine positions. Player reveals tiles one by one.
  - Safe tile: multiplier increases, player can cashout
  - Mine tile: lose entire bet
- **Multiplier formula per safe tile:**
  `multiplier = prev_multiplier * ((25 - mines - safe_so_far) / (25 - safe_so_far)) * 0.97`
- **API:** POST /api/games/mines/start → POST /api/games/mines/reveal {tile_index} → POST /api/games/mines/cashout

## Game 6: Plinko
- **Type:** Instant
- **Grid:** 12 rows, 13 slots
- **Risk modes:** Low | Medium | High (different multiplier distributions)
- **Mechanics:** Server picks slot via weighted RNG. Frontend animates ball purely cosmetically.
- **Low risk multipliers:** [0.5, 0.7, 1.0, 1.0, 1.0, 1.5, 1.0, 1.0, 1.0, 0.7, 0.5, 0.3, 0.2]
- **High risk multipliers:** [1000, 10, 3, 1, 0.5, 0.3, 0.2, 0.3, 0.5, 1, 3, 10, 1000]

## Game 7: Keno
- **Type:** Instant
- **Mechanic:** Player picks 1-10 numbers from 1-40. System draws 20 numbers. Payout by matches.
- **Payout table (pick 5 example):**
  - 0 match: 0x | 1: 0x | 2: 0.5x | 3: 2x | 4: 10x | 5: 50x
- **RNG:** random shuffle of 1-40, take first 20

## Game 8: Tower Climb
- **Type:** Instant (multi-step)
- **Mechanic:** Each floor has N tiles (increases per floor), 1 is safe.
  - Floor 1: 1 of 2 tiles safe (50%) | Floor 2: 1 of 3 (33%) | Floor 3: 1 of 4 (25%)...
- **Payout:** multiplier grows per floor cleared. Cashout anytime.
- **Full loss:** picking wrong tile loses entire bet
- **API:** Same pattern as Mines (start → pick → cashout)

## Game 9: Dice Duel
- **Type:** Instant
- **Mechanic:** Roll 1-100. Player bets Over/Under a threshold they set (default 50.5).
- **Payout:** 99/(100-threshold) for Over | 99/threshold for Under
- **Slider UI:** player drags threshold, win% and multiplier shown live

## Game 10: HiLo Cards
- **Type:** Instant (chain)
- **Mechanic:** Card shown (2-Ace). Player bets: next card Higher or Lower.
  Correct = multiplier up, keep going or cashout. Wrong = lose all.
- **Payout per correct step:** slightly below true probability
- **Deck:** virtual 52 cards, reshuffled each chain start
- **API:** POST /start → POST /pick {direction} → POST /cashout

## Game 11: Dragon Tiger
- **Type:** Instant (timer round optional or instant)
- **Mechanic:** Two cards dealt face-up: Dragon vs Tiger. Bet which is higher (or Tie).
- **Payouts:** Dragon/Tiger = 1.95x | Tie = 8x (true ~11.7x → massive house edge)
- **Equal cards:** counts as Tie, non-Tie bets lose half

## Game 12: Spin Wheel
- **Type:** Instant
- **Mechanic:** 52-segment weighted wheel. Server picks segment, GSAP animates cosmetically.
- **Segments:** See RNG engine doc for distribution
- **Animation:** wheel spins to correct segment position (calculated from result after bet)

## Game 13: Coin Flip
- **Type:** Instant
- **Mechanic:** Heads or Tails. Win = 1.9x.
- **RNG:** random_int(0,1) with 50/50 probability
- **Speed:** fastest game — no complex UI, 3s animation

## Game 14: Roulette Lite
- **Type:** Instant
- **Bet types:** Red(1.9x) | Black(1.9x) | Zero(17x) | Odd(1.9x) | Even(1.9x) | High(1.9x) | Low(1.9x)
- **RNG:** random_int(0,36)
- **Zero rule:** all even-money bets (red/black/odd/even/high/low) lose on 0

## Game 15: Lucky Slots
- **Type:** Instant
- **Mechanic:** 3 reels, 5 paylines. Each reel independently picks symbol via weighted RNG.
- **Near-miss:** if 2 high symbols match, 3rd reel biased toward adjacent non-matching symbol
- **Payout table:**
  - 3× diamond = 100x | 3× seven = 30x | 3× grape = 10x
  - 3× orange = 5x | 3× lemon = 3x | 3× cherry = 2x
  - 2× diamond = 3x | all other 2× match = 0.5x
  - No match = 0x

## Game 16: Number Guess
- **Type:** Instant
- **Mechanic:** Pick exact number 1-10 = 8x (true 10x) OR pick range:
  - 1-3 = 2.5x | 1-5 = 1.5x | 6-10 = 1.5x | 8-10 = 2.5x
- **RNG:** random_int(1,10)

## Common API Pattern (Instant Games)
```
POST /api/games/{slug}/play
Body: { bet_amount: 100, bet_data: { /* game specific */ } }
Auth: session cookie required

Response:
{
  "result": "win|loss",
  "multiplier": 2.5,
  "payout": 250,
  "game_data": { /* revealed state */ },
  "new_balance": 1250.00,
  "streak": { "count": 3, "type": "win" },
  "xp_gained": 10
}
```

## Multi-Step Game State (Mines, Tower, HiLo)
- Session state stored in PHP session or DB (active_game_sessions table)
- State includes: bet_amount, mines_positions (hidden), tiles_revealed, current_multiplier
- State locked to user — cannot start 2 active sessions on same game
- Abandoning game (close browser) = auto-cashout on reconnect, or auto-loss after 10 min timeout

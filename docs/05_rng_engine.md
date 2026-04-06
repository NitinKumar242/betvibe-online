# RNG Engine + House Edge System

## Core Principle
ALL game results are generated SERVER-SIDE before client sees anything.
Client sends bet → server computes result → server returns outcome.
Never trust client for any game logic.

## RNG Service
```php
class RNGService {
    /**
     * Cryptographically secure random integer
     */
    public static function randInt(int $min, int $max): int {
        return random_int($min, $max);
    }

    /**
     * Random float 0.0 to 1.0
     */
    public static function randFloat(): float {
        return random_int(0, PHP_INT_MAX) / PHP_INT_MAX;
    }

    /**
     * Weighted random pick
     * $weights = ['red' => 45, 'green' => 45, 'violet' => 10]
     */
    public static function weightedRandom(array $weights): string {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $cumulative = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $key;
        }
        return array_key_last($weights);
    }

    /**
     * Generate Crash multiplier using provably fair method
     */
    public static function crashMultiplier(float $houseEdge = 0.04): float {
        $r = self::randFloat();
        if ($r < $houseEdge) return 1.00; // instant crash 4% of time
        return max(1.00, (1 / (1 - $r)) * (1 - $houseEdge));
    }
}
```

## House Edge Implementation Per Game

### Color Predict
- True probability Red/Green = 50%, payout should be 2x
- Actual: Red/Green = 1.95x, Violet = 4.5x (true = 10x if 10%)
- House edge: ~5% on colors, ~55% on violet
- RNG weights: Red=45, Green=45, Violet=10

### Fast Parity
- Numbers 0-9, Even/Odd should pay 2x (50% chance)
- BUT: 0 and 5 are "house numbers" — even/odd bets lose on 0 and 5
- Actual even/odd win prob = 40% (4 even + 4 odd out of 10, 0 and 5 = house)
- Payout = 1.95x → house edge ~22%

### Crash
```php
public static function crashMultiplier(): float {
    // 4% instant crash (house takes all)
    if (random_int(1, 100) <= 4) return 1.00;
    // Weighted distribution — most crashes happen early
    $r = self::randFloat();
    $multiplier = 0.99 / (1 - $r); // inverse CDF
    return min(round($multiplier, 2), 1000.00);
}
```

### Mines
```php
public static function generateMines(int $gridSize, int $mineCount): array {
    $positions = range(0, $gridSize - 1);
    shuffle($positions); // uses mt_rand internally — replace with random_int shuffle
    return array_slice($positions, 0, $mineCount);
}
// Payout formula: after k safe tiles with m mines on 25 grid:
// multiplier = product of (25-m-i)/(25-i) for i=0..k-1, times 0.97 (house edge)
```

### Dice Duel
- Player bets Over X (1-100)
- Win probability = (100-X)/100
- Fair payout = 100/(100-X)
- Actual payout = 99/(100-X) — house keeps 1%

### Spin Wheel
```php
// 52 segments with defined multipliers
$segments = [
    0.2, 0.2, 0.5, 0.2, 0.2, 0.5, 0.2, 0.2, 0.5, 0.2,  // 10
    0.2, 0.5, 0.2, 0.2, 0.5, 0.2, 0.2, 1.5, 0.2, 0.2,  // 20
    0.5, 0.2, 0.2, 0.5, 0.2, 1.5, 0.2, 0.2, 0.5, 0.2,  // 30
    0.2, 1.5, 0.2, 0.2, 3.0, 0.2, 0.2, 1.5, 0.2, 0.2,  // 40
    0.5, 0.2, 1.5, 0.2, 3.0, 0.2, 1.5, 0.2, 3.0, 5.0,  // 50
    3.0, 50.0                                              // 52
];
// Expected value = sum(segments)/52 = ~0.79 → house edge ~21%
$resultIndex = random_int(0, 51);
$multiplier = $segments[$resultIndex];
```

### Lucky Slots
```php
$symbols = ['cherry','lemon','orange','grape','seven','diamond'];
// Weighted per reel
$weights = [
    'cherry'  => 35,
    'lemon'   => 25,
    'orange'  => 20,
    'grape'   => 12,
    'seven'   => 6,
    'diamond' => 2,
];
// Near-miss illusion: if 2 reels match on high symbol, 3rd reel picks
// deliberately near (adjacent index) but not matching
```

### Roulette Lite
- 37 numbers (0-36): 18 red, 18 black, 1 green(0)
- Red/Black pays 1.9x (true = 2x) → house edge = 5% + zero sweeps
- Zero: all red/black/odd/even bets lose → massive edge on zero

## Game Config Driven Edge
Admin can adjust win_ratio in game_config table.
RNG engine reads this value to scale results.
```php
$config = DB::query("SELECT * FROM game_config WHERE game_slug = ?", [$gameSlug])->first();
$targetWinRatio = $config->win_ratio / 100; // e.g. 0.20 = 20%
// Use to weight crash points, mine counts, wheel segments etc.
```

## Provably Fair (Optional Enhancement)
For Crash game specifically, server can pre-generate seed:
1. Server generates round_seed = random_bytes(32)
2. Broadcasts sha256(round_seed) hash BEFORE round starts
3. After crash: reveals raw seed
4. Player can verify: crash_point = derive_crash(seed)
This builds trust without exposing house edge implementation.

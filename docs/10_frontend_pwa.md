# Frontend UI + PWA

## Design System
- **Theme:** Dark by default. Background: #0D0D0D. Surface: #1A1A1A. Card: #242424
- **Accent:** Purple #7F77DD (primary) | Green #1D9E75 (win) | Red #E24B4A (loss)
- **Font:** Inter (Google Fonts) — 400 body, 600 headings
- **Animations:** GSAP 3.x for game animations. CSS transitions for UI.
- **Mobile-first:** 375px base, flex/grid layout

## Page Structure
```
/                   → Landing (non-logged) OR Game Lobby (logged in)
/login              → Login form
/register           → Register form
/register?ref=CODE  → Register with referral
/games/{slug}       → Individual game page
/wallet             → Deposit + withdraw + history
/profile            → User profile, stats, level, badges
/referral           → Referral dashboard
/leaderboard        → Daily + weekly + all-time
/quests             → Daily quest progress
/admin/*            → Admin panel (separate layout)
```

## Game Lobby Layout
```
Header: [Logo] [Balance: NPR 1,240] [Bonus: 50] [Avatar] [Hamburger]

Hero Banner: Lucky Hours countdown / promotional banner

Game Grid (4 columns, scrollable):
[Color Predict] [Fast Parity] [Crash] [Limbo]
[Mines]         [Plinko]      [Dice]  [Keno]
[Tower Climb]   [HiLo]        [Dragon Tiger] [Spin Wheel]
[Coin Flip]     [Roulette]    [Slots] [Number Guess]

Right Panel (desktop):
  Live Win Feed ticker (WebSocket)

Bottom Nav (mobile):
  [Home] [Games] [Wallet] [Leaderboard] [Profile]
```

## Game Page Layout
```
Back Button | Game Title | How to Play (?)

[Game Visual Area — full width, 300-400px height]
  (crash graph / mines grid / wheel / coin etc.)

Bet Panel:
  [Balance indicator]
  Amount input: [10] [50] [100] [Max] [Custom]
  Game-specific options (color/direction/mines count etc.)
  [BET NOW] button (big, accent colored)

Recent Bets Table (last 10 rounds, this game):
  Result | Amount | Payout | Multiplier | Time
```

## GSAP Animations Per Game
```javascript
// Crash — multiplier ticker
const crashTick = gsap.to('#multiplier', {
    innerText: targetMultiplier,
    duration: duration,
    ease: 'power1.out',
    snap: { innerText: 0.01 },
    onUpdate: () => {
        const val = parseFloat(document.getElementById('multiplier').innerText);
        if (val > 5) document.getElementById('multiplier').style.color = '#1D9E75';
        if (val > 20) document.getElementById('multiplier').style.color = '#EF9F27';
    }
});

// Mines tile reveal
gsap.fromTo(`#tile-${index}`, {scale: 1}, {
    scale: 1.15, duration: 0.1, yoyo: true, repeat: 1,
    onComplete: () => showTileResult(index, isSafe)
});

// Coin flip
gsap.to('#coin', {
    rotationY: 720 + (result === 'heads' ? 0 : 180),
    duration: 1.2,
    ease: 'power2.out'
});

// Win explosion (confetti)
function showWinEffect() {
    const confetti = Array.from({length: 40}, (_, i) => {
        const el = document.createElement('div');
        el.className = 'confetti-piece';
        document.body.appendChild(el);
        gsap.fromTo(el, {x: '50vw', y: '50vh', opacity: 1, scale: 0},
            {x: (Math.random()*100-50)+'vw', y: (Math.random()*60-30)+'vh',
             opacity: 0, scale: Math.random()*1.5+0.5, duration: 1.5,
             onComplete: () => el.remove()});
    });
}
```

## WebSocket Client
```javascript
class GameSocket {
    constructor() {
        this.ws = null;
        this.reconnectDelay = 1000;
        this.connect();
    }

    connect() {
        this.ws = new WebSocket('wss://betvibe.com/ws');
        this.ws.onopen = () => { this.reconnectDelay = 1000; };
        this.ws.onmessage = (e) => this.handleMessage(JSON.parse(e.data));
        this.ws.onclose = () => setTimeout(() => this.connect(), this.reconnectDelay *= 2);
    }

    handleMessage(data) {
        switch(data.type) {
            case 'crash_tick':    updateCrashMultiplier(data.multiplier); break;
            case 'crash_end':     showCrashResult(data.crash_point); break;
            case 'round_start':   handleRoundStart(data); break;
            case 'win_feed':      appendWinFeed(data); break;
            case 'balance_update': updateBalanceDisplay(data); break;
        }
    }
}
const socket = new GameSocket();
```

## PWA Setup
```json
// manifest.json
{
  "name": "BetVibe",
  "short_name": "BetVibe",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#0D0D0D",
  "theme_color": "#7F77DD",
  "icons": [
    {"src": "/assets/images/icon-192.png", "sizes": "192x192", "type": "image/png"},
    {"src": "/assets/images/icon-512.png", "sizes": "512x512", "type": "image/png"}
  ]
}
```

```javascript
// sw.js — Service Worker
const CACHE = 'betvibe-v1';
const STATIC = ['/','  /assets/css/app.css','/assets/js/app.js','/offline.html'];

self.addEventListener('install', e =>
    e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC))));

self.addEventListener('fetch', e => {
    if (e.request.url.includes('/api/')) return; // never cache API
    e.respondWith(
        caches.match(e.request).then(r => r || fetch(e.request)
            .catch(() => caches.match('/offline.html')))
    );
});

// Push notification handler
self.addEventListener('push', e => {
    const data = e.data.json();
    self.registration.showNotification(data.title, {
        body: data.body,
        icon: '/assets/images/icon-192.png',
        badge: '/assets/images/badge.png',
        data: { url: data.url }
    });
});
```

## UI Language (Hinglish Examples)
```javascript
const ui = {
    bet_button: 'Bet Laga De!',
    win_toast: 'Bhai scene set hai! 🔥',
    loss_toast: 'Agli baar pakka! 💪',
    streak_3: 'Tu hot chal raha hai! 🔥',
    streak_5: 'Bhai all-time high! 🚀',
    low_balance: 'Balance kam hai yaar, deposit kar',
    cashout_btn: 'Nikal Le!',
    loading: 'Thoda ruk...',
    lucky_hours: 'Lucky Hours chal raha hai! ⚡',
};
```

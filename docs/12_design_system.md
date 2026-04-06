# Frontend Design System + UI Components

## Color Tokens
```css
:root {
  --bg-primary:    #0D0D0D;
  --bg-surface:    #1A1A1A;
  --bg-card:       #242424;
  --bg-elevated:   #2E2E2E;

  --accent-purple: #7F77DD;
  --accent-green:  #1D9E75;
  --accent-red:    #E24B4A;
  --accent-amber:  #EF9F27;
  --accent-blue:   #378ADD;

  --text-primary:   #FFFFFF;
  --text-secondary: #A0A0A0;
  --text-muted:     #666666;

  --border:         #333333;
  --border-light:   #444444;

  --win-green:  #1D9E75;
  --loss-red:   #E24B4A;
  --pending-amber: #EF9F27;
  --bonus-purple: #7F77DD;
}
```

## Typography
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); }

.text-h1 { font-size: 24px; font-weight: 700; }
.text-h2 { font-size: 20px; font-weight: 600; }
.text-h3 { font-size: 16px; font-weight: 600; }
.text-body { font-size: 14px; font-weight: 400; }
.text-small { font-size: 12px; font-weight: 400; }
.text-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }
```

## Core Components

### Button
```html
<!-- Primary -->
<button class="btn-primary">Bet Laga De!</button>

<!-- Secondary -->
<button class="btn-secondary">Cancel</button>

<!-- Danger -->
<button class="btn-danger">Cashout Kar</button>
```
```css
.btn-primary {
  background: var(--accent-purple);
  color: white;
  border: none;
  border-radius: 10px;
  padding: 14px 24px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
  transition: transform 0.1s, opacity 0.1s;
}
.btn-primary:active { transform: scale(0.97); opacity: 0.9; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
```

### Balance Display (Header)
```html
<div class="balance-display">
  <div class="balance-real">
    <span class="label">Balance</span>
    <span class="amount" id="real-balance">NPR 1,240</span>
  </div>
  <div class="balance-bonus">
    <span class="label">Bonus</span>
    <span class="amount bonus" id="bonus-coins">50</span>
  </div>
</div>
```

### Bet Amount Panel
```html
<div class="bet-panel">
  <div class="quick-amounts">
    <button class="quick-btn" data-amount="10">10</button>
    <button class="quick-btn" data-amount="50">50</button>
    <button class="quick-btn" data-amount="100">100</button>
    <button class="quick-btn" data-amount="500">500</button>
    <button class="quick-btn" onclick="setMax()">Max</button>
  </div>
  <div class="amount-input-row">
    <button class="half-btn" onclick="halveAmount()">½</button>
    <input type="number" id="bet-amount" value="10" min="10" step="10">
    <button class="double-btn" onclick="doubleAmount()">2×</button>
  </div>
</div>
```

### Toast Notification
```javascript
function showToast(type, message, amount = null) {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = amount
    ? `<span class="toast-msg">${message}</span><span class="toast-amount">${amount}</span>`
    : `<span class="toast-msg">${message}</span>`;
  document.body.appendChild(toast);

  if (type === 'loss') {
    gsap.fromTo(document.body,
      {x: -5}, {x: 0, duration: 0.3, ease: 'elastic.out(4, 0.3)'}
    );
  }

  gsap.fromTo(toast,
    {y: 100, opacity: 0},
    {y: 0, opacity: 1, duration: 0.3,
     onComplete: () => gsap.to(toast, {opacity: 0, delay: 2, onComplete: () => toast.remove()})}
  );
}

// Usage:
showToast('win', 'Bhai scene set hai! 🔥', '+NPR 240');
showToast('loss', 'Agli baar pakka! 💪', '-NPR 100');
showToast('info', 'Lucky Hours shuru ho gaya! ⚡');
```

### Recent Bets Table
```html
<div class="recent-bets">
  <h3 class="section-title">Recent Bets</h3>
  <table class="bets-table">
    <thead>
      <tr>
        <th>Result</th>
        <th>Amount</th>
        <th>Multiplier</th>
        <th>Payout</th>
        <th>Time</th>
      </tr>
    </thead>
    <tbody id="bets-tbody">
      <!-- populated via JS from /api/games/{slug}/history -->
    </tbody>
  </table>
</div>
```

### Streak Badge
```html
<!-- Injected by JS when streak >= 3 -->
<div class="streak-badge streak-fire" id="streak-badge">
  <span class="streak-icon">🔥</span>
  <span class="streak-count">5 WIN STREAK</span>
</div>
```
```css
.streak-badge {
  position: fixed;
  top: 70px;
  right: 16px;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  z-index: 100;
}
.streak-fire   { background: #EF9F27; color: #0D0D0D; }
.streak-hot    { background: #E24B4A; color: white; }
.streak-legend { background: linear-gradient(90deg, #E24B4A, #EF9F27); color: white; }
```

## Mobile Navigation (Bottom Bar)
```html
<nav class="bottom-nav">
  <a href="/" class="nav-item active">
    <svg><!-- home icon --></svg>
    <span>Home</span>
  </a>
  <a href="/games" class="nav-item">
    <svg><!-- game icon --></svg>
    <span>Games</span>
  </a>
  <a href="/wallet" class="nav-item">
    <svg><!-- wallet icon --></svg>
    <span>Wallet</span>
  </a>
  <a href="/leaderboard" class="nav-item">
    <svg><!-- trophy icon --></svg>
    <span>Rank</span>
  </a>
  <a href="/profile" class="nav-item">
    <svg><!-- person icon --></svg>
    <span>Profile</span>
  </a>
</nav>
```

## Live Win Feed (Right Panel / Bottom Ticker)
```javascript
// Appends to feed, removes oldest if > 15 items
function appendWinFeed(data) {
  const feed = document.getElementById('win-feed');
  const item = document.createElement('div');
  item.className = 'feed-item';
  item.innerHTML = `
    <span class="feed-user">${data.username}</span>
    <span class="feed-action">won</span>
    <span class="feed-amount">NPR ${data.payout}</span>
    <span class="feed-game">on ${data.game}</span>
    <span class="feed-multi">${data.multiplier}x</span>
  `;
  feed.prepend(item);
  gsap.from(item, {x: 50, opacity: 0, duration: 0.4});
  if (feed.children.length > 15) feed.lastChild.remove();
}
```

## Confetti Win Effect
```javascript
function showConfetti(amount) {
  const colors = ['#7F77DD', '#1D9E75', '#EF9F27', '#E24B4A', '#FFFFFF'];
  for (let i = 0; i < 60; i++) {
    const el = document.createElement('div');
    el.style.cssText = `
      position:fixed; width:8px; height:8px; border-radius:2px;
      background:${colors[i % colors.length]};
      left:${Math.random()*100}vw; top:${Math.random()*40+30}vh;
      pointer-events:none; z-index:9999;
    `;
    document.body.appendChild(el);
    gsap.fromTo(el,
      {y: 0, rotation: 0, opacity: 1, scale: 0},
      {y: -(Math.random()*200+100), rotation: Math.random()*360,
       opacity: 0, scale: Math.random()+0.5, duration: 1.5,
       delay: Math.random()*0.3,
       onComplete: () => el.remove()}
    );
  }
}
```

## Hinglish UI String Map
```javascript
const UI_STRINGS = {
  bet_button:       'Bet Laga De! 🎯',
  cashout_button:   'Nikal Le! 💰',
  win_title:        'Bhai scene set hai! 🔥',
  loss_title:       'Agli baar pakka! 💪',
  streak_3:         'Tu chal raha hai bhai! 🔥',
  streak_5:         'Hot streak! Rok nahi sakta! ⚡',
  streak_10:        'LEGENDARY! Koi nahi rokega! 🚀',
  low_balance:      'Yaar balance kam ho gaya, deposit kar',
  deposit_success:  'Paisa aa gaya! Ab khelo! 💸',
  withdraw_pending: 'Request submit ho gayi! 2-24 ghante mein process hoga',
  lucky_hours:      'Lucky Hours chal raha hai — extra multipliers! ⚡',
  quest_complete:   'Quest complete! XP mil gaya! 🎯',
  level_up:         'Level up ho gaya bhai! 🆙',
  referral_bonus:   'Tera referral deposit kar diya! Bonus mila tujhe! 🎁',
  loading:          'Thoda ruk yaar...',
  error_balance:    'Itna balance nahi hai yaar',
  error_wagering:   'Pehle thoda khelo, phir withdraw kar',
};
```

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="BetVibe — Gen Z gambling platform with 16 casino games. Play Color Predict, Crash, Mines & more. Win real money in NPR.">
  <meta name="theme-color" content="#7F77DD">
  <meta name="csrf-token" content="<?= \App\Core\App::generateCsrfToken() ?>">
  <meta name="vapid-key" content="<?= $_ENV['VAPID_PUBLIC_KEY'] ?? '' ?>">
  <title>BetVibe — Khelo Jeeto!</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" type="image/png" href="/assets/images/icon-192.png">
  <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
</head>
<body>
  <!-- ─── Header ────────────────────────────────── -->
  <header class="app-header">
    <a href="/" class="header-logo">Bet<span>Vibe</span></a>

    <div class="header-actions">
      <div class="balance-display">
        <div class="balance-real">
          <span class="label">Balance</span>
          <span class="amount" id="real-balance">NPR 0</span>
        </div>
        <div class="balance-divider"></div>
        <div class="balance-bonus">
          <span class="label">Bonus</span>
          <span class="amount" id="bonus-coins">0</span>
        </div>
      </div>
      <a href="/profile" class="avatar" id="user-avatar">?</a>
    </div>
  </header>

  <!-- ─── Page Content ──────────────────────────── -->
  <main class="page-wrapper">
    <div class="container">

      <!-- Hero Banner -->
      <div class="hero-banner" id="hero-banner">
        <div class="hero-title">🎰 Welcome to BetVibe!</div>
        <div class="hero-subtitle">16 games. Real money. Real wins. Ab khel!</div>
      </div>

      <!-- Lucky Hours Banner (8pm-10pm NPT = UTC+5:45) -->
      <?php
      $npt_hour = (int)gmdate('G', time() + 20700); // UTC + 5h45m
      if ($npt_hour >= 20 && $npt_hour < 22): ?>
      <div class="lucky-hours-banner">
        <span>⚡ Lucky Hours chal raha hai! Extra multipliers active! ⚡</span>
      </div>
      <?php endif; ?>

      <!-- Win Ticker -->
      <div class="win-ticker" id="win-ticker" style="margin-bottom:16px;">
        <div class="win-ticker-track" id="win-ticker-track">
          <span class="ticker-item">
            <span class="feed-user">Ra***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 840</span>
            <span class="feed-game">on Crash</span>
            <span class="feed-multi">4.2x</span>
          </span>
          <span class="ticker-item">
            <span class="feed-user">Su***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 320</span>
            <span class="feed-game">on Color Predict</span>
            <span class="feed-multi">1.95x</span>
          </span>
          <span class="ticker-item">
            <span class="feed-user">Am***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 5,000</span>
            <span class="feed-game">on Mines</span>
            <span class="feed-multi">12.5x</span>
          </span>
          <span class="ticker-item">
            <span class="feed-user">Pr***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 1,200</span>
            <span class="feed-game">on Spin Wheel</span>
            <span class="feed-multi">50x</span>
          </span>
          <!-- Duplicate for seamless scroll -->
          <span class="ticker-item">
            <span class="feed-user">Ra***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 840</span>
            <span class="feed-game">on Crash</span>
            <span class="feed-multi">4.2x</span>
          </span>
          <span class="ticker-item">
            <span class="feed-user">Su***</span>
            <span class="feed-action">won</span>
            <span class="feed-amount">NPR 320</span>
            <span class="feed-game">on Color Predict</span>
            <span class="feed-multi">1.95x</span>
          </span>
        </div>
      </div>

      <!-- Section: Popular Games -->
      <div class="section-header">
        <h2 class="section-title">🎮 All Games</h2>
        <span class="text-label" id="game-count">16 games</span>
      </div>

      <!-- Game Grid -->
      <div class="game-grid" id="game-grid">
        <!-- Games loaded via JS -->
      </div>

      <!-- Section: Quick Links -->
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:16px 0;">
        <a href="/quests" class="card" style="text-decoration:none;text-align:center;">
          <div style="font-size:28px;margin-bottom:8px;">🎯</div>
          <div style="font-weight:600;font-size:14px;">Daily Quests</div>
          <div class="text-small text-muted">Earn XP & Coins</div>
        </a>
        <a href="/leaderboard" class="card" style="text-decoration:none;text-align:center;">
          <div style="font-size:28px;margin-bottom:8px;">🏆</div>
          <div style="font-weight:600;font-size:14px;">Leaderboard</div>
          <div class="text-small text-muted">Top Winners</div>
        </a>
        <a href="/referral" class="card" style="text-decoration:none;text-align:center;">
          <div style="font-size:28px;margin-bottom:8px;">🎁</div>
          <div style="font-weight:600;font-size:14px;">Refer & Earn</div>
          <div class="text-small text-muted">5% Bonus</div>
        </a>
        <a href="/wallet" class="card" style="text-decoration:none;text-align:center;">
          <div style="font-size:28px;margin-bottom:8px;">💰</div>
          <div style="font-weight:600;font-size:14px;">Wallet</div>
          <div class="text-small text-muted">Deposit & Withdraw</div>
        </a>
      </div>

    </div>
  </main>

  <!-- ─── Bottom Navigation ─────────────────────── -->
  <nav class="bottom-nav">
    <a href="/" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/></svg>
      <span>Home</span>
    </a>
    <a href="/wallet" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
      <span>Wallet</span>
    </a>
    <a href="/leaderboard" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      <span>Rank</span>
    </a>
    <a href="/quests" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
      <span>Quests</span>
    </a>
    <a href="/profile" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profile</span>
    </a>
  </nav>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/socket.js"></script>
  <script>
    // Game data with emojis as thumbnail placeholders
    const GAMES = [
      { slug: 'color-predict', name: 'Color Predict', emoji: '🎨', badge: 'LIVE', min: 10, max: 5000 },
      { slug: 'fast-parity', name: 'Fast Parity', emoji: '⚡', badge: 'LIVE', min: 10, max: 5000 },
      { slug: 'crash', name: 'Crash', emoji: '📈', badge: 'LIVE', min: 10, max: 10000 },
      { slug: 'limbo', name: 'Limbo', emoji: '🎯', min: 10, max: 10000 },
      { slug: 'mines', name: 'Mines', emoji: '💣', badge: 'HOT', min: 10, max: 10000 },
      { slug: 'plinko', name: 'Plinko', emoji: '🔵', min: 10, max: 10000 },
      { slug: 'dice-duel', name: 'Dice Duel', emoji: '🎲', min: 10, max: 10000 },
      { slug: 'keno', name: 'Keno', emoji: '🎱', min: 10, max: 5000 },
      { slug: 'tower', name: 'Tower Climb', emoji: '🏗️', badge: 'HOT', min: 10, max: 10000 },
      { slug: 'hilo', name: 'HiLo Cards', emoji: '🃏', min: 10, max: 10000 },
      { slug: 'dragon-tiger', name: 'Dragon Tiger', emoji: '🐉', min: 10, max: 10000 },
      { slug: 'spin-wheel', name: 'Spin Wheel', emoji: '🎰', min: 10, max: 10000 },
      { slug: 'coin-flip', name: 'Coin Flip', emoji: '🪙', min: 10, max: 10000 },
      { slug: 'roulette', name: 'Roulette Lite', emoji: '🔴', min: 10, max: 10000 },
      { slug: 'lucky-slots', name: 'Lucky Slots', emoji: '🎰', min: 10, max: 5000 },
      { slug: 'number-guess', name: 'Number Guess', emoji: '🔢', min: 10, max: 10000 },
    ];

    function renderGameGrid() {
      const grid = document.getElementById('game-grid');
      grid.innerHTML = GAMES.map(g => `
        <a href="/games/${g.slug}.html" class="game-card" style="position:relative;">
          ${g.badge ? `<span class="game-badge ${g.badge==='LIVE'?'badge-live':'badge-hot'}">${g.badge}</span>` : ''}
          <div class="game-card-thumb">${g.emoji}</div>
          <div class="game-card-info">
            <div class="game-card-name">${g.name}</div>
            <div class="game-card-limits">NPR ${g.min} – ${g.max.toLocaleString()}</div>
          </div>
        </a>
      `).join('');
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
      renderGameGrid();
      refreshBalance();

      // Stagger animate game cards
      if (window.gsap) {
        gsap.from('.game-card', {
          y: 20, opacity: 0, duration: 0.4, stagger: 0.05, ease: 'power2.out', delay: 0.2
        });
        gsap.from('.hero-banner', {
          y: -20, opacity: 0, duration: 0.5, ease: 'power2.out'
        });
      }
    });
  </script>
</body>
</html>

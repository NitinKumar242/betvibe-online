<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="Your BetVibe profile — level, XP, stats, and settings.">
  <meta name="theme-color" content="#7F77DD">
  <meta name="csrf-token" content="<?= \App\Core\App::generateCsrfToken() ?>">
  <title>Profile — BetVibe</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/css/app.css">
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
    </div>
  </header>

  <main class="page-wrapper">
    <div class="container">

      <!-- Profile Card -->
      <div class="card" style="text-align:center;padding:32px 16px;margin-bottom:16px;">
        <div class="avatar-ring" style="display:inline-block;margin-bottom:12px;">
          <div class="avatar avatar-xl" id="profile-avatar">?</div>
        </div>
        <h1 class="text-h2" id="profile-username" style="margin-bottom:4px;">Loading...</h1>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;">
          <span class="badge badge-bonus" id="profile-level">Level 1</span>
          <span class="text-small text-muted" id="profile-xp">0 XP</span>
        </div>

        <!-- XP Progress Bar -->
        <div style="max-width:280px;margin:0 auto;">
          <div class="progress-bar">
            <div class="progress-fill" id="xp-progress" style="width:0%"></div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:4px;">
            <span class="text-small text-muted" id="xp-current">0 XP</span>
            <span class="text-small text-muted" id="xp-next">Next: 200 XP</span>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-2 gap-md" style="margin-bottom:16px;" id="stats-grid">
        <div class="stat-card">
          <div class="stat-value text-green" id="stat-total-won">-</div>
          <div class="stat-label">Total Won</div>
        </div>
        <div class="stat-card">
          <div class="stat-value text-red" id="stat-total-bets">-</div>
          <div class="stat-label">Total Bets</div>
        </div>
        <div class="stat-card">
          <div class="stat-value text-amber" id="stat-win-streak">-</div>
          <div class="stat-label">Best Streak</div>
        </div>
        <div class="stat-card">
          <div class="stat-value text-purple" id="stat-referrals">-</div>
          <div class="stat-label">Referrals</div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="section-header">
        <h2 class="section-title">Quick Actions</h2>
      </div>

      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
        <a href="/wallet" class="card flex items-center gap-md" style="text-decoration:none;padding:14px 16px;">
          <span style="font-size:20px;">💰</span>
          <span style="flex:1;font-weight:500;">Wallet</span>
          <span class="text-muted" style="font-size:20px;">›</span>
        </a>
        <a href="/referral" class="card flex items-center gap-md" style="text-decoration:none;padding:14px 16px;">
          <span style="font-size:20px;">🎁</span>
          <span style="flex:1;font-weight:500;">Refer & Earn</span>
          <span class="text-muted" style="font-size:20px;">›</span>
        </a>
        <a href="/quests" class="card flex items-center gap-md" style="text-decoration:none;padding:14px 16px;">
          <span style="font-size:20px;">🎯</span>
          <span style="flex:1;font-weight:500;">Daily Quests</span>
          <span class="text-muted" style="font-size:20px;">›</span>
        </a>
        <a href="/leaderboard" class="card flex items-center gap-md" style="text-decoration:none;padding:14px 16px;">
          <span style="font-size:20px;">🏆</span>
          <span style="flex:1;font-weight:500;">Leaderboard</span>
          <span class="text-muted" style="font-size:20px;">›</span>
        </a>
      </div>

      <!-- Logout -->
      <button class="btn btn-danger btn-full" onclick="handleLogout()" id="logout-btn">
        Logout
      </button>
      <div style="height:24px;"></div>

    </div>
  </main>

  <!-- ─── Bottom Navigation ─────────────────────── -->
  <nav class="bottom-nav">
    <a href="/" class="nav-item">
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
    <a href="/profile" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Profile</span>
    </a>
  </nav>

  <script src="/assets/js/app.js"></script>
  <script>
    const XP_THRESHOLDS = [0, 200, 500, 900, 1400, 2000, 2800, 3800, 5000, 6500,
      8200, 10100, 12200, 14500, 17000, 19700, 22600, 25700, 29000, 32500];

    async function loadProfile() {
      try {
        const data = await apiFetch('/api/auth/me');
        if (!data.success) {
          window.location.href = '/login';
          return;
        }

        const user = data.user || data.data;
        document.getElementById('profile-username').textContent = user.username || 'User';
        document.getElementById('profile-avatar').textContent = (user.username || '?')[0].toUpperCase();
        document.getElementById('profile-level').textContent = `Level ${user.level || 1}`;
        document.getElementById('profile-xp').textContent = `${user.xp || 0} XP`;

        // XP progress
        const level = user.level || 1;
        const xp = user.xp || 0;
        const currentThreshold = XP_THRESHOLDS[level - 1] || 0;
        const nextThreshold = XP_THRESHOLDS[level] || XP_THRESHOLDS[XP_THRESHOLDS.length - 1];
        const progress = Math.min(100, ((xp - currentThreshold) / (nextThreshold - currentThreshold)) * 100);

        document.getElementById('xp-progress').style.width = progress + '%';
        document.getElementById('xp-current').textContent = `${xp} XP`;
        document.getElementById('xp-next').textContent = `Next: ${nextThreshold} XP`;

        // Balance
        updateBalanceDisplay(user.real_balance || 0, user.bonus_coins || 0);

      } catch (e) {
        window.location.href = '/login';
      }
    }

    async function loadStats() {
      try {
        // Fetch stats from various endpoints
        const [balanceData, referralData] = await Promise.allSettled([
          apiFetch('/api/wallet/balance'),
          apiFetch('/api/referral/dashboard'),
        ]);

        if (balanceData.status === 'fulfilled' && balanceData.value.success) {
          // Stats would come from a dedicated endpoint in production
        }
        if (referralData.status === 'fulfilled' && referralData.value.success) {
          const ref = referralData.value.data;
          document.getElementById('stat-referrals').textContent = ref.total || 0;
        }
      } catch (e) {
        // Non-critical, leave defaults
      }
    }

    async function handleLogout() {
      try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
      } catch (e) {
        // Proceed with logout regardless
      }
      localStorage.clear();
      window.location.href = '/login';
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadProfile();
      loadStats();
    });
  </script>
</body>
</html>

/**
 * BetVibe — Core Application JavaScript
 * Toast notifications, confetti, CSRF tokens, PWA install, balance updates
 */

/* ─── UI Strings (Hinglish) ─────────────────────────── */
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

/* ─── CSRF Token Management ─────────────────────────── */
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function getCsrfToken() {
  return window.csrfToken;
}

/* ─── API Fetch Wrapper ─────────────────────────────── */
async function apiFetch(url, options = {}) {
  const defaults = {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': getCsrfToken(),
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  };

  const config = {
    ...defaults,
    ...options,
    headers: { ...defaults.headers, ...(options.headers || {}) },
  };

  if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
    config.body = JSON.stringify(config.body);
  }

  try {
    const response = await fetch(url, config);
    const data = await response.json();

    if (!response.ok) {
      throw { status: response.status, ...data };
    }

    return data;
  } catch (err) {
    if (err.status === 401) {
      window.location.href = '/login';
      return;
    }
    if (err.status === 429) {
      showToast('error', 'Thoda wait kar yaar, bahut fast chal raha hai!');
      return;
    }
    throw err;
  }
}

/* ─── Toast Notification System ─────────────────────── */
let toastContainer = null;

function ensureToastContainer() {
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    toastContainer.id = 'toast-container';
    document.body.appendChild(toastContainer);
  }
  return toastContainer;
}

function showToast(type, message, amount = null) {
  const container = ensureToastContainer();

  // Max 3 toasts visible
  while (container.children.length >= 3) {
    container.removeChild(container.firstChild);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = amount
    ? `<span class="toast-msg">${message}</span><span class="toast-amount">${amount}</span>`
    : `<span class="toast-msg">${message}</span>`;
  container.appendChild(toast);

  // Win screen flash
  if (type === 'win') {
    const flash = document.createElement('div');
    flash.className = 'win-flash';
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 500);
  }

  // Loss shake
  if (type === 'loss') {
    document.body.classList.add('shake');
    setTimeout(() => document.body.classList.remove('shake'), 300);
  }

  // GSAP animate if available, otherwise CSS handles it
  if (window.gsap) {
    gsap.fromTo(toast,
      { y: 30, opacity: 0 },
      {
        y: 0, opacity: 1, duration: 0.3, ease: 'back.out(1.7)',
        onComplete: () => {
          gsap.to(toast, {
            opacity: 0, y: -10, delay: 2.5, duration: 0.3,
            onComplete: () => toast.remove()
          });
        }
      }
    );
  } else {
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-10px)';
      setTimeout(() => toast.remove(), 300);
    }, 2800);
  }
}

function showWinToast(amount) {
  showToast('win', UI_STRINGS.win_title, `+NPR ${amount}`);
}

function showLossToast(amount) {
  showToast('loss', UI_STRINGS.loss_title, `-NPR ${amount}`);
}

/* ─── Confetti Effect ───────────────────────────────── */
function showConfetti() {
  const colors = ['#7F77DD', '#1D9E75', '#EF9F27', '#E24B4A', '#FFFFFF'];
  const particles = [];

  for (let i = 0; i < 60; i++) {
    const el = document.createElement('div');
    el.className = 'confetti-piece';
    el.style.cssText = `
      background: ${colors[i % colors.length]};
      left: ${Math.random() * 100}vw;
      top: ${30 + Math.random() * 40}vh;
    `;
    document.body.appendChild(el);
    particles.push(el);

    if (window.gsap) {
      gsap.fromTo(el,
        { y: 0, rotation: 0, opacity: 1, scale: 0 },
        {
          y: -(Math.random() * 200 + 100),
          x: (Math.random() * 100 - 50),
          rotation: Math.random() * 720 - 360,
          opacity: 0,
          scale: Math.random() + 0.5,
          duration: 1.5 + Math.random() * 0.5,
          delay: Math.random() * 0.3,
          ease: 'power2.out',
          onComplete: () => el.remove()
        }
      );
    } else {
      setTimeout(() => el.remove(), 2000);
    }
  }
}

/* ─── Balance Display Updater ───────────────────────── */
function updateBalanceDisplay(real, bonus) {
  const realEl = document.getElementById('real-balance');
  const bonusEl = document.getElementById('bonus-coins');

  const formatNPRBalance = (v) => {
    return 'NPR ' + parseFloat(v).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  if (realEl) {
    const formattedReal = formatNPRBalance(real);
    realEl.textContent = formattedReal;
    realEl.classList.add('balance-flash');
    setTimeout(() => realEl.classList.remove('balance-flash'), 600);
    if (window.gsap) {
      gsap.fromTo(realEl, { scale: 1.1 }, { scale: 1, duration: 0.3 });
    }
  }

  if (bonusEl && bonus !== null && bonus !== undefined) {
    bonusEl.textContent = Number(bonus).toLocaleString('en-IN');
  }
}

async function refreshBalance() {
  try {
    const data = await apiFetch('/api/wallet/balance');
    if (data.success) {
      updateBalanceDisplay(data.real, data.bonus);
    }
  } catch (e) {
    // Silently fail for unauthenticated users
  }
}

/* ─── Streak Badge ──────────────────────────────────── */
function showStreakBadge(count, type) {
  // Remove existing badge
  document.getElementById('streak-badge')?.remove();

  if (count < 3) return;

  const badge = document.createElement('div');
  badge.id = 'streak-badge';

  let badgeClass = 'streak-fire';
  let emoji = '🔥';
  let text = `${count} WIN STREAK`;
  if (count >= 10) { badgeClass = 'streak-legend'; emoji = '🚀'; text = `LEGENDARY ${count}`; }
  else if (count >= 5) { badgeClass = 'streak-hot'; emoji = '⚡'; text = `HOT STREAK ${count}`; }

  badge.className = `streak-badge ${badgeClass}`;
  badge.innerHTML = `<span class="streak-icon">${emoji}</span><span class="streak-count">${text}</span>`;
  document.body.appendChild(badge);

  if (window.gsap) {
    gsap.from(badge, { x: 100, opacity: 0, duration: 0.4, ease: 'back.out(1.7)' });
  }

  // Auto-remove after 3s
  setTimeout(() => {
    if (window.gsap) {
      gsap.to(badge, { x: 100, opacity: 0, duration: 0.3, onComplete: () => badge.remove() });
    } else {
      badge.remove();
    }
  }, 3000);
}

/* ─── Bet Panel Object (Global) ─────────────────────── */
window.BetPanel = {
  minBet: 10,
  maxBet: 10000,
  realBalance: 0,

  init(minBet = 10, maxBet = 10000, realBalance = 0) {
    this.minBet = minBet;
    this.maxBet = maxBet;
    this.realBalance = realBalance;
    initBetPanel();
  },

  setAmount(v) {
    const input = document.getElementById('bet-amount');
    if (input) input.value = Math.max(this.minBet, Math.min(this.maxBet, Math.floor(v)));
  },

  halve() {
    this.setAmount(Math.max(this.minBet, Math.floor(this.getAmount() / 2)));
  },

  double() {
    this.setAmount(Math.min(this.maxBet, this.getAmount() * 2));
  },

  setMax() {
    this.setAmount(Math.min(this.maxBet, this.realBalance));
  },

  getAmount() {
    return parseFloat(document.getElementById('bet-amount')?.value) || this.minBet;
  }
};

/* ─── Bet Amount Helpers (Legacy compat) ────────────── */
function initBetPanel() {
  document.querySelectorAll('.quick-btn[data-amount]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById('bet-amount');
      if (input) {
        input.value = btn.dataset.amount;
        document.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      }
    });
  });
}

function halveAmount() { window.BetPanel.halve(); }
function doubleAmount() { window.BetPanel.double(); }
function setMax() { window.BetPanel.setMax(); }
function getBetAmount() { return window.BetPanel.getAmount(); }

/* ─── Game Result Handler ───────────────────────────── */
function handleGameResult(result) {
  if (result.result === 'win') {
    showWinToast(result.payout);
    showConfetti();
    if (result.streak && result.streak.count >= 3) {
      showStreakBadge(result.streak.count, result.streak.type);
    }
  } else {
    showLossToast(result.bet_amount || result.amount || 0);
  }

  // Update balance
  if (result.new_balance !== undefined) {
    updateBalanceDisplay(result.new_balance, result.new_bonus || null);
  } else {
    refreshBalance();
  }

  // XP notification
  if (result.xp_gained) {
    setTimeout(() => {
      showToast('info', `+${result.xp_gained} XP earned!`);
    }, 1500);
  }

  // Level up
  if (result.level_up) {
    setTimeout(() => {
      showToast('info', `${UI_STRINGS.level_up} Level ${result.new_level}`);
    }, 2500);
  }

  // Win card prompt (multiplier >= 3 and win_card_url exists)
  if (result.result === 'win' && result.multiplier >= 3 && result.win_card_url) {
    setTimeout(() => showWinCardPrompt(result.bet_id, result.win_card_url), 3000);
  }
}

/* ─── Win Card Share Modal ──────────────────────────── */
function showWinCardPrompt(betId, winCardUrl) {
  const imgUrl = winCardUrl || `/api/win-card/${betId}`;
  const overlay = document.createElement('div');
  overlay.className = 'share-overlay';
  overlay.id = 'share-overlay';
  overlay.innerHTML = `
    <div class="share-card-preview">
      <img src="${imgUrl}" alt="Win Card" loading="lazy">
    </div>
    <p style="color:white;font-weight:600;margin-bottom:16px;">Share your win! 🔥</p>
    <div class="share-buttons">
      <button class="btn btn-success btn-sm" onclick="shareWhatsApp(${betId})">WhatsApp</button>
      <button class="btn btn-primary btn-sm" onclick="copyShareLink(${betId})">Copy Link</button>
      <button class="btn btn-ghost btn-sm" onclick="closeShareOverlay()">✕ Close</button>
    </div>
  `;
  document.body.appendChild(overlay);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeShareOverlay();
  });
}

function shareWhatsApp(betId) {
  const url = `${window.location.origin}/api/win-card/${betId}`;
  window.open(`https://wa.me/?text=I just won big on BetVibe! 🔥 Check it out: ${encodeURIComponent(url)}`, '_blank');
}

function copyShareLink(betId) {
  const url = `${window.location.origin}/api/win-card/${betId}`;
  navigator.clipboard.writeText(url).then(() => {
    showToast('info', 'Link copied!');
  });
}

function closeShareOverlay() {
  document.getElementById('share-overlay')?.remove();
}

/* ─── PWA Install Prompt ────────────────────────────── */
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;

  // Don't show if dismissed recently (within 7 days)
  const dismissed = localStorage.getItem('bv_pwa_dismissed');
  if (dismissed) {
    const daysSince = (Date.now() - parseInt(dismissed)) / (1000 * 60 * 60 * 24);
    if (daysSince < 7) return;
  }

  // Show after 2nd visit
  const visits = parseInt(localStorage.getItem('bv_visits') || '0') + 1;
  localStorage.setItem('bv_visits', visits);
  if (visits < 2) return;

  showPWABanner();
});

function showPWABanner() {
  if (document.getElementById('pwa-banner')) return;

  const banner = document.createElement('div');
  banner.className = 'pwa-install-banner';
  banner.id = 'pwa-banner';
  banner.innerHTML = `
    <span style="font-size:24px;">📱</span>
    <span class="pwa-text">BetVibe install karo homescreen pe! 📲</span>
    <button class="pwa-install-btn" onclick="installPWA()">Install</button>
    <button class="pwa-dismiss" onclick="dismissPWA()">✕</button>
  `;
  document.body.appendChild(banner);
}

async function installPWA() {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  const { outcome } = await deferredPrompt.userChoice;
  deferredPrompt = null;
  document.getElementById('pwa-banner')?.remove();
}

function dismissPWA() {
  localStorage.setItem('bv_pwa_dismissed', Date.now().toString());
  document.getElementById('pwa-banner')?.remove();
}

/* ─── Daily Reward Popup ────────────────────────────── */
window.checkDailyReward = async function() {
  try {
    const data = await apiFetch('/api/daily-reward/status');
    if (data.success && data.data.can_claim) {
      showDailyRewardPopup(data.data);
    }
  } catch (e) {
    // Not logged in or API unavailable
  }
}

function showDailyRewardPopup(rewardData) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = 'daily-reward-modal';

  const rewards = [20, 30, 50, 75, 100, 150, 300];
  const currentDay = rewardData.day_number || 1;
  const streak = rewardData.streak || 0;

  let calendarHTML = '';
  for (let i = 0; i < 7; i++) {
    const dayNum = i + 1;
    let cls = '';
    if (dayNum < currentDay) cls = 'claimed';
    else if (dayNum === currentDay) cls = 'current';
    calendarHTML += `
      <div class="reward-day ${cls}">
        <span class="day-num">Day ${dayNum}</span>
        <span class="day-coins">${rewards[i]}🪙</span>
      </div>`;
  }

  overlay.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">🎁 Daily Reward</h3>
        <button class="modal-close" onclick="closeDailyReward()">✕</button>
      </div>
      <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
        Streak: ${streak} days 🔥
      </p>
      <div class="reward-calendar">${calendarHTML}</div>
      <div style="margin-top:20px;">
        <button class="btn btn-bet" onclick="claimDailyReward()">
          Claim Karo! +${rewards[currentDay - 1]} coins 🎉
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeDailyReward();
  });
}

async function claimDailyReward() {
  try {
    const data = await apiFetch('/api/daily-reward/claim', { method: 'POST' });
    if (data.success || data.coins_given) {
      showToast('info', `+${data.coins_given || data.data?.coins_given || 0} bonus coins claimed! 🎉`);
      refreshBalance();
    }
  } catch (e) {
    showToast('loss', e.error || 'Failed to claim reward');
  }
  closeDailyReward();
}

function closeDailyReward() {
  document.getElementById('daily-reward-modal')?.remove();
}

/* ─── Service Worker Registration ───────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(reg => console.log('SW registered:', reg.scope))
      .catch(err => console.log('SW registration failed:', err));
  });
}

/* ─── Push Notification Subscription ────────────────── */
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

window.requestPushPermission = async function() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return;
  const sw = await navigator.serviceWorker.ready;
  const vapidKey = document.querySelector('meta[name="vapid-key"]')?.content;
  if (!vapidKey) return;
  const sub = await sw.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapidKey)
  });
  await apiFetch('/api/push/subscribe', { method: 'POST', body: JSON.stringify(sub) });
}

/* ─── Navigation Active State ───────────────────────── */
function setActiveNav() {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href');
    if (href === path || (href !== '/' && path.startsWith(href))) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });
}

/* ─── Number Formatting ─────────────────────────────── */
function formatNPR(amount) {
  return 'NPR ' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatTime(dateStr) {
  const date = new Date(dateStr);
  const now = new Date();
  const diff = now - date;

  if (diff < 60000) return 'Just now';
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
}

/* ─── Escape HTML ───────────────────────────────────── */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/* ─── WebSocket Balance Sync ────────────────────────── */
document.addEventListener('ws:balance_update', (e) => {
  if (e.detail) {
    updateBalanceDisplay(e.detail.real, e.detail.bonus);
  }
});

/* ─── Init on DOM Ready ─────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  setActiveNav();
  initBetPanel();

  // Check daily reward on dashboard
  if (window.location.pathname === '/' || window.location.pathname === '/dashboard') {
    setTimeout(checkDailyReward, 1500);
  }

  // Attempt push subscription
  setTimeout(() => {
    if (Notification.permission === 'default') {
      // Don't auto-prompt, wait for user interaction
    } else if (Notification.permission === 'granted') {
      requestPushPermission();
    }
  }, 5000);
});

/* ─── Global Namespace Export ───────────────────────── */
window.betvibe = {
  apiFetch,
  showToast,
  showConfetti,
  handleGameResult,
  BetPanel: window.BetPanel,
  updateBalance: updateBalanceDisplay,
  refreshBalance,
  formatNPR,
  UI_STRINGS,
};

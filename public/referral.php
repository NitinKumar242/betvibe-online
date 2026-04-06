<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Referral Program</title>
    <meta name="description" content="Refer friends to BetVibe and earn 5% of their first deposit as real balance!">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0D0D0D;
            color: #fff;
            min-height: 100vh;
        }
        .page-container { max-width: 600px; margin: 0 auto; padding: 20px 16px 100px; }

        /* Header */
        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .back-btn {
            width: 40px; height: 40px;
            background: #1A1A1A;
            border: 1px solid #2A2A2A;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #fff; text-decoration: none;
            transition: all 0.2s;
        }
        .back-btn:hover { background: #242424; border-color: #7F77DD; }
        .page-title { font-size: 1.5rem; font-weight: 800; }

        /* Share Link Card */
        .share-card {
            background: linear-gradient(135deg, #1A1A1A 0%, #242424 100%);
            border: 1px solid #333;
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .share-card::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(127,119,221,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .share-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .share-link-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .link-display {
            flex: 1;
            background: #0D0D0D;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 14px 16px;
            font-family: 'Inter', monospace;
            font-size: 0.9rem;
            color: #7F77DD;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .share-actions {
            display: flex;
            gap: 10px;
        }
        .btn-copy, .btn-whatsapp {
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        .btn-copy {
            background: #1A1A1A;
            border: 1px solid #333;
            color: #fff;
        }
        .btn-copy:hover { border-color: #7F77DD; background: #242424; }
        .btn-copy.copied { background: #1D9E75; border-color: #1D9E75; }
        .btn-whatsapp {
            background: #25D366;
            color: #fff;
        }
        .btn-whatsapp:hover { background: #20bd5a; transform: translateY(-1px); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #1A1A1A;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            border-color: #333;
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .stat-value.earned { color: #1D9E75; }
        .stat-value.total { color: #7F77DD; }
        .stat-value.pending { color: #EF9F27; }
        .stat-value.converted { color: #1D9E75; }
        .stat-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* How it works */
        .how-it-works {
            background: #1A1A1A;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .how-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: #fff;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }
        .step:last-child { margin-bottom: 0; }
        .step-number {
            width: 28px; height: 28px;
            background: linear-gradient(135deg, #7F77DD, #6B63C8);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .step-text {
            font-size: 0.9rem;
            color: #aaa;
            line-height: 1.5;
        }
        .step-text strong { color: #fff; }

        /* Referral Table */
        .referrals-section {
            background: #1A1A1A;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 24px;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .referral-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #242424;
        }
        .referral-item:last-child { border-bottom: none; }
        .referral-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .referral-avatar {
            width: 36px; height: 36px;
            background: #242424;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
        }
        .referral-name { font-weight: 600; font-size: 0.95rem; }
        .referral-date { font-size: 0.8rem; color: #666; margin-top: 2px; }
        .referral-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .referral-status.converted {
            background: rgba(29, 158, 117, 0.15);
            color: #1D9E75;
        }
        .referral-status.pending {
            background: rgba(239, 159, 39, 0.15);
            color: #EF9F27;
        }
        .referral-earned {
            font-size: 0.85rem;
            color: #1D9E75;
            font-weight: 700;
            text-align: right;
        }
        .referral-right { text-align: right; }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #555;
        }
        .empty-state .emoji { font-size: 2.5rem; margin-bottom: 12px; }
        .empty-state p { font-size: 0.9rem; line-height: 1.5; }

        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        .spinner {
            width: 32px; height: 32px;
            border: 3px solid #333;
            border-top-color: #7F77DD;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #1D9E75;
            color: #fff;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            opacity: 0;
            transition: all 0.3s;
            z-index: 999;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>

<body>
    <div class="page-container">
        <!-- Header -->
        <div class="page-header">
            <a href="/" class="back-btn">←</a>
            <h1 class="page-title">🤝 Referral Program</h1>
        </div>

        <!-- Share Link -->
        <div class="share-card">
            <div class="share-label">Your Referral Link</div>
            <div class="share-link-row">
                <div class="link-display" id="linkDisplay">Loading...</div>
            </div>
            <div class="share-actions">
                <button class="btn-copy" id="copyBtn" onclick="copyLink()">
                    📋 Copy Link
                </button>
                <button class="btn-whatsapp" id="whatsappBtn" onclick="shareWhatsApp()">
                    💬 WhatsApp
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value total" id="statTotal">0</div>
                <div class="stat-label">Total Referred</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value pending" id="statPending">0</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value converted" id="statConverted">0</div>
                <div class="stat-label">Converted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value earned" id="statEarned">₹0</div>
                <div class="stat-label">Total Earned</div>
            </div>
        </div>

        <!-- How it Works -->
        <div class="how-it-works">
            <div class="how-title">🎯 How It Works</div>
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-text">Share your <strong>referral link</strong> with friends</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-text">They sign up and make a <strong>first deposit of NPR 200+</strong></div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">You earn <strong>5% of their deposit</strong> as real balance (withdrawable!)</div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-text">Your friend gets <strong>50 bonus coins</strong> as welcome gift 🎁</div>
            </div>
        </div>

        <!-- Recent Referrals -->
        <div class="referrals-section">
            <div class="section-title">📋 Recent Referrals</div>
            <div id="referralsList">
                <div class="loading-spinner"><div class="spinner"></div></div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">Link copied!</div>

    <script>
        let shareLink = '';
        let whatsappUrl = '';

        // Load share link
        async function loadShareLink() {
            try {
                const res = await fetch('/api/referral/share-link');
                const data = await res.json();
                if (data.success) {
                    shareLink = data.data.link;
                    whatsappUrl = data.data.whatsapp_url;
                    document.getElementById('linkDisplay').textContent = shareLink;
                }
            } catch (err) {
                console.error('Failed to load share link:', err);
                document.getElementById('linkDisplay').textContent = 'Login required';
            }
        }

        // Load dashboard stats
        async function loadDashboard() {
            try {
                const res = await fetch('/api/referral/dashboard');
                const data = await res.json();
                if (data.success) {
                    const d = data.data;
                    document.getElementById('statTotal').textContent = d.total;
                    document.getElementById('statPending').textContent = d.pending;
                    document.getElementById('statConverted').textContent = d.converted;
                    document.getElementById('statEarned').textContent = 'NPR ' + d.total_earned.toFixed(0);
                    renderReferrals(d.recent_referrals);
                }
            } catch (err) {
                console.error('Failed to load dashboard:', err);
                document.getElementById('referralsList').innerHTML =
                    '<div class="empty-state"><div class="emoji">🔒</div><p>Please login to see your referrals</p></div>';
            }
        }

        // Render referral list
        function renderReferrals(referrals) {
            const container = document.getElementById('referralsList');
            if (!referrals || referrals.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="emoji">📭</div>
                        <p>No referrals yet.<br>Share your link and start earning!</p>
                    </div>`;
                return;
            }

            let html = '';
            referrals.forEach(ref => {
                const date = new Date(ref.referred_at).toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric'
                });
                const statusClass = ref.status;
                const statusLabel = ref.status === 'converted' ? '✅ Converted' : '⏳ Pending';
                const earned = ref.status === 'converted'
                    ? `<div class="referral-earned">+NPR ${ref.bonus_paid.toFixed(0)}</div>`
                    : '<div class="referral-earned" style="color:#666">—</div>';

                html += `
                    <div class="referral-item">
                        <div class="referral-info">
                            <div class="referral-avatar">👤</div>
                            <div>
                                <div class="referral-name">${ref.username}</div>
                                <div class="referral-date">${date}</div>
                            </div>
                        </div>
                        <div class="referral-right">
                            <span class="referral-status ${statusClass}">${statusLabel}</span>
                            ${earned}
                        </div>
                    </div>`;
            });

            container.innerHTML = html;
        }

        // Copy link
        function copyLink() {
            if (!shareLink) return;
            navigator.clipboard.writeText(shareLink).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.classList.add('copied');
                btn.innerHTML = '✅ Copied!';
                showToast('Link copied to clipboard!');
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '📋 Copy Link';
                }, 2000);
            });
        }

        // Share WhatsApp
        function shareWhatsApp() {
            if (whatsappUrl) {
                window.open(whatsappUrl, '_blank');
            }
        }

        // Toast notification
        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            loadShareLink();
            loadDashboard();
        });
    </script>
</body>

</html>

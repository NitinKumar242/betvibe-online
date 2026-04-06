<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Leaderboard</title>
    <meta name="description" content="See top earners on BetVibe leaderboard. Win weekly prizes!">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0D0D0D; color: #fff; min-height: 100vh; }
        .page-container { max-width: 600px; margin: 0 auto; padding: 20px 16px 100px; }
        .page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .back-btn { width: 40px; height: 40px; background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; text-decoration: none; transition: all 0.2s; }
        .back-btn:hover { background: #242424; border-color: #7F77DD; }
        .page-title { font-size: 1.5rem; font-weight: 800; }

        .tab-pills { display: flex; gap: 8px; margin-bottom: 24px; }
        .pill {
            flex: 1; padding: 12px; text-align: center; border-radius: 12px; cursor: pointer;
            background: #1A1A1A; border: 1px solid #2A2A2A; font-weight: 700; font-size: 0.85rem;
            transition: all 0.2s;
        }
        .pill:hover { border-color: #7F77DD; }
        .pill.active { background: linear-gradient(135deg, #7F77DD, #6B63C8); border-color: transparent; }

        .podium { display: flex; align-items: flex-end; justify-content: center; gap: 12px; margin-bottom: 28px; padding: 20px 0; }
        .podium-item { text-align: center; flex: 1; max-width: 120px; }
        .podium-avatar {
            width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 8px;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .podium-item:nth-child(1) .podium-avatar { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); }
        .podium-item:nth-child(2) .podium-avatar { background: linear-gradient(135deg, #FFD700, #FFA500); width: 64px; height: 64px; }
        .podium-item:nth-child(3) .podium-avatar { background: linear-gradient(135deg, #CD7F32, #A0522D); }
        .podium-name { font-size: 0.85rem; font-weight: 700; margin-bottom: 4px; }
        .podium-profit { font-size: 0.9rem; font-weight: 800; color: #1D9E75; }
        .podium-rank { font-size: 0.75rem; color: #888; margin-bottom: 6px; }
        .podium-bar { height: 4px; border-radius: 2px; margin: 6px auto 0; }
        .podium-item:nth-child(1) .podium-bar { background: #C0C0C0; width: 60%; height: 60px; border-radius: 8px 8px 0 0; }
        .podium-item:nth-child(2) .podium-bar { background: #FFD700; width: 60%; height: 80px; border-radius: 8px 8px 0 0; }
        .podium-item:nth-child(3) .podium-bar { background: #CD7F32; width: 60%; height: 40px; border-radius: 8px 8px 0 0; }

        .leaderboard-list { background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 16px; overflow: hidden; }
        .lb-item {
            display: flex; align-items: center; padding: 16px 20px;
            border-bottom: 1px solid #242424; transition: background 0.2s;
        }
        .lb-item:last-child { border-bottom: none; }
        .lb-item:hover { background: #242424; }
        .lb-rank { width: 36px; font-weight: 800; font-size: 1rem; color: #666; }
        .lb-rank.gold { color: #FFD700; }
        .lb-rank.silver { color: #C0C0C0; }
        .lb-rank.bronze { color: #CD7F32; }
        .lb-avatar { width: 40px; height: 40px; background: #242424; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 14px; font-size: 1rem; }
        .lb-info { flex: 1; }
        .lb-name { font-weight: 700; font-size: 0.95rem; }
        .lb-stats { font-size: 0.8rem; color: #666; }
        .lb-profit { font-weight: 800; font-size: 1rem; color: #1D9E75; text-align: right; }

        .my-rank {
            background: linear-gradient(135deg, rgba(127,119,221,0.1), rgba(127,119,221,0.05));
            border: 1px solid #7F77DD; border-radius: 16px; padding: 20px;
            margin-top: 20px; display: flex; align-items: center;
        }
        .my-rank .lb-rank { color: #7F77DD; }
        .my-rank .lb-name { color: #7F77DD; }

        .loading { text-align: center; padding: 60px; color: #555; }
        .spinner { width: 32px; height: 32px; border: 3px solid #333; border-top-color: #7F77DD; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <a href="/" class="back-btn">←</a>
            <h1 class="page-title">🏆 Leaderboard</h1>
        </div>

        <div class="tab-pills">
            <div class="pill active" data-period="daily" onclick="switchPeriod('daily')">Today</div>
            <div class="pill" data-period="weekly" onclick="switchPeriod('weekly')">This Week</div>
            <div class="pill" data-period="alltime" onclick="switchPeriod('alltime')">All Time</div>
        </div>

        <div id="leaderboardContainer">
            <div class="loading"><div class="spinner"></div>Loading leaderboard...</div>
        </div>

        <div id="myRankContainer"></div>
    </div>

    <script>
        let currentPeriod = 'daily';
        let refreshInterval;

        function switchPeriod(period) {
            currentPeriod = period;
            document.querySelectorAll('.pill').forEach(p => p.classList.toggle('active', p.dataset.period === period));
            loadLeaderboard();
        }

        async function loadLeaderboard() {
            try {
                const [lbRes, myRes] = await Promise.all([
                    fetch(`/api/leaderboard?period=${currentPeriod}`),
                    fetch(`/api/leaderboard/my-rank?period=${currentPeriod}`).catch(() => null)
                ]);

                const lbData = await lbRes.json();
                if (lbData.success) renderLeaderboard(lbData.data);

                if (myRes) {
                    const myData = await myRes.json();
                    if (myData.success) renderMyRank(myData.data);
                }
            } catch (err) {
                document.getElementById('leaderboardContainer').innerHTML = '<div class="loading">Failed to load leaderboard</div>';
            }
        }

        function renderLeaderboard(data) {
            const container = document.getElementById('leaderboardContainer');
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="loading">No data yet for this period. Start playing! 🎮</div>';
                return;
            }

            let html = '<div class="leaderboard-list">';
            data.forEach((p, i) => {
                const rankEmoji = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '';
                const rankClass = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : '';
                html += `
                    <div class="lb-item">
                        <div class="lb-rank ${rankClass}">${rankEmoji || '#' + p.rank}</div>
                        <div class="lb-avatar">👤</div>
                        <div class="lb-info">
                            <div class="lb-name">${p.username}</div>
                            <div class="lb-stats">${p.wins} wins · ${p.best_multiplier}x best</div>
                        </div>
                        <div class="lb-profit">NPR ${p.profit.toLocaleString()}</div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function renderMyRank(data) {
            const container = document.getElementById('myRankContainer');
            if (!data || data.rank === null) {
                container.innerHTML = '';
                return;
            }
            container.innerHTML = `
                <div class="my-rank">
                    <div class="lb-rank">#${data.rank}</div>
                    <div class="lb-avatar">⭐</div>
                    <div class="lb-info">
                        <div class="lb-name">${data.username} (You)</div>
                        <div class="lb-stats">${data.wins} wins · ${data.total_bets} bets</div>
                    </div>
                    <div class="lb-profit">NPR ${data.profit.toLocaleString()}</div>
                </div>`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadLeaderboard();
            refreshInterval = setInterval(loadLeaderboard, 60000); // Auto-refresh every 60s
        });
    </script>
</body>
</html>

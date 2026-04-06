<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Games</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; }
        .admin-nav { background: #1a1a2e; color: #fff; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
        .admin-nav .logo { font-weight: 800; font-size: 1.2rem; }
        .admin-nav .nav-links { display: flex; gap: 20px; }
        .admin-nav a { color: #aaa; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .admin-nav a:hover, .admin-nav a.active { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        input[type="number"], input[type="range"] { padding: 6px 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.85rem; width: 80px; }
        .toggle { width: 48px; height: 24px; background: #ccc; border-radius: 12px; position: relative; cursor: pointer; transition: background 0.2s; }
        .toggle.on { background: #1D9E75; }
        .toggle::after { content: ''; width: 20px; height: 20px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: left 0.2s; }
        .toggle.on::after { left: 26px; }
        .save-btn { padding: 6px 16px; background: #7F77DD; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.8rem; }
        .save-btn:hover { background: #6B63C8; }
        .success-msg { background: #e6f9f0; color: #1D9E75; padding: 8px 16px; border-radius: 8px; margin-bottom: 16px; display: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users">Users</a>
            <a href="/admin/games" class="active">Games</a><a href="/admin/withdrawals">Withdrawals</a>
            <a href="/admin/finance">Finance</a><a href="/admin/fraud">Fraud</a><a href="/admin/audit">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>🎮 Game Configuration</h2>
        <div id="successMsg" class="success-msg">✅ Config saved!</div>
        <div class="card">
            <table><thead><tr><th>Game</th><th>Win Ratio %</th><th>Min Bet</th><th>Max Bet</th><th>Enabled</th><th></th></tr></thead>
            <tbody id="gamesTable"><tr><td colspan="6">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <script>
        let games = [];
        async function loadGames() {
            const res = await fetch('/api/admin/games');
            const data = await res.json();
            games = data.data || [];
            renderGames();
        }
        function renderGames() {
            document.getElementById('gamesTable').innerHTML = games.map((g, i) => `<tr>
                <td><strong>${g.display_name}</strong><br><small style="color:#888">${g.game_slug}</small></td>
                <td><input type="number" id="wr_${i}" value="${g.win_ratio}" min="5" max="50" step="0.5"> %</td>
                <td><input type="number" id="min_${i}" value="${g.min_bet}" min="1"></td>
                <td><input type="number" id="max_${i}" value="${g.max_bet}" min="10"></td>
                <td><div class="toggle ${g.is_enabled ? 'on' : ''}" id="en_${i}" onclick="this.classList.toggle('on')"></div></td>
                <td><button class="save-btn" onclick="saveGame(${i})">Save</button></td>
            </tr>`).join('');
        }
        async function saveGame(i) {
            const g = games[i];
            const body = {
                win_ratio: parseFloat(document.getElementById(`wr_${i}`).value),
                min_bet: parseFloat(document.getElementById(`min_${i}`).value),
                max_bet: parseFloat(document.getElementById(`max_${i}`).value),
                is_enabled: document.getElementById(`en_${i}`).classList.contains('on') ? 1 : 0
            };
            await fetch(`/admin/games/${g.game_slug}/config`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            const msg = document.getElementById('successMsg');
            msg.style.display = 'block';
            setTimeout(() => msg.style.display = 'none', 3000);
        }
        loadGames();
    </script>
</body>
</html>

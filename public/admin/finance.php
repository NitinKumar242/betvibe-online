<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .chart-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .chart-title { font-weight: 700; margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users">Users</a><a href="/admin/games">Games</a>
            <a href="/admin/withdrawals">Withdrawals</a><a href="/admin/finance" class="active">Finance</a>
            <a href="/admin/fraud">Fraud</a><a href="/admin/audit">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>📈 Finance Overview</h2>
        <div class="chart-card"><div class="chart-title">Revenue (30 Days)</div><canvas id="revenueChart" height="120"></canvas></div>
        <div class="grid-2">
            <div class="card"><div class="chart-title">Game Breakdown</div>
                <table><thead><tr><th>Game</th><th>Bets</th><th>Wagered</th><th>Profit</th></tr></thead>
                <tbody id="gameTable"></tbody></table>
            </div>
            <div class="card"><div class="chart-title">Top Depositors</div>
                <table><thead><tr><th>User</th><th>Total Deposited</th><th>Deposits</th></tr></thead>
                <tbody id="depositorsTable"></tbody></table>
            </div>
        </div>
    </div>
    <script>
        async function load() {
            const res = await fetch('/api/admin/finance');
            const { data } = await res.json();
            // Revenue chart
            new Chart(document.getElementById('revenueChart').getContext('2d'), {
                type: 'line', data: {
                    labels: data.revenue_chart.map(d => d.date),
                    datasets: [
                        { label: 'Deposits', data: data.revenue_chart.map(d => parseFloat(d.deposits)), borderColor: '#1D9E75', fill: false, tension: 0.3 },
                        { label: 'Withdrawals', data: data.revenue_chart.map(d => parseFloat(d.withdrawals)), borderColor: '#e24b4a', fill: false, tension: 0.3 }
                    ]
                }, options: { responsive: true }
            });
            // Game breakdown
            document.getElementById('gameTable').innerHTML = data.game_breakdown.map(g => `<tr>
                <td>${g.display_name}</td><td>${g.total_bets}</td>
                <td>NPR ${parseFloat(g.total_wagered).toLocaleString()}</td>
                <td style="color:${parseFloat(g.house_profit) >= 0 ? '#1D9E75' : '#e24b4a'}">NPR ${parseFloat(g.house_profit).toLocaleString()}</td>
            </tr>`).join('');
            // Top depositors
            document.getElementById('depositorsTable').innerHTML = data.top_depositors.map(d => `<tr>
                <td>${d.username}</td><td>NPR ${parseFloat(d.total_deposited).toLocaleString()}</td><td>${d.deposit_count}</td>
            </tr>`).join('');
        }
        load();
    </script>
</body>
</html>

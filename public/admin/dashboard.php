<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; min-height: 100vh; }
        .admin-nav { background: #1a1a2e; color: #fff; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
        .admin-nav .logo { font-weight: 800; font-size: 1.2rem; }
        .admin-nav .nav-links { display: flex; gap: 20px; align-items: center; }
        .admin-nav a { color: #aaa; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: color 0.2s; }
        .admin-nav a:hover, .admin-nav a.active { color: #fff; }
        .admin-nav .logout-btn { background: #e24b4a; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; color: #1a1a2e; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .stat-label { font-size: 0.8rem; color: #888; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
        .stat-val { font-size: 1.6rem; font-weight: 800; }
        .stat-val.green { color: #1D9E75; }
        .stat-val.red { color: #e24b4a; }
        .stat-val.blue { color: #7F77DD; }
        .stat-val.orange { color: #EF9F27; }
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 28px; }
        .chart-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .chart-title { font-weight: 700; margin-bottom: 16px; color: #1a1a2e; }
        .table-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
        .badge.win { background: #e6f9f0; color: #1D9E75; }
        .badge.loss { background: #fff5f5; color: #e24b4a; }
        .badge.pending { background: #fff8e6; color: #EF9F27; }
        .loading { text-align: center; padding: 40px; color: #888; }
        @media (max-width: 768px) { .charts-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard" class="active">Dashboard</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/games">Games</a>
            <a href="/admin/withdrawals">Withdrawals</a>
            <a href="/admin/finance">Finance</a>
            <a href="/admin/fraud">Fraud</a>
            <a href="/admin/audit">Audit Log</a>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </nav>
    <div class="container">
        <h2>📊 Dashboard</h2>
        <div class="stats-grid" id="statsGrid"><div class="loading">Loading stats...</div></div>
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-title">30-Day Revenue</div>
                <canvas id="revenueChart" height="200"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Revenue by Game</div>
                <canvas id="gameChart" height="200"></canvas>
            </div>
        </div>
        <div class="table-card">
            <div class="chart-title">Recent Bets</div>
            <table><thead><tr><th>User</th><th>Game</th><th>Amount</th><th>Result</th><th>Payout</th><th>Time</th></tr></thead>
            <tbody id="recentBets"><tr><td colspan="6" class="loading">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <script>
        let revenueChartInstance, gameChartInstance;
        async function loadDashboard() {
            try {
                const res = await fetch('/api/admin/dashboard-data');
                const { data } = await res.json();
                renderStats(data.stats);
                renderRevenueChart(data.revenue_30d);
                renderGameChart(data.game_revenue);
                renderRecentBets(data.recent_bets);
            } catch (err) { console.error(err); }
        }
        function renderStats(s) {
            document.getElementById('statsGrid').innerHTML = `
                <div class="stat-card"><div class="stat-label">Deposits Today</div><div class="stat-val green">NPR ${s.deposits_today.toLocaleString()}</div></div>
                <div class="stat-card"><div class="stat-label">Withdrawals Today</div><div class="stat-val red">NPR ${s.withdrawals_today.toLocaleString()}</div></div>
                <div class="stat-card"><div class="stat-label">House Profit</div><div class="stat-val ${s.house_profit_today >= 0 ? 'green' : 'red'}">NPR ${s.house_profit_today.toLocaleString()}</div></div>
                <div class="stat-card"><div class="stat-label">Active Users</div><div class="stat-val blue">${s.active_users_today}</div></div>
                <div class="stat-card"><div class="stat-label">New Users</div><div class="stat-val blue">${s.new_users_today}</div></div>
                <div class="stat-card"><div class="stat-label">Pending Withdrawals</div><div class="stat-val orange">${s.pending_withdrawals} (NPR ${s.pending_withdrawal_amount.toLocaleString()})</div></div>`;
        }
        function renderRevenueChart(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            if (revenueChartInstance) revenueChartInstance.destroy();
            revenueChartInstance = new Chart(ctx, {
                type: 'bar', data: {
                    labels: data.map(d => d.date),
                    datasets: [
                        { label: 'Deposits', data: data.map(d => parseFloat(d.deposits)), backgroundColor: 'rgba(29,158,117,0.7)' },
                        { label: 'Withdrawals', data: data.map(d => parseFloat(d.withdrawals)), backgroundColor: 'rgba(226,75,74,0.7)' }
                    ]
                }, options: { responsive: true, scales: { x: { display: false } } }
            });
        }
        function renderGameChart(data) {
            const ctx = document.getElementById('gameChart').getContext('2d');
            if (gameChartInstance) gameChartInstance.destroy();
            const colors = ['#7F77DD','#1D9E75','#EF9F27','#E24B4A','#4ECDC4','#FF6B6B','#45B7D1','#F9CA24','#6C5CE7','#A29BFE','#FD79A8','#00B894','#E17055','#74B9FF','#DFE6E9','#636e72'];
            gameChartInstance = new Chart(ctx, {
                type: 'doughnut', data: {
                    labels: data.map(d => d.display_name || d.game),
                    datasets: [{ data: data.map(d => Math.max(0, parseFloat(d.house_profit))), backgroundColor: colors }]
                }, options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
            });
        }
        function renderRecentBets(bets) {
            const tbody = document.getElementById('recentBets');
            if (!bets.length) { tbody.innerHTML = '<tr><td colspan="6">No bets yet</td></tr>'; return; }
            tbody.innerHTML = bets.map(b => `<tr>
                <td>${b.username}</td><td>${b.game_name}</td>
                <td>NPR ${parseFloat(b.bet_amount).toFixed(0)}</td>
                <td><span class="badge ${b.result}">${b.result}</span></td>
                <td>NPR ${parseFloat(b.payout).toFixed(0)}</td>
                <td>${new Date(b.created_at).toLocaleTimeString()}</td>
            </tr>`).join('');
        }
        async function logout() {
            await fetch('/api/admin/logout', { method: 'POST' });
            window.location.href = '/admin/login';
        }
        loadDashboard();
        setInterval(loadDashboard, 30000);
    </script>
</body>
</html>

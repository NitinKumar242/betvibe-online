<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Fraud Detection</title>
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
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #fff5f5; color: #e24b4a; }
        .action-btn { padding: 6px 14px; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; margin-right: 4px; }
        .action-btn.dismiss { background: #f0f0f0; color: #888; }
        .action-btn.ban { background: #fff5f5; color: #e24b4a; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users">Users</a><a href="/admin/games">Games</a>
            <a href="/admin/withdrawals">Withdrawals</a><a href="/admin/finance">Finance</a>
            <a href="/admin/fraud" class="active">Fraud</a><a href="/admin/audit">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>🚨 Fraud Detection</h2>
        <div class="card">
            <table><thead><tr><th>ID</th><th>Username</th><th>Reason</th><th>IP</th><th>Balance</th><th>Wagered</th><th>Actions</th></tr></thead>
            <tbody id="fraudTable"><tr><td colspan="7">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <script>
        async function loadFraud() {
            const res = await fetch('/api/admin/fraud');
            const { data } = await res.json();
            const tbody = document.getElementById('fraudTable');
            if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="7">No flagged users 🎉</td></tr>'; return; }
            tbody.innerHTML = data.map(u => `<tr>
                <td>${u.id}</td><td><strong>${u.username}</strong></td>
                <td><span class="badge">${u.fraud_reason || 'Unknown'}</span></td>
                <td>${u.last_ip || '—'}</td>
                <td>NPR ${parseFloat(u.real_balance||0).toFixed(0)}</td>
                <td>NPR ${parseFloat(u.total_wagered||0).toFixed(0)}</td>
                <td>
                    <button class="action-btn dismiss" onclick="dismiss(${u.id})">Dismiss</button>
                    <button class="action-btn ban" onclick="banFraud(${u.id})">Ban</button>
                </td>
            </tr>`).join('');
        }
        async function dismiss(id) {
            await fetch(`/admin/users/${id}/unban`, { method: 'POST' });
            loadFraud();
        }
        async function banFraud(id) {
            if (!confirm('Ban this user?')) return;
            await fetch(`/admin/users/${id}/ban`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason: 'Fraud detected' }) });
            loadFraud();
        }
        loadFraud();
    </script>
</body>
</html>

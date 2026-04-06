<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Audit Log</title>
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
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #e8e6ff; color: #7F77DD; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users">Users</a><a href="/admin/games">Games</a>
            <a href="/admin/withdrawals">Withdrawals</a><a href="/admin/finance">Finance</a>
            <a href="/admin/fraud">Fraud</a><a href="/admin/audit" class="active">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>📝 Admin Audit Log</h2>
        <div class="card">
            <table><thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
            <tbody id="auditTable"><tr><td colspan="6">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <script>
        async function loadAudit() {
            const res = await fetch('/api/admin/audit');
            const { data } = await res.json();
            const tbody = document.getElementById('auditTable');
            if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="6">No audit logs</td></tr>'; return; }
            tbody.innerHTML = data.map(l => `<tr>
                <td>${new Date(l.created_at).toLocaleString()}</td>
                <td>${l.admin_username || '#' + l.admin_id}</td>
                <td><span class="badge">${l.action}</span></td>
                <td>${l.target_type ? l.target_type + ' #' + l.target_id : '—'}</td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis">${l.new_value || '—'}</td>
                <td>${l.ip || '—'}</td>
            </tr>`).join('');
        }
        loadAudit();
    </script>
</body>
</html>

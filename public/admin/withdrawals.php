<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Withdrawals</title>
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
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .filter-tab { padding: 10px 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .filter-tab.active { background: #7F77DD; color: #fff; border-color: #7F77DD; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
        .badge.pending { background: #fff8e6; color: #EF9F27; }
        .badge.approved { background: #e6f9f0; color: #1D9E75; }
        .badge.rejected { background: #fff5f5; color: #e24b4a; }
        .action-btn { padding: 6px 14px; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; margin-right: 4px; }
        .action-btn.approve { background: #e6f9f0; color: #1D9E75; }
        .action-btn.reject { background: #fff5f5; color: #e24b4a; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users">Users</a>
            <a href="/admin/games">Games</a><a href="/admin/withdrawals" class="active">Withdrawals</a>
            <a href="/admin/finance">Finance</a><a href="/admin/fraud">Fraud</a><a href="/admin/audit">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>💸 Withdrawal Queue</h2>
        <div class="filter-tabs">
            <div class="filter-tab active" onclick="loadWithdrawals('pending')">Pending</div>
            <div class="filter-tab" onclick="loadWithdrawals('approved')">Approved</div>
            <div class="filter-tab" onclick="loadWithdrawals('rejected')">Rejected</div>
        </div>
        <div class="card">
            <table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Account</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="wdTable"><tr><td colspan="7">Loading...</td></tr></tbody></table>
        </div>
    </div>
    <script>
        async function loadWithdrawals(status = 'pending') {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            const res = await fetch(`/admin/withdrawals?status=${status}`);
            const { data } = await res.json();
            const tbody = document.getElementById('wdTable');
            if (!data || !data.length) { tbody.innerHTML = '<tr><td colspan="7">No withdrawals</td></tr>'; return; }
            tbody.innerHTML = data.map(w => `<tr>
                <td>${w.id}</td><td>${w.username}</td><td><strong>NPR ${parseFloat(w.amount).toLocaleString()}</strong></td>
                <td>${w.watchpay_account}</td><td>${new Date(w.requested_at).toLocaleString()}</td>
                <td><span class="badge ${w.status}">${w.status}</span></td>
                <td>${w.status === 'pending' ? `
                    <button class="action-btn approve" onclick="approveWd(${w.id})">✅ Approve</button>
                    <button class="action-btn reject" onclick="rejectWd(${w.id})">❌ Reject</button>
                ` : '—'}</td>
            </tr>`).join('');
        }
        async function approveWd(id) {
            if (!confirm('Approve this withdrawal?')) return;
            await fetch(`/admin/withdrawals/${id}/approve`, { method: 'POST' });
            loadWithdrawals('pending');
        }
        async function rejectWd(id) {
            const reason = prompt('Rejection reason:');
            if (!reason) return;
            await fetch(`/admin/withdrawals/${id}/reject`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason }) });
            loadWithdrawals('pending');
        }
        loadWithdrawals('pending');
    </script>
</body>
</html>

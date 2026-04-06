<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; }
        .admin-nav { background: #1a1a2e; color: #fff; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
        .admin-nav .logo { font-weight: 800; font-size: 1.2rem; }
        .admin-nav .nav-links { display: flex; gap: 20px; align-items: center; }
        .admin-nav a { color: #aaa; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .admin-nav a:hover, .admin-nav a.active { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; }
        .search-bar { display: flex; gap: 12px; margin-bottom: 20px; }
        .search-input { flex: 1; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 0.95rem; font-family: 'Inter', sans-serif; }
        .search-input:focus { outline: none; border-color: #7F77DD; }
        .search-btn { padding: 12px 24px; background: #7F77DD; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; }
        th { font-weight: 700; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
        .badge.active { background: #e6f9f0; color: #1D9E75; }
        .badge.banned { background: #fff5f5; color: #e24b4a; }
        .action-btn { padding: 6px 12px; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; margin-right: 4px; }
        .action-btn.view { background: #e8e6ff; color: #7F77DD; }
        .action-btn.ban { background: #fff5f5; color: #e24b4a; }
        .pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: center; }
        .pagination button { padding: 8px 14px; border: 1px solid #e0e0e0; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .pagination button.active { background: #7F77DD; color: #fff; border-color: #7F77DD; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="logo">🎰 BetVibe Admin</div>
        <div class="nav-links">
            <a href="/admin/dashboard">Dashboard</a><a href="/admin/users" class="active">Users</a>
            <a href="/admin/games">Games</a><a href="/admin/withdrawals">Withdrawals</a>
            <a href="/admin/finance">Finance</a><a href="/admin/fraud">Fraud</a><a href="/admin/audit">Audit</a>
        </div>
    </nav>
    <div class="container">
        <h2>👥 User Management</h2>
        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by username, email, or phone...">
            <button class="search-btn" onclick="searchUsers()">Search</button>
        </div>
        <div class="card">
            <table><thead><tr><th>ID</th><th>Username</th><th>Balance</th><th>Deposited</th><th>Withdrawn</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody id="usersTable"><tr><td colspan="8">Loading...</td></tr></tbody></table>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
    <script>
        let currentPage = 1;
        async function loadUsers(page = 1, search = '') {
            currentPage = page;
            const res = await fetch(`/api/admin/users?page=${page}&search=${encodeURIComponent(search)}`);
            const { data, pagination } = await res.json();
            renderUsers(data); renderPagination(pagination);
        }
        function renderUsers(users) {
            const tbody = document.getElementById('usersTable');
            if (!users.length) { tbody.innerHTML = '<tr><td colspan="8">No users found</td></tr>'; return; }
            tbody.innerHTML = users.map(u => `<tr>
                <td>${u.id}</td><td><strong>${u.username}</strong></td>
                <td>NPR ${parseFloat(u.real_balance||0).toFixed(0)}</td>
                <td>NPR ${parseFloat(u.total_deposited||0).toFixed(0)}</td>
                <td>NPR ${parseFloat(u.total_withdrawn||0).toFixed(0)}</td>
                <td><span class="badge ${u.is_banned ? 'banned' : 'active'}">${u.is_banned ? 'Banned' : 'Active'}</span></td>
                <td>${new Date(u.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="action-btn view" onclick="viewUser(${u.id})">View</button>
                    <button class="action-btn ban" onclick="toggleBan(${u.id}, ${u.is_banned})">${u.is_banned ? 'Unban' : 'Ban'}</button>
                </td>
            </tr>`).join('');
        }
        function renderPagination(p) {
            const el = document.getElementById('pagination');
            let html = '';
            for (let i = 1; i <= Math.min(p.total_pages, 10); i++) {
                html += `<button class="${i === p.page ? 'active' : ''}" onclick="loadUsers(${i}, document.getElementById('searchInput').value)">${i}</button>`;
            }
            el.innerHTML = html;
        }
        function searchUsers() { loadUsers(1, document.getElementById('searchInput').value); }
        function viewUser(id) { window.location.href = `/admin/users/${id}`; }
        async function toggleBan(id, isBanned) {
            const action = isBanned ? 'unban' : 'ban';
            if (!confirm(`${action} user #${id}?`)) return;
            const body = isBanned ? {} : { reason: prompt('Ban reason:') || 'Banned by admin' };
            await fetch(`/admin/users/${id}/${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            loadUsers(currentPage, document.getElementById('searchInput').value);
        }
        document.getElementById('searchInput').addEventListener('keypress', e => { if (e.key === 'Enter') searchUsers(); });
        loadUsers();
    </script>
</body>
</html>

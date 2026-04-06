<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe Admin - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card {
            background: #fff; border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 420px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .logo { text-align: center; font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; color: #1a1a2e; }
        .subtitle { text-align: center; color: #888; font-size: 0.9rem; margin-bottom: 32px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 0.9rem; }
        .form-input {
            width: 100%; padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 10px;
            font-size: 1rem; font-family: 'Inter', sans-serif; transition: border-color 0.2s;
        }
        .form-input:focus { outline: none; border-color: #7F77DD; }
        .btn-login {
            width: 100%; padding: 16px; background: linear-gradient(135deg, #7F77DD, #6B63C8);
            border: none; border-radius: 10px; color: #fff; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(127,119,221,0.3); }
        .btn-login:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .error-msg { background: #fff5f5; border: 1px solid #fecaca; color: #e24b4a; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">🎰 BetVibe</div>
        <div class="subtitle">Admin Panel Login</div>
        <div class="error-msg" id="errorMsg"></div>
        <form onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" id="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" id="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login" id="loginBtn">Login</button>
        </form>
    </div>
    <script>
        async function handleLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('loginBtn');
            const errEl = document.getElementById('errorMsg');
            errEl.style.display = 'none';
            btn.disabled = true; btn.textContent = 'Logging in...';
            try {
                const res = await fetch('/api/admin/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = '/admin/dashboard';
                } else {
                    errEl.textContent = data.error || 'Login failed';
                    errEl.style.display = 'block';
                }
            } catch (err) {
                errEl.textContent = 'Connection error';
                errEl.style.display = 'block';
            }
            btn.disabled = false; btn.textContent = 'Login';
        }
    </script>
</body>
</html>

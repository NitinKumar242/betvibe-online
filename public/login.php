<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BetVibe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D0D0D;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 400px;
            min-width: 375px;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: #7F77DD;
        }

        .logo p {
            color: #888;
            margin-top: 8px;
        }

        .form-card {
            background-color: #1A1A1A;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #ccc;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background-color: #1A1A1A;
            border: 1px solid #333;
            border-radius: 8px;
            color: #ffffff;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #7F77DD;
        }

        .form-group input::placeholder {
            color: #666;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            accent-color: #7F77DD;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #ccc;
            cursor: pointer;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background-color: #7F77DD;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #6B65C0;
        }

        .btn:disabled {
            background-color: #444;
            cursor: not-allowed;
        }

        .error-message {
            color: #E24B4A;
            font-size: 14px;
            margin-top: 8px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .form-footer {
            margin-top: 24px;
            text-align: center;
        }

        .form-footer a {
            color: #7F77DD;
            text-decoration: none;
            font-size: 14px;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn.loading .btn-text {
            display: none;
        }

        .btn.loading .loading {
            display: inline-block;
        }

        .btn .loading {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <h1>BetVibe</h1>
            <p>Login to continue</p>
        </div>

        <div class="form-card">
            <form id="loginForm">
                <div class="form-group">
                    <label for="identifier">Email or Phone</label>
                    <input type="text" id="identifier" name="identifier" placeholder="Enter email or phone" required>
                    <div class="error-message" id="identifierError"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                    <div class="error-message" id="passwordError"></div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn" id="loginBtn">
                    <span class="loading"></span>
                    <span class="btn-text">Login Karo</span>
                </button>

                <div class="error-message" id="formError"></div>
            </form>

            <div class="form-footer">
                <p>Don't have an account? <a href="/register">Register</a></p>
            </div>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const formError = document.getElementById('formError');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous errors
            clearErrors();

            // Get form data
            const formData = {
                identifier: document.getElementById('identifier').value.trim(),
                password: document.getElementById('password').value,
                remember_me: document.getElementById('remember').checked
            };

            // Basic validation
            if (!formData.identifier) {
                showError('identifierError', 'Email or phone is required');
                return;
            }

            if (!formData.password) {
                showError('passwordError', 'Password is required');
                return;
            }

            // Show loading state
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;

            try {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect to home
                    window.location.href = '/';
                } else {
                    // Show error
                    showError('formError', data.error || 'Login failed');
                }
            } catch (error) {
                showError('formError', 'Network error. Please try again.');
            } finally {
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }
        });

        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }

        function clearErrors() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.textContent = '';
                el.classList.remove('show');
            });
        }
    </script>
</body>

</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BetVibe</title>
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

        .form-group input:disabled {
            background-color: #2A2A2A;
            cursor: not-allowed;
        }

        .form-group input.valid {
            border-color: #4CAF50;
        }

        .form-group input.invalid {
            border-color: #E24B4A;
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
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .success-message {
            color: #4CAF50;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }

        .success-message.show {
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

        .contact-toggle {
            display: flex;
            margin-bottom: 20px;
            background-color: #2A2A2A;
            border-radius: 8px;
            padding: 4px;
        }

        .contact-toggle button {
            flex: 1;
            padding: 10px;
            background-color: transparent;
            color: #888;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
        }

        .contact-toggle button.active {
            background-color: #7F77DD;
            color: #ffffff;
        }

        .contact-type-group {
            display: none;
        }

        .contact-type-group.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <h1>BetVibe</h1>
            <p>Create your account</p>
        </div>

        <div class="form-card">
            <form id="registerForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required
                        autocomplete="username">
                    <div class="error-message" id="usernameError"></div>
                    <div class="success-message" id="usernameSuccess"></div>
                </div>

                <div class="contact-toggle">
                    <button type="button" class="active" data-type="email">Email</button>
                    <button type="button" data-type="phone">Phone</button>
                </div>

                <div class="form-group contact-type-group active" id="emailGroup">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" autocomplete="email">
                    <div class="error-message" id="emailError"></div>
                </div>

                <div class="form-group contact-type-group" id="phoneGroup">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" autocomplete="tel">
                    <div class="error-message" id="phoneError"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required
                        autocomplete="new-password">
                    <div class="error-message" id="passwordError"></div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm"
                        placeholder="Confirm your password" required autocomplete="new-password">
                    <div class="error-message" id="passwordConfirmError"></div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="age_confirm" name="age_confirm" required>
                    <label for="age_confirm">I am 18+ years old</label>
                </div>

                <div class="form-group">
                    <label for="ref_code">Referral Code (Optional)</label>
                    <input type="text" id="ref_code" name="ref_code" placeholder="Enter referral code" readonly>
                    <div class="error-message" id="refCodeError"></div>
                </div>

                <button type="submit" class="btn" id="registerBtn">
                    <span class="loading"></span>
                    <span class="btn-text">Account Banao</span>
                </button>

                <div class="error-message" id="formError"></div>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="/login">Login</a></p>
            </div>
        </div>
    </div>

    <script>
        const registerForm = document.getElementById('registerForm');
        const registerBtn = document.getElementById('registerBtn');
        const formError = document.getElementById('formError');
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const passwordConfirmInput = document.getElementById('password_confirm');
        const refCodeInput = document.getElementById('ref_code');

        // Contact type toggle
        const contactToggle = document.querySelector('.contact-toggle');
        const contactTypeButtons = contactToggle.querySelectorAll('button');
        const emailGroup = document.getElementById('emailGroup');
        const phoneGroup = document.getElementById('phoneGroup');

        contactTypeButtons.forEach(button => {
            button.addEventListener('click', () => {
                contactTypeButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const type = button.dataset.type;
                if (type === 'email') {
                    emailGroup.classList.add('active');
                    phoneGroup.classList.remove('active');
                    emailInput.required = true;
                    phoneInput.required = false;
                } else {
                    phoneGroup.classList.add('active');
                    emailGroup.classList.remove('active');
                    phoneInput.required = true;
                    emailInput.required = false;
                }
            });
        });

        // Check referral code from URL
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        if (refCode) {
            refCodeInput.value = refCode;
        }

        // Username availability check with debounce
        let usernameCheckTimeout;
        usernameInput.addEventListener('input', () => {
            clearTimeout(usernameCheckTimeout);
            const username = usernameInput.value.trim();

            if (username.length < 3) {
                return;
            }

            usernameCheckTimeout = setTimeout(() => {
                checkUsernameAvailability(username);
            }, 500);
        });

        async function checkUsernameAvailability(username) {
            try {
                const response = await fetch(`/api/auth/check-username?username=${encodeURIComponent(username)}`);
                const data = await response.json();

                const usernameError = document.getElementById('usernameError');
                const usernameSuccess = document.getElementById('usernameSuccess');

                if (data.available) {
                    usernameInput.classList.remove('invalid');
                    usernameInput.classList.add('valid');
                    usernameError.classList.remove('show');
                    usernameSuccess.textContent = 'Username available';
                    usernameSuccess.classList.add('show');
                } else {
                    usernameInput.classList.remove('valid');
                    usernameInput.classList.add('invalid');
                    usernameSuccess.classList.remove('show');
                    usernameError.textContent = data.error || 'Username not available';
                    usernameError.classList.add('show');
                }
            } catch (error) {
                console.error('Username check failed:', error);
            }
        }

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous errors
            clearErrors();

            // Get form data
            const formData = {
                username: usernameInput.value.trim(),
                email: emailInput.value.trim(),
                phone: phoneInput.value.trim(),
                password: passwordInput.value,
                password_confirm: passwordConfirmInput.value,
                age_confirm: document.getElementById('age_confirm').checked ? '1' : '0',
                ref_code: refCodeInput.value.trim()
            };

            // Basic validation
            let hasError = false;

            if (!formData.username) {
                showError('usernameError', 'Username is required');
                hasError = true;
            } else if (!/^[a-zA-Z0-9_]{3,20}$/.test(formData.username)) {
                showError('usernameError', 'Username must be 3-20 characters, alphanumeric and underscore only');
                hasError = true;
            }

            if (!formData.email && !formData.phone) {
                showError('emailError', 'Email or phone is required');
                showError('phoneError', 'Email or phone is required');
                hasError = true;
            } else if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
                showError('emailError', 'Invalid email format');
                hasError = true;
            } else if (formData.phone && !/^[0-9]{10,15}$/.test(formData.phone)) {
                showError('phoneError', 'Invalid phone format');
                hasError = true;
            }

            if (!formData.password) {
                showError('passwordError', 'Password is required');
                hasError = true;
            } else if (formData.password.length < 8) {
                showError('passwordError', 'Password must be at least 8 characters');
                hasError = true;
            }

            if (formData.password !== formData.password_confirm) {
                showError('passwordConfirmError', 'Passwords do not match');
                hasError = true;
            }

            if (formData.age_confirm !== '1') {
                showError('formError', 'You must confirm you are 18+ years old');
                hasError = true;
            }

            if (hasError) {
                return;
            }

            // Show loading state
            registerBtn.classList.add('loading');
            registerBtn.disabled = true;

            try {
                const response = await fetch('/api/auth/register', {
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
                    // Show errors
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const errorElement = document.getElementById(`${field}Error`);
                            if (errorElement) {
                                showError(field + 'Error', data.errors[field]);
                            }
                        });
                    } else {
                        showError('formError', data.error || 'Registration failed');
                    }
                }
            } catch (error) {
                showError('formError', 'Network error. Please try again.');
            } finally {
                registerBtn.classList.remove('loading');
                registerBtn.disabled = false;
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
            document.querySelectorAll('.success-message').forEach(el => {
                el.textContent = '';
                el.classList.remove('show');
            });
            document.querySelectorAll('input').forEach(el => {
                el.classList.remove('valid', 'invalid');
            });
        }
    </script>
</body>

</html>
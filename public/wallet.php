<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Wallet</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .balance-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .balance-item {
            text-align: center;
            margin-bottom: 20px;
        }

        .balance-item:last-child {
            margin-bottom: 0;
        }

        .balance-label {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 5px;
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .balance-amount.real {
            color: #00d26a;
        }

        .balance-amount.bonus {
            color: #a855f7;
        }

        .balance-note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-weight: 600;
        }

        .tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .tab-content {
            display: none;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #ccc;
        }

        .form-input {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid transparent;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.15);
        }

        .quick-amounts {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .quick-amount {
            flex: 1;
            min-width: 80px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid transparent;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .quick-amount:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #667eea;
        }

        .quick-amount.selected {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: transparent;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #ffc107;
        }

        .balance-check {
            background: rgba(0, 210, 106, 0.1);
            border: 1px solid rgba(0, 210, 106, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .balance-check .status {
            font-weight: 700;
        }

        .balance-check .status.yes {
            color: #00d26a;
        }

        .balance-check .status.no {
            color: #ff6b6b;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .filter-tab {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-tab:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .filter-tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transaction-table th,
        .transaction-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .transaction-table th {
            font-weight: 600;
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .transaction-table td {
            font-size: 0.95rem;
        }

        .transaction-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.completed {
            background: rgba(0, 210, 106, 0.2);
            color: #00d26a;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-badge.failed,
        .status-badge.rejected {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }

        .amount-positive {
            color: #00d26a;
        }

        .amount-negative {
            color: #ff6b6b;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination button.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6b6b;
            font-size: 0.9rem;
        }

        .success-message {
            background: rgba(0, 210, 106, 0.1);
            border: 1px solid rgba(0, 210, 106, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #00d26a;
            font-size: 0.9rem;
        }

        @media (max-width: 600px) {
            .balance-amount {
                font-size: 2rem;
            }

            .quick-amounts {
                gap: 8px;
            }

            .quick-amount {
                min-width: 70px;
                padding: 10px;
                font-size: 0.9rem;
            }

            .transaction-table {
                font-size: 0.85rem;
            }

            .transaction-table th,
            .transaction-table td {
                padding: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>💰 Wallet</h1>
        </div>

        <!-- Balance Section -->
        <div class="balance-section">
            <div class="balance-item">
                <div class="balance-label">Real Balance</div>
                <div class="balance-amount real" id="realBalance">NPR 0.00</div>
            </div>
            <div class="balance-item">
                <div class="balance-label">Bonus Coins</div>
                <div class="balance-amount bonus" id="bonusBalance">0</div>
                <div class="balance-note">Not withdrawable</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="deposit">Deposit</div>
            <div class="tab" data-tab="withdraw">Withdraw</div>
            <div class="tab" data-tab="history">History</div>
        </div>

        <!-- Deposit Tab -->
        <div class="tab-content active" id="depositTab">
            <div id="depositError" class="error-message" style="display: none;"></div>
            <div class="form-group">
                <label class="form-label">Amount</label>
                <div class="quick-amounts">
                    <div class="quick-amount" data-amount="100">NPR 100</div>
                    <div class="quick-amount" data-amount="500">NPR 500</div>
                    <div class="quick-amount" data-amount="1000">NPR 1000</div>
                    <div class="quick-amount" data-amount="2000">NPR 2000</div>
                </div>
                <input type="number" class="form-input" id="depositAmount" placeholder="Enter amount" min="100">
            </div>
            <button class="btn" id="depositBtn">Deposit Karo</button>
        </div>

        <!-- Withdraw Tab -->
        <div class="tab-content" id="withdrawTab">
            <div id="withdrawError" class="error-message" style="display: none;"></div>
            <div id="withdrawSuccess" class="success-message" style="display: none;"></div>

            <div class="balance-check">
                <div>Available: <span id="availableBalance">NPR 0.00</span></div>
                <div>Wagering met: <span class="status" id="wageringStatus">Loading...</span></div>
            </div>

            <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" class="form-input" id="withdrawAmount" placeholder="Enter amount" min="500"
                    max="50000">
            </div>
            <div class="form-group">
                <label class="form-label">WatchPay Account</label>
                <input type="text" class="form-input" id="watchpayAccount" placeholder="Enter WatchPay account number">
            </div>
            <button class="btn" id="withdrawBtn">Withdraw Request Bhejo</button>
            <div class="warning">
                ⚠️ Minimum NPR 500 | Processing 2-24 hours
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-content" id="historyTab">
            <div class="filter-tabs">
                <div class="filter-tab active" data-filter="all">All</div>
                <div class="filter-tab" data-filter="deposit">Deposits</div>
                <div class="filter-tab" data-filter="withdraw">Withdrawals</div>
                <div class="filter-tab" data-filter="win">Wins</div>
                <div class="filter-tab" data-filter="loss">Losses</div>
                <div class="filter-tab" data-filter="bonus">Bonuses</div>
            </div>
            <div id="transactionsContainer">
                <div class="loading">Loading transactions...</div>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <script>
        // State
        let currentTab = 'deposit';
        let currentFilter = 'all';
        let currentPage = 1;
        let totalPages = 1;
        let selectedDepositAmount = null;

        // DOM Elements
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        const filterTabs = document.querySelectorAll('.filter-tab');
        const realBalanceEl = document.getElementById('realBalance');
        const bonusBalanceEl = document.getElementById('bonusBalance');
        const availableBalanceEl = document.getElementById('availableBalance');
        const wageringStatusEl = document.getElementById('wageringStatus');
        const depositAmountInput = document.getElementById('depositAmount');
        const withdrawAmountInput = document.getElementById('withdrawAmount');
        const watchpayAccountInput = document.getElementById('watchpayAccount');
        const depositBtn = document.getElementById('depositBtn');
        const withdrawBtn = document.getElementById('withdrawBtn');
        const transactionsContainer = document.getElementById('transactionsContainer');
        const paginationContainer = document.getElementById('pagination');
        const depositErrorEl = document.getElementById('depositError');
        const withdrawErrorEl = document.getElementById('withdrawError');
        const withdrawSuccessEl = document.getElementById('withdrawSuccess');
        const quickAmounts = document.querySelectorAll('.quick-amount');

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadBalance();
            loadTransactions();
            setupEventListeners();
        });

        // Setup Event Listeners
        function setupEventListeners() {
            // Tab switching
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabName = tab.dataset.tab;
                    switchTab(tabName);
                });
            });

            // Filter tabs
            filterTabs.forEach(filterTab => {
                filterTab.addEventListener('click', () => {
                    const filter = filterTab.dataset.filter;
                    switchFilter(filter);
                });
            });

            // Quick amount buttons
            quickAmounts.forEach(btn => {
                btn.addEventListener('click', () => {
                    const amount = btn.dataset.amount;
                    selectQuickAmount(amount);
                });
            });

            // Deposit button
            depositBtn.addEventListener('click', handleDeposit);

            // Withdraw button
            withdrawBtn.addEventListener('click', handleWithdraw);
        }

        // Switch Tab
        function switchTab(tabName) {
            currentTab = tabName;

            tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabName);
            });

            tabContents.forEach(content => {
                content.classList.toggle('active', content.id === tabName + 'Tab');
            });

            if (tabName === 'history') {
                loadTransactions();
            }
        }

        // Switch Filter
        function switchFilter(filter) {
            currentFilter = filter;
            currentPage = 1;

            filterTabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.filter === filter);
            });

            loadTransactions();
        }

        // Select Quick Amount
        function selectQuickAmount(amount) {
            selectedDepositAmount = amount;
            depositAmountInput.value = amount;

            quickAmounts.forEach(btn => {
                btn.classList.toggle('selected', btn.dataset.amount === amount);
            });
        }

        // Load Balance
        async function loadBalance() {
            try {
                const response = await fetch('/api/wallet/balance');
                const data = await response.json();

                if (data.success) {
                    realBalanceEl.textContent = `NPR ${data.real_balance.toFixed(2)}`;
                    bonusBalanceEl.textContent = data.bonus_coins.toFixed(0);
                    availableBalanceEl.textContent = `NPR ${data.real_balance.toFixed(2)}`;
                }
            } catch (error) {
                console.error('Failed to load balance:', error);
            }
        }

        // Check Wagering Requirement
        async function checkWageringRequirement() {
            try {
                const response = await fetch('/api/wallet/wagering-check');
                const data = await response.json();

                if (data.success) {
                    if (data.met) {
                        wageringStatusEl.textContent = 'Yes';
                        wageringStatusEl.className = 'status yes';
                    } else {
                        wageringStatusEl.textContent = 'No';
                        wageringStatusEl.className = 'status no';
                    }
                }
            } catch (error) {
                console.error('Failed to check wagering requirement:', error);
                wageringStatusEl.textContent = 'Unknown';
                wageringStatusEl.className = 'status';
            }
        }

        // Handle Deposit
        async function handleDeposit() {
            const amount = parseFloat(depositAmountInput.value);

            if (!amount || amount < 100) {
                showError(depositErrorEl, 'Minimum deposit amount is NPR 100');
                return;
            }

            depositBtn.disabled = true;
            depositBtn.textContent = 'Processing...';

            try {
                const response = await fetch('/api/wallet/deposit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount })
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect to WatchPay checkout
                    window.location.href = data.checkout_url;
                } else {
                    showError(depositErrorEl, data.error || 'Failed to create deposit');
                }
            } catch (error) {
                showError(depositErrorEl, 'Failed to create deposit. Please try again.');
            } finally {
                depositBtn.disabled = false;
                depositBtn.textContent = 'Deposit Karo';
            }
        }

        // Handle Withdraw
        async function handleWithdraw() {
            const amount = parseFloat(withdrawAmountInput.value);
            const watchpayAccount = watchpayAccountInput.value.trim();

            if (!amount || amount < 500) {
                showError(withdrawErrorEl, 'Minimum withdrawal amount is NPR 500');
                return;
            }

            if (amount > 50000) {
                showError(withdrawErrorEl, 'Maximum withdrawal amount is NPR 50,000');
                return;
            }

            if (!watchpayAccount) {
                showError(withdrawErrorEl, 'Please enter your WatchPay account number');
                return;
            }

            withdrawBtn.disabled = true;
            withdrawBtn.textContent = 'Processing...';
            hideError(withdrawErrorEl);
            hideError(withdrawSuccessEl);

            try {
                const response = await fetch('/api/wallet/withdraw', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount, watchpay_account: watchpayAccount })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(withdrawSuccessEl, data.message);
                    withdrawAmountInput.value = '';
                    watchpayAccountInput.value = '';
                    loadBalance();
                } else {
                    showError(withdrawErrorEl, data.error || 'Failed to create withdrawal request');
                }
            } catch (error) {
                showError(withdrawErrorEl, 'Failed to create withdrawal request. Please try again.');
            } finally {
                withdrawBtn.disabled = false;
                withdrawBtn.textContent = 'Withdraw Request Bhejo';
            }
        }

        // Load Transactions
        async function loadTransactions() {
            transactionsContainer.innerHTML = '<div class="loading">Loading transactions...</div>';

            try {
                const url = `/api/wallet/transactions?page=${currentPage}&type=${currentFilter === 'all' ? '' : currentFilter}`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    totalPages = data.pagination.total_pages;
                    renderTransactions(data.data);
                    renderPagination();
                } else {
                    transactionsContainer.innerHTML = '<div class="empty-state">Failed to load transactions</div>';
                }
            } catch (error) {
                console.error('Failed to load transactions:', error);
                transactionsContainer.innerHTML = '<div class="empty-state">Failed to load transactions</div>';
            }
        }

        // Render Transactions
        function renderTransactions(transactions) {
            if (transactions.length === 0) {
                transactionsContainer.innerHTML = '<div class="empty-state">No transactions found</div>';
                return;
            }

            let html = `
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            transactions.forEach(tx => {
                const date = new Date(tx.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                const amountClass = ['deposit', 'win', 'bonus', 'referral_bonus'].includes(tx.type) ? 'amount-positive' : 'amount-negative';
                const amountPrefix = ['deposit', 'win', 'bonus', 'referral_bonus'].includes(tx.type) ? '+' : '-';

                html += `
                    <tr>
                        <td>${date}</td>
                        <td>${formatTransactionType(tx.type)}</td>
                        <td class="${amountClass}">${amountPrefix}NPR ${tx.amount.toFixed(2)}</td>
                        <td><span class="status-badge ${tx.status}">${tx.status}</span></td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            transactionsContainer.innerHTML = html;
        }

        // Format Transaction Type
        function formatTransactionType(type) {
            const types = {
                'deposit': 'Deposit',
                'withdraw': 'Withdrawal',
                'win': 'Win',
                'loss': 'Loss',
                'bonus': 'Bonus',
                'referral_bonus': 'Referral Bonus'
            };
            return types[type] || type;
        }

        // Render Pagination
        function renderPagination() {
            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">Previous</button>`;

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += '<button disabled>...</button>';
                }
            }

            // Next button
            html += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Next</button>`;

            paginationContainer.innerHTML = html;
        }

        // Change Page
        function changePage(page) {
            if (page < 1 || page > totalPages || page === currentPage) {
                return;
            }
            currentPage = page;
            loadTransactions();
        }

        // Show Error
        function showError(element, message) {
            element.textContent = message;
            element.style.display = 'block';
        }

        // Hide Error
        function hideError(element) {
            element.style.display = 'none';
        }

        // Show Success
        function showSuccess(element, message) {
            element.textContent = message;
            element.style.display = 'block';
        }
    </script>
</body>

</html>
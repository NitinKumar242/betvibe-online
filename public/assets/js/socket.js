/**
 * BetVibe WebSocket Client
 * Auto-reconnect with exponential backoff
 * Handles: crash_tick, round_start, round_end, win_feed, balance_update, level_up, quest_complete
 */

class GameSocket {
    constructor() {
        this.ws = null;
        this.reconnectDelay = 1000;
        this.maxReconnectDelay = 30000;
        this.handlers = {};
        this.authenticated = false;
        this.connect();
    }

    connect() {
        const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${location.host}/ws`;

        try {
            this.ws = new WebSocket(wsUrl);
        } catch (e) {
            console.warn('[WS] WebSocket not available');
            return;
        }

        this.ws.onopen = () => {
            console.log('[WS] Connected');
            this.reconnectDelay = 1000;
            this.authenticate();
        };

        this.ws.onmessage = (e) => {
            try {
                const data = JSON.parse(e.data);
                this.handleMessage(data);
            } catch (err) {
                console.error('[WS] Parse error:', err);
            }
        };

        this.ws.onclose = (e) => {
            console.log(`[WS] Disconnected (code: ${e.code})`);
            this.authenticated = false;
            this.scheduleReconnect();
        };

        this.ws.onerror = (err) => {
            console.error('[WS] Error:', err);
        };
    }

    authenticate() {
        // Get session token from cookie or stored value
        const token = this.getSessionToken();
        if (token) {
            this.send({ type: 'auth', token: token });
        }
    }

    getSessionToken() {
        // Try to get from cookie
        const cookies = document.cookie.split(';');
        for (let c of cookies) {
            c = c.trim();
            if (c.startsWith('BVSESSID=')) {
                return c.substring(9);
            }
        }
        // Try sessionStorage
        return sessionStorage.getItem('ws_token') || null;
    }

    scheduleReconnect() {
        console.log(`[WS] Reconnecting in ${this.reconnectDelay}ms...`);
        setTimeout(() => this.connect(), this.reconnectDelay);
        this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxReconnectDelay);
    }

    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }

    subscribe(game) {
        this.send({ type: 'subscribe', game: game });
    }

    unsubscribe(game) {
        this.send({ type: 'unsubscribe', game: game });
    }

    on(type, callback) {
        if (!this.handlers[type]) {
            this.handlers[type] = [];
        }
        this.handlers[type].push(callback);
    }

    off(type, callback) {
        if (this.handlers[type]) {
            this.handlers[type] = this.handlers[type].filter(h => h !== callback);
        }
    }

    handleMessage(data) {
        const type = data.type;

        // Auth responses
        if (type === 'auth_success') {
            this.authenticated = true;
            console.log('[WS] Authenticated as', data.username);
        }
        if (type === 'auth_failed') {
            console.warn('[WS] Auth failed:', data.reason);
        }

        // Fire registered handlers
        if (this.handlers[type]) {
            this.handlers[type].forEach(cb => {
                try {
                    cb(data);
                } catch (err) {
                    console.error(`[WS] Handler error for ${type}:`, err);
                }
            });
        }

        // Built-in handlers
        switch (type) {
            case 'crash_tick':
                if (typeof updateCrashMultiplier === 'function') updateCrashMultiplier(data.multiplier);
                break;
            case 'crash_end':
                if (typeof showCrashResult === 'function') showCrashResult(data.crash_point);
                break;
            case 'round_start':
                if (typeof handleRoundStart === 'function') handleRoundStart(data);
                break;
            case 'round_result':
                if (typeof handleRoundResult === 'function') handleRoundResult(data);
                break;
            case 'win_feed':
                this.appendWinFeed(data);
                break;
            case 'balance_update':
                this.updateBalance(data);
                break;
            case 'level_up':
                this.showLevelUp(data);
                break;
            case 'quest_complete':
                this.showQuestComplete(data);
                break;
            case 'referral_converted':
                this.showNotification('💰 ' + data.message);
                break;
        }
    }

    appendWinFeed(data) {
        const feed = document.getElementById('winFeed');
        if (!feed) return;

        const item = document.createElement('div');
        item.className = 'win-feed-item';
        item.innerHTML = `<span class="wf-user">${data.username}</span> won <span class="wf-amount">NPR ${data.payout}</span> on <span class="wf-game">${data.game}</span>`;
        item.style.animation = 'slideIn 0.3s ease';

        feed.insertBefore(item, feed.firstChild);

        // Keep only last 20 items
        while (feed.children.length > 20) {
            feed.removeChild(feed.lastChild);
        }
    }

    updateBalance(data) {
        const realEl = document.getElementById('headerBalance');
        const bonusEl = document.getElementById('headerBonus');
        if (realEl && data.real !== undefined) {
            realEl.textContent = 'NPR ' + parseFloat(data.real).toFixed(2);
        }
        if (bonusEl && data.bonus !== undefined) {
            bonusEl.textContent = parseInt(data.bonus);
        }
    }

    showLevelUp(data) {
        this.showNotification(`🎉 Level Up! You're now Level ${data.new_level}`);
    }

    showQuestComplete(data) {
        this.showNotification(`⚔️ Quest Complete: ${data.quest_title} (+${data.xp_reward} XP)`);
    }

    showNotification(text) {
        // Create toast notification
        let toast = document.getElementById('wsToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'wsToast';
            toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);background:#7F77DD;color:#fff;padding:12px 24px;border-radius:12px;font-weight:600;font-size:0.9rem;opacity:0;transition:all 0.3s;z-index:9999;pointer-events:none;max-width:90%;text-align:center;';
            document.body.appendChild(toast);
        }
        toast.textContent = text;
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
        }, 4000);
    }

    disconnect() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

// Global socket instance
const socket = new GameSocket();

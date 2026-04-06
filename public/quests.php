<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetVibe - Daily Quests</title>
    <meta name="description" content="Complete daily quests to earn XP and bonus coins on BetVibe!">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0D0D0D; color: #fff; min-height: 100vh; }
        .page-container { max-width: 600px; margin: 0 auto; padding: 20px 16px 100px; }
        .page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .back-btn { width: 40px; height: 40px; background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; text-decoration: none; transition: all 0.2s; }
        .back-btn:hover { background: #242424; border-color: #7F77DD; }
        .page-title { font-size: 1.5rem; font-weight: 800; }

        .quest-card {
            background: #1A1A1A; border: 1px solid #2A2A2A; border-radius: 16px;
            padding: 24px; margin-bottom: 16px; position: relative; overflow: hidden;
            transition: all 0.3s;
        }
        .quest-card:hover { border-color: #333; transform: translateY(-2px); }
        .quest-card.completed { border-color: #1D9E75; }
        .quest-card.completed::after {
            content: '✅'; position: absolute; top: 16px; right: 16px; font-size: 1.5rem;
        }
        .quest-difficulty {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; margin-bottom: 12px;
        }
        .quest-difficulty.easy { background: rgba(29,158,117,0.15); color: #1D9E75; }
        .quest-difficulty.medium { background: rgba(239,159,39,0.15); color: #EF9F27; }
        .quest-difficulty.hard { background: rgba(226,75,74,0.15); color: #E24B4A; }
        .quest-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; }
        .quest-desc { font-size: 0.85rem; color: #888; margin-bottom: 16px; }
        .quest-rewards {
            display: flex; gap: 12px; margin-bottom: 14px;
        }
        .reward-badge {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 12px; background: #242424; border-radius: 8px;
            font-size: 0.8rem; font-weight: 700;
        }
        .reward-badge.xp { color: #7F77DD; }
        .reward-badge.coins { color: #EF9F27; }
        .progress-bar {
            width: 100%; height: 8px; background: #242424;
            border-radius: 4px; overflow: hidden; margin-bottom: 8px;
        }
        .progress-fill {
            height: 100%; border-radius: 4px; transition: width 0.5s ease;
            background: linear-gradient(90deg, #7F77DD, #6B63C8);
        }
        .quest-card.completed .progress-fill { background: #1D9E75; }
        .progress-text { font-size: 0.8rem; color: #666; font-weight: 600; }

        .all-done {
            text-align: center; padding: 40px; background: #1A1A1A;
            border: 1px solid #1D9E75; border-radius: 16px; margin-top: 20px;
        }
        .all-done .emoji { font-size: 3rem; margin-bottom: 12px; }
        .all-done .msg { font-size: 1rem; color: #1D9E75; font-weight: 700; margin-bottom: 8px; }
        .all-done .submsg { font-size: 0.85rem; color: #666; }

        .loading { text-align: center; padding: 60px; color: #555; }
        .spinner { width: 32px; height: 32px; border: 3px solid #333; border-top-color: #7F77DD; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <a href="/" class="back-btn">←</a>
            <h1 class="page-title">⚔️ Daily Quests</h1>
        </div>
        <div id="questsContainer">
            <div class="loading"><div class="spinner"></div>Loading quests...</div>
        </div>
    </div>

    <script>
        async function loadQuests() {
            try {
                const res = await fetch('/api/quests/today');
                const data = await res.json();
                if (data.success) {
                    renderQuests(data.data);
                } else {
                    document.getElementById('questsContainer').innerHTML = '<div class="loading">🔒 Login to see quests</div>';
                }
            } catch (err) {
                document.getElementById('questsContainer').innerHTML = '<div class="loading">Failed to load quests</div>';
            }
        }

        function renderQuests(quests) {
            const container = document.getElementById('questsContainer');
            if (!quests || quests.length === 0) {
                container.innerHTML = '<div class="loading">No quests available today. Check back tomorrow!</div>';
                return;
            }

            let html = '';
            let allDone = true;

            quests.forEach(q => {
                const pct = Math.min((q.progress / q.total_needed) * 100, 100);
                const completed = q.is_complete ? 'completed' : '';
                if (!q.is_complete) allDone = false;

                html += `
                    <div class="quest-card ${completed}">
                        <span class="quest-difficulty ${q.difficulty}">${q.difficulty}</span>
                        <div class="quest-title">${q.title}</div>
                        <div class="quest-desc">${q.description}</div>
                        <div class="quest-rewards">
                            <div class="reward-badge xp">⚡ ${q.xp_reward} XP</div>
                            <div class="reward-badge coins">🪙 ${q.coin_reward} coins</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                        <div class="progress-text">${q.progress}/${q.total_needed} ${q.is_complete ? '— Completed!' : ''}</div>
                    </div>`;
            });

            if (allDone) {
                html += `
                    <div class="all-done">
                        <div class="emoji">🎉</div>
                        <div class="msg">Sab quests complete!</div>
                        <div class="submsg">Come back tomorrow for new quests 💪</div>
                    </div>`;
            }

            container.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', loadQuests);
    </script>
</body>
</html>

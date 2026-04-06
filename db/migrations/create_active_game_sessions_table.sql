-- Active Game Sessions Table
-- Stores active multi-step game sessions (Mines, Tower, HiLo, etc.)

CREATE TABLE IF NOT EXISTS active_game_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  game_slug   VARCHAR(30) NOT NULL,
  bet_id      BIGINT UNSIGNED NOT NULL,
  state       JSON NOT NULL,
  expires_at  DATETIME NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (bet_id) REFERENCES bets(id) ON DELETE CASCADE,
  INDEX idx_user_game (user_id, game_slug),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: expires_at should be set to NOW() + 10 minutes when creating a session
-- Auto-cashout on expire will be handled by a cron job or cleanup script

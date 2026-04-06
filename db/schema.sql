-- BetVibe Database Schema
-- All tables created in order to respect foreign key dependencies

-- 1. admins
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','moderator','finance') DEFAULT 'moderator',
  telegram_id   VARCHAR(50),
  last_login    DATETIME,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. users
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(30) UNIQUE NOT NULL,
  email         VARCHAR(100) UNIQUE,
  phone         VARCHAR(20) UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  avatar_id     TINYINT DEFAULT 1,
  xp            INT DEFAULT 0,
  level         TINYINT DEFAULT 1,
  streak_count  TINYINT DEFAULT 0,
  streak_date   DATE,
  login_streak  TINYINT DEFAULT 0,
  last_login    DATETIME,
  last_ip       VARCHAR(45),
  device_fp     VARCHAR(64),
  failed_attempts TINYINT DEFAULT 0,
  last_failed_attempt DATETIME,
  is_banned     TINYINT DEFAULT 0,
  ban_reason    VARCHAR(255),
  fraud_flag    TINYINT DEFAULT 0,
  fraud_reason  VARCHAR(255),
  kyc_status    ENUM('none','pending','verified') DEFAULT 'none',
  referred_by   INT UNSIGNED,
  ref_code      VARCHAR(10) UNIQUE NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referred_by) REFERENCES users(id),
  INDEX idx_fraud (fraud_flag)
);

-- 3. wallets
DROP TABLE IF EXISTS wallets;
CREATE TABLE wallets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED UNIQUE NOT NULL,
  real_balance  DECIMAL(12,2) DEFAULT 0.00,
  bonus_coins   DECIMAL(12,2) DEFAULT 0.00,
  total_wagered DECIMAL(14,2) DEFAULT 0.00,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 4. sessions
DROP TABLE IF EXISTS sessions;
CREATE TABLE sessions (
  token       VARCHAR(64) PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  ip          VARCHAR(45),
  device_fp   VARCHAR(64),
  expires_at  DATETIME NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
);

-- 5. transactions
DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  type            ENUM('deposit','withdraw','win','loss','bonus','referral_bonus') NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  balance_type    ENUM('real','bonus') DEFAULT 'real',
  status          ENUM('pending','completed','failed','rejected') DEFAULT 'completed',
  reference_id    VARCHAR(100),
  gateway         VARCHAR(50),
  note            VARCHAR(255),
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_type (user_id, type),
  INDEX idx_status (status)
);

-- 6. withdrawal_requests
DROP TABLE IF EXISTS withdrawal_requests;
CREATE TABLE withdrawal_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  watchpay_account VARCHAR(100) NOT NULL,
  status          ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_note      VARCHAR(255),
  reviewed_by     INT UNSIGNED,
  requested_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  reviewed_at     DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status (status)
);

-- 7. game_config
DROP TABLE IF EXISTS game_config;
CREATE TABLE game_config (
  game_slug       VARCHAR(30) PRIMARY KEY,
  display_name    VARCHAR(50) NOT NULL,
  is_enabled      TINYINT DEFAULT 1,
  win_ratio       DECIMAL(5,2) DEFAULT 20.00,
  min_bet         DECIMAL(8,2) DEFAULT 10.00,
  max_bet         DECIMAL(8,2) DEFAULT 10000.00,
  round_duration  INT DEFAULT 0,
  extra_config    JSON,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 8. game_rounds
DROP TABLE IF EXISTS game_rounds;
CREATE TABLE game_rounds (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_slug   VARCHAR(30) NOT NULL,
  result      VARCHAR(50) NOT NULL,
  result_meta JSON,
  started_at  DATETIME NOT NULL,
  ended_at    DATETIME NOT NULL,
  INDEX idx_game_time (game_slug, started_at)
);

-- 9. bets
DROP TABLE IF EXISTS bets;
CREATE TABLE bets (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  game_slug   VARCHAR(30) NOT NULL,
  round_id    INT UNSIGNED,
  bet_amount  DECIMAL(12,2) NOT NULL,
  balance_type ENUM('real','bonus') DEFAULT 'real',
  bet_data    JSON NOT NULL,
  result      ENUM('win','loss','pending') DEFAULT 'pending',
  payout      DECIMAL(12,2) DEFAULT 0.00,
  multiplier  DECIMAL(8,2) DEFAULT 0.00,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_game (user_id, game_slug),
  INDEX idx_created (created_at)
);

-- 10. referrals
DROP TABLE IF EXISTS referrals;
CREATE TABLE referrals (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_id     INT UNSIGNED NOT NULL,
  referred_id     INT UNSIGNED NOT NULL UNIQUE,
  status          ENUM('pending','converted') DEFAULT 'pending',
  bonus_paid      DECIMAL(10,2) DEFAULT 0.00,
  converted_at    DATETIME,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES users(id),
  FOREIGN KEY (referred_id) REFERENCES users(id),
  INDEX idx_referrer (referrer_id)
);

-- 11. daily_quests
DROP TABLE IF EXISTS daily_quests;
CREATE TABLE daily_quests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quest_key   VARCHAR(50) NOT NULL,
  title       VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  difficulty  ENUM('easy','medium','hard') NOT NULL,
  xp_reward   INT NOT NULL,
  coin_reward INT DEFAULT 0,
  condition   JSON NOT NULL,
  active_date DATE NOT NULL,
  INDEX idx_date (active_date)
);

-- 12. user_quest_progress
DROP TABLE IF EXISTS user_quest_progress;
CREATE TABLE user_quest_progress (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  quest_id    INT UNSIGNED NOT NULL,
  progress    INT DEFAULT 0,
  is_complete TINYINT DEFAULT 0,
  completed_at DATETIME,
  date        DATE NOT NULL,
  UNIQUE KEY unique_user_quest_date (user_id, quest_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 13. login_rewards
DROP TABLE IF EXISTS login_rewards;
CREATE TABLE login_rewards (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  day_number  TINYINT NOT NULL,
  coins_given INT NOT NULL,
  given_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 14. win_streaks
DROP TABLE IF EXISTS win_streaks;
CREATE TABLE win_streaks (
  user_id         INT UNSIGNED PRIMARY KEY,
  current_streak  INT DEFAULT 0,
  best_streak     INT DEFAULT 0,
  last_result     ENUM('win','loss'),
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 15. admin_audit_log
DROP TABLE IF EXISTS admin_audit_log;
CREATE TABLE admin_audit_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED NOT NULL,
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50),
  target_id   INT UNSIGNED,
  old_value   TEXT,
  new_value   TEXT,
  ip          VARCHAR(45),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin (admin_id),
  INDEX idx_target (target_type, target_id)
);

-- 16. ip_blacklist
DROP TABLE IF EXISTS ip_blacklist;
CREATE TABLE ip_blacklist (
  ip          VARCHAR(45) PRIMARY KEY,
  reason      VARCHAR(255),
  blocked_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 17. telegram_support_tickets
DROP TABLE IF EXISTS telegram_support_tickets;
CREATE TABLE telegram_support_tickets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  telegram_id   VARCHAR(50) NOT NULL,
  user_id       INT UNSIGNED,
  type          ENUM('password_recovery','support','other') NOT NULL,
  message       TEXT,
  status        ENUM('open','resolved') DEFAULT 'open',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 18. push_subscriptions
DROP TABLE IF EXISTS push_subscriptions;
CREATE TABLE push_subscriptions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  endpoint    TEXT NOT NULL,
  p256dh      TEXT NOT NULL,
  auth        TEXT NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seed Data (game_config inserts)
INSERT INTO game_config (game_slug, display_name, win_ratio, min_bet, max_bet, round_duration) VALUES
('color_predict','Color Predict',18.00,10,5000,180),
('fast_parity','Fast Parity',19.00,10,5000,60),
('crash','Crash',20.00,10,10000,0),
('limbo','Limbo',20.00,10,10000,0),
('mines','Mines',22.00,10,10000,0),
('plinko','Plinko',21.00,10,10000,0),
('dice_duel','Dice Duel',19.00,10,10000,0),
('keno','Keno',20.00,10,5000,0),
('hilo','HiLo Cards',20.00,10,10000,0),
('tower_climb','Tower Climb',19.00,10,10000,0),
('dragon_tiger','Dragon Tiger',18.50,10,10000,0),
('spin_wheel','Spin Wheel',20.00,10,10000,0),
('coin_flip','Coin Flip',19.00,10,10000,0),
('roulette_lite','Roulette Lite',18.90,10,10000,0),
('lucky_slots','Lucky Slots',19.00,10,5000,0),
('number_guess','Number Guess',20.00,10,10000,0);

-- 19. rate_limits (for PHP-level rate limiting)
DROP TABLE IF EXISTS rate_limits;
CREATE TABLE rate_limits (
  key_hash      VARCHAR(64) PRIMARY KEY,
  attempts      INT DEFAULT 0,
  window_start  DATETIME,
  INDEX idx_window (window_start)
);

-- 20. active_game_sessions (multi-step games: mines, tower, hilo)
DROP TABLE IF EXISTS active_game_sessions;
CREATE TABLE active_game_sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED UNIQUE,
  game_slug   VARCHAR(30),
  bet_id      BIGINT UNSIGNED,
  state       JSON,
  expires_at  DATETIME,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_expires (expires_at)
);

-- 21. telegram_states (bot conversation state machine)
DROP TABLE IF EXISTS telegram_states;
CREATE TABLE telegram_states (
  chat_id     VARCHAR(50) PRIMARY KEY,
  state       VARCHAR(50),
  data        JSON,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

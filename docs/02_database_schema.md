# Database Schema — Complete

## All Tables

### users
```sql
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
  is_banned     TINYINT DEFAULT 0,
  ban_reason    VARCHAR(255),
  kyc_status    ENUM('none','pending','verified') DEFAULT 'none',
  referred_by   INT UNSIGNED,
  ref_code      VARCHAR(10) UNIQUE NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referred_by) REFERENCES users(id)
);
```

### wallets
```sql
CREATE TABLE wallets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED UNIQUE NOT NULL,
  real_balance  DECIMAL(12,2) DEFAULT 0.00,
  bonus_coins   DECIMAL(12,2) DEFAULT 0.00,
  total_wagered DECIMAL(14,2) DEFAULT 0.00,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### transactions
```sql
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
```

### withdrawal_requests
```sql
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
```

### game_rounds (for timer-based games: Color Predict, Fast Parity)
```sql
CREATE TABLE game_rounds (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_slug   VARCHAR(30) NOT NULL,
  result      VARCHAR(50) NOT NULL,
  result_meta JSON,
  started_at  DATETIME NOT NULL,
  ended_at    DATETIME NOT NULL,
  INDEX idx_game_time (game_slug, started_at)
);
```

### bets
```sql
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
```

### game_config (admin-controlled per-game settings)
```sql
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
```

### referrals
```sql
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
```

### daily_quests
```sql
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
```

### user_quest_progress
```sql
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
```

### login_rewards
```sql
CREATE TABLE login_rewards (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  day_number  TINYINT NOT NULL,
  coins_given INT NOT NULL,
  given_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### win_streaks
```sql
CREATE TABLE win_streaks (
  user_id         INT UNSIGNED PRIMARY KEY,
  current_streak  INT DEFAULT 0,
  best_streak     INT DEFAULT 0,
  last_result     ENUM('win','loss'),
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### sessions
```sql
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
```

### admin_audit_log
```sql
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
```

### admins
```sql
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','moderator','finance') DEFAULT 'moderator',
  telegram_id   VARCHAR(50),
  last_login    DATETIME,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### ip_blacklist
```sql
CREATE TABLE ip_blacklist (
  ip          VARCHAR(45) PRIMARY KEY,
  reason      VARCHAR(255),
  blocked_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### telegram_support_tickets
```sql
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
```

### push_subscriptions
```sql
CREATE TABLE push_subscriptions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  endpoint    TEXT NOT NULL,
  p256dh      TEXT NOT NULL,
  auth        TEXT NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Indexes Summary
All foreign keys indexed. Additional composite indexes:
- `bets (user_id, game_slug)` — user bet history per game
- `bets (created_at)` — leaderboard time queries
- `transactions (user_id, type)` — wallet history filters
- `game_rounds (game_slug, started_at)` — active round lookup

## Seed Data (game_config inserts)
```sql
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
```

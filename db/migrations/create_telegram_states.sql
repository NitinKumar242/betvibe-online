-- Telegram conversation states
CREATE TABLE IF NOT EXISTS telegram_states (
    chat_id VARCHAR(64) PRIMARY KEY,
    state VARCHAR(32) DEFAULT 'idle',
    data JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin sessions table
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    ip VARCHAR(45),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admins table (if not exists)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add fraud_flag and fraud_reason to users if missing
-- ALTER TABLE users ADD COLUMN fraud_flag TINYINT(1) DEFAULT 0 AFTER is_banned;
-- ALTER TABLE users ADD COLUMN fraud_reason VARCHAR(255) NULL AFTER fraud_flag;
-- ALTER TABLE users ADD COLUMN telegram_chat_id VARCHAR(64) NULL;
-- ALTER TABLE users ADD COLUMN login_streak INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN xp INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN level INT DEFAULT 1;
-- ALTER TABLE users ADD COLUMN avatar_id INT DEFAULT 1;
-- ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) NULL;
-- ALTER TABLE users ADD COLUMN device_fp VARCHAR(255) NULL;

-- Note: Uncomment the ALTER TABLE statements above and run manually
-- if these columns don't already exist in your users table.

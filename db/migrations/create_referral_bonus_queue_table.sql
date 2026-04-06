-- Create referral_bonus_queue table for processing referral bonuses
DROP TABLE IF EXISTS referral_bonus_queue;
CREATE TABLE referral_bonus_queue (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  status      ENUM('pending','processed','failed') DEFAULT 'pending',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
);

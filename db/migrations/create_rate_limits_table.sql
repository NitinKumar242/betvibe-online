-- Create rate_limits table for PHP-level rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
  key_hash VARCHAR(64) PRIMARY KEY,
  attempts INT DEFAULT 0,
  window_start DATETIME,
  INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

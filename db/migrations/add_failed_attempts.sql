-- Add failed_attempts and last_failed_attempt columns to users table
ALTER TABLE users
ADD COLUMN failed_attempts TINYINT DEFAULT 0 AFTER device_fp,
ADD COLUMN last_failed_attempt DATETIME AFTER failed_attempts;

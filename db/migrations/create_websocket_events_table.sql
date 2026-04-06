-- Create websocket_events table for storing WebSocket events to be sent to users
DROP TABLE IF EXISTS websocket_events;
CREATE TABLE websocket_events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  event_data  JSON NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  processed   TINYINT DEFAULT 0,
  processed_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_processed (user_id, processed),
  INDEX idx_created (created_at)
);

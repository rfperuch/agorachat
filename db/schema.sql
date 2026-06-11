CREATE TABLE IF NOT EXISTS chat_users (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  site_id          VARCHAR(64)  NOT NULL,
  external_id      VARCHAR(255) NOT NULL,
  display_name     VARCHAR(255),
  avatar_url       VARCHAR(512),
  is_super         TINYINT(1)   NOT NULL DEFAULT 0,
  last_seen        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_message_at  TIMESTAMP    NULL,
  UNIQUE KEY uq_site_user (site_id, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  site_id     VARCHAR(64) NOT NULL,
  sender_id   INT         NOT NULL,
  content     TEXT        NOT NULL,
  created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_id   (site_id, id),
  INDEX idx_site_time (site_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tombstones for message deletions (for propagating to poll clients)
CREATE TABLE IF NOT EXISTS message_deletions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  site_id    VARCHAR(64) NOT NULL,
  message_id INT         NOT NULL,
  deleted_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_del (site_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PHP session storage (replaces file-based sessions — locks stay in MySQL)
CREATE TABLE IF NOT EXISTS php_sessions (
  session_id   VARCHAR(128)  NOT NULL PRIMARY KEY,
  data         MEDIUMBLOB    NOT NULL,
  last_active  INT UNSIGNED  NOT NULL,
  INDEX idx_last_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fixed-window rate limiting (atomic via ON DUPLICATE KEY UPDATE)
CREATE TABLE IF NOT EXISTS rate_limits (
  key_hash     CHAR(64)         NOT NULL,
  window_start INT UNSIGNED     NOT NULL,
  hit_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (key_hash, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS used_tokens (
  jti         VARCHAR(128) PRIMARY KEY,
  expires_at  TIMESTAMP    NOT NULL,
  INDEX idx_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

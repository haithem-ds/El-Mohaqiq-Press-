-- Homepage advertising carousel (images, GIFs, videos). Safe additive migration.
-- Run once on your MySQL database (phpMyAdmin, mysql CLI, or your deploy process).

CREATE TABLE IF NOT EXISTS homepage_ads (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  media_url TEXT NOT NULL,
  media_type VARCHAR(20) NOT NULL DEFAULT 'image',
  link_url TEXT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_homepage_ads_active ON homepage_ads(is_active, display_order);

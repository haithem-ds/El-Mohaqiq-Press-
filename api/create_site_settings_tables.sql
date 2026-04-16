-- ============================================
-- MySQL DATABASE TABLES FOR SITE SETTINGS
-- Breaking News, Footer Content, Social Media, PDF News
-- ============================================

-- Create breaking_news table
CREATE TABLE IF NOT EXISTS breaking_news (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  text_ar TEXT NOT NULL,
  text_fr TEXT,
  text_en TEXT,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create footer_settings table
CREATE TABLE IF NOT EXISTS footer_settings (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  value_ar TEXT,
  value_fr TEXT,
  value_en TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create social_media_links table
CREATE TABLE IF NOT EXISTS social_media_links (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  platform VARCHAR(50) NOT NULL,
  url TEXT NOT NULL,
  icon VARCHAR(50),
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pdf_news table (electronic news/PDFs)
CREATE TABLE IF NOT EXISTS pdf_news (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  title_ar VARCHAR(500) NOT NULL,
  title_fr VARCHAR(500),
  title_en VARCHAR(500),
  description_ar TEXT,
  description_fr TEXT,
  description_en TEXT,
  pdf_url TEXT NOT NULL,
  cover_image_url TEXT,
  file_size INT,
  page_count INT,
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create footer_sections table (for dynamic footer sections)
CREATE TABLE IF NOT EXISTS footer_sections (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  title_ar VARCHAR(255) NOT NULL,
  title_fr VARCHAR(255),
  title_en VARCHAR(255),
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create footer_section_links table (links within footer sections)
CREATE TABLE IF NOT EXISTS footer_section_links (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  section_id VARCHAR(36) NOT NULL,
  label_ar VARCHAR(255) NOT NULL,
  label_fr VARCHAR(255),
  label_en VARCHAR(255),
  url TEXT NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES footer_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default footer settings
INSERT INTO footer_settings (setting_key, value_ar, value_fr, value_en) VALUES
('email', 'contact@elmohaqiq.press', 'contact@elmohaqiq.press', 'contact@elmohaqiq.press'),
('phone', '+213 XXX XXX XXX', '+213 XXX XXX XXX', '+213 XXX XXX XXX'),
('address_ar', 'الجزائر العاصمة', NULL, NULL),
('address_fr', NULL, 'Alger, Algérie', NULL),
('address_en', NULL, NULL, 'Algiers, Algeria'),
('about_text_ar', 'منصة إخبارية شاملة تقدم آخر الأخبار من الجزائر والعالم', NULL, NULL),
('about_text_fr', NULL, 'Plateforme d''information complète offrant les dernières nouvelles d''Algérie et du monde', NULL),
('about_text_en', NULL, NULL, 'Comprehensive news platform offering the latest news from Algeria and the world'),
('site_manager_ar', 'سيتم إضافة الاسم', NULL, NULL),
('site_manager_fr', NULL, 'Nom à ajouter', NULL),
('site_manager_en', NULL, NULL, 'Name to be added'),
('editor_in_chief_ar', 'سيتم إضافة الاسم', NULL, NULL),
('editor_in_chief_fr', NULL, 'Nom à ajouter', NULL),
('editor_in_chief_en', NULL, NULL, 'Name to be added'),
('journalists_ar', 'سيتم إضافة الأسماء', NULL, NULL),
('journalists_fr', NULL, 'Noms à ajouter', NULL),
('journalists_en', NULL, NULL, 'Names to be added')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Insert default social media links
INSERT INTO social_media_links (platform, url, icon, display_order) VALUES
('facebook', '#', 'Facebook', 1),
('twitter', '#', 'Twitter', 2),
('instagram', '#', 'Instagram', 3),
('youtube', '#', 'Youtube', 4)
ON DUPLICATE KEY UPDATE platform = platform;

-- Homepage advertising (images / GIFs / videos; carousel on site when multiple active rows)
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

-- Create indexes
CREATE INDEX idx_homepage_ads_active ON homepage_ads(is_active, display_order);
CREATE INDEX idx_breaking_news_active ON breaking_news(is_active, display_order);
CREATE INDEX idx_social_media_active ON social_media_links(is_active, display_order);
CREATE INDEX idx_pdf_news_active ON pdf_news(is_active, display_order);
CREATE INDEX idx_footer_sections_active ON footer_sections(is_active, display_order);
CREATE INDEX idx_footer_section_links_section ON footer_section_links(section_id, display_order);


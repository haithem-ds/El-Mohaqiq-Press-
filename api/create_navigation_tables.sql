-- ============================================
-- MySQL DATABASE TABLE FOR NAVIGATION MENU
-- ============================================

-- Create navigation_menu_items table
CREATE TABLE IF NOT EXISTS navigation_menu_items (
  id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
  label_ar VARCHAR(255) NOT NULL,
  label_fr VARCHAR(255),
  label_en VARCHAR(255),
  href VARCHAR(500) NOT NULL,
  icon VARCHAR(50),
  display_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  is_external BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default navigation items
INSERT INTO navigation_menu_items (label_ar, label_fr, label_en, href, display_order) VALUES
('الرئيسية', 'Accueil', 'Home', '/', 1),
('محلي', 'Local', 'Local', '/category/local', 2),
('الجزائر', 'Algérie', 'Algeria', '/category/algeria', 3),
('العالم', 'Monde', 'World', '/category/world', 4),
('الرياضة', 'Sports', 'Sports', '/category/sports', 5),
('الاقتصاد', 'Économie', 'Economy', '/category/economy', 6),
('المنبر الحر', 'Tribune Libre', 'Free Platform', '/category/forum', 7)
ON DUPLICATE KEY UPDATE label_ar = label_ar;

-- Create index
CREATE INDEX idx_nav_menu_order ON navigation_menu_items(display_order, is_active);


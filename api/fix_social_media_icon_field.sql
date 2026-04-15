-- Fix social_media_links icon field to support full URLs
-- Change from VARCHAR(50) to TEXT to allow full icon URLs
-- This is for MySQL/MariaDB (cPanel)

-- Check if column exists and modify it
ALTER TABLE social_media_links 
MODIFY COLUMN icon TEXT;

-- This allows storing full URLs for custom icons (SVG, PNG, etc.)
-- Full URLs can be like: https://elmohaqiqpress.com/api/uploads/user_id/filename.svg


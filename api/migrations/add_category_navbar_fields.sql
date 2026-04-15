-- Add navbar visibility and ordering fields to categories table
-- This migration adds support for controlling which categories appear in the navbar

-- Add show_in_navbar column (default false - categories are hidden by default)
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS show_in_navbar BOOLEAN DEFAULT 0;

-- Add navbar_order column (for ordering categories in navbar)
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS navbar_order INT DEFAULT 999;

-- Add is_active column (for enabling/disabling categories)
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT 1;

-- Create index for better performance when querying navbar categories
CREATE INDEX IF NOT EXISTS idx_categories_navbar ON categories(show_in_navbar, navbar_order, is_active);

-- Update existing categories to be visible in navbar if they match common category names
-- This is optional - you can remove this if you want all existing categories hidden
UPDATE categories 
SET show_in_navbar = 1, navbar_order = 
  CASE 
    WHEN LOWER(name) LIKE '%رئيس%' OR LOWER(name) LIKE '%home%' OR LOWER(slug) = 'home' THEN 1
    WHEN LOWER(name) LIKE '%محلي%' OR LOWER(slug) = 'local' THEN 2
    WHEN LOWER(name) LIKE '%جزائر%' OR LOWER(slug) = 'algeria' THEN 3
    WHEN LOWER(name) LIKE '%عالم%' OR LOWER(slug) = 'world' THEN 4
    WHEN LOWER(name) LIKE '%رياض%' OR LOWER(slug) = 'sports' THEN 5
    WHEN LOWER(name) LIKE '%اقتصاد%' OR LOWER(slug) = 'economy' THEN 6
    WHEN LOWER(name) LIKE '%منبر%' OR LOWER(slug) = 'forum' THEN 7
    ELSE 999
  END
WHERE show_in_navbar IS NULL OR show_in_navbar = 0;


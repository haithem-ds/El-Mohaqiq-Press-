-- Add icon_scale column to social_media_links table
-- This allows custom scaling of social media icons (e.g., 1.5x, 2x, 3x)

ALTER TABLE social_media_links 
ADD COLUMN icon_scale DECIMAL(3,1) DEFAULT 3.0;

-- Default scale is 3.0 to match current behavior


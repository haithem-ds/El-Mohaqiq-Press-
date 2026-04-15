-- Add featured column to articles table
ALTER TABLE articles 
ADD COLUMN featured BOOLEAN NOT NULL DEFAULT FALSE;

-- Add index for better performance when querying featured articles
CREATE INDEX idx_articles_featured ON articles(featured, published_at DESC);


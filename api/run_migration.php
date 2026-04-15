<?php
/**
 * Migration script to add navbar fields to categories table
 * Run this once to add the new columns to your database
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting migration: Adding navbar fields to categories table...\n";
    
    // Check if columns already exist
    $checkStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'show_in_navbar'");
    if ($checkStmt->rowCount() > 0) {
        echo "Columns already exist. Migration not needed.\n";
        exit(0);
    }
    
    // Add show_in_navbar column
    echo "Adding show_in_navbar column...\n";
    $db->exec("ALTER TABLE categories ADD COLUMN show_in_navbar BOOLEAN DEFAULT 0");
    
    // Add navbar_order column
    echo "Adding navbar_order column...\n";
    $db->exec("ALTER TABLE categories ADD COLUMN navbar_order INT DEFAULT 999");
    
    // Add is_active column
    echo "Adding is_active column...\n";
    $db->exec("ALTER TABLE categories ADD COLUMN is_active BOOLEAN DEFAULT 1");
    
    // Create index
    echo "Creating index...\n";
    try {
        $db->exec("CREATE INDEX idx_categories_navbar ON categories(show_in_navbar, navbar_order, is_active)");
    } catch (Exception $e) {
        echo "Index might already exist: " . $e->getMessage() . "\n";
    }
    
    // Update existing categories (optional - sets common categories to be visible)
    echo "Updating existing categories...\n";
    $updateStmt = $db->prepare("
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
        WHERE show_in_navbar = 0
    ");
    $updateStmt->execute();
    $affected = $updateStmt->rowCount();
    echo "Updated $affected categories.\n";
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}


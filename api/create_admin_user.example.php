<?php
/**
 * Create Admin User Script
 *
 * Copy this file to create_admin_user.php, set the credentials below, run once
 * in the browser, then delete create_admin_user.php from the server.
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$adminEmail = 'admin@example.com';
$adminPassword = 'CHANGE_THIS_STRONG_PASSWORD';
$adminUsername = 'admin';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$adminEmail, $adminUsername]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $userId = $existingUser['id'];

        $stmt = $db->prepare("SELECT role FROM user_roles WHERE user_id = ? AND role = 'admin'");
        $stmt->execute([$userId]);
        $hasAdminRole = $stmt->fetch();

        if ($hasAdminRole) {
            echo json_encode([
                'status' => 'info',
                'message' => 'Admin user already exists with admin role. No changes made.',
                'user_id' => $userId
            ], JSON_PRETTY_PRINT);
        } else {
            $stmt = $db->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'admin')");
            $stmt->execute([$userId]);

            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Existing user updated: Admin role added and password updated.',
                'user_id' => $userId
            ], JSON_PRETTY_PRINT);
        }
    } else {
        $userId = bin2hex(random_bytes(16));

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO profiles (id, username, full_name, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $adminUsername, 'Admin']);

            $stmt = $db->prepare("
                INSERT INTO users (id, email, password_hash, username, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $adminEmail, $passwordHash, $adminUsername]);

            $stmt = $db->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'admin')");
            $stmt->execute([$userId]);

            $db->commit();

            echo json_encode([
                'status' => 'success',
                'message' => 'Admin user created successfully!',
                'user_id' => $userId,
                'email' => $adminEmail,
                'username' => $adminUsername,
                'note' => 'Delete create_admin_user.php after confirming the user was created.'
            ], JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating admin user: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

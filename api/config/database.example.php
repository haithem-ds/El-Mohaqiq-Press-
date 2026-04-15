<?php
// Copy this file to database.php and fill in real values. Do not commit database.php.

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

define('JWT_SECRET', 'generate_a_long_random_string_here');
define('JWT_EXPIRY', 86400);

define('API_BASE_URL', 'https://your-domain.com/api');

$apiDir = dirname(__DIR__);
define('UPLOAD_DIR', $apiDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

function has_role($db, $user_id, $role) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_roles WHERE user_id = ? AND role = ?");
    $stmt->execute([$user_id, $role]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

function get_user_roles($db, $user_id) {
    $stmt = $db->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

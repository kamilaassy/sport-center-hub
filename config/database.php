<?php
// =============================================
// config/database.php
// Database Connection Configuration
// =============================================

define('DB_HOST', 'localhost');      
define('DB_NAME', 'sport_center_hub');
define('DB_USER', 'root');
define('DB_PASS', '');                       
define('DB_CHARSET', 'utf8mb4');

/**
 * Membuat koneksi PDO ke database.
 * Melempar exception jika koneksi gagal.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            die(json_encode([
                'error' => true,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
    }
    return $pdo;
}
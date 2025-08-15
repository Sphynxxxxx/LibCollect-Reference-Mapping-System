<?php
// logout.php
session_start();



if (!isset($pdo) || $pdo === null) {
    try {
        $host = 'localhost';
        $dbname = 'library_system';
        $username = 'root';
        $password = '';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        error_log("Database connection failed in logout: " . $e->getMessage());
        $pdo = null;
    }
}

$activity_logger_paths = [
    '../classes/ActivityLogger.php',
    'classes/ActivityLogger.php',
];

$logger = null;
foreach ($activity_logger_paths as $path) {
    if (file_exists($path) && $pdo !== null) {
        require_once $path;
        try {
            $logger = new ActivityLogger($pdo);
        } catch (Exception $e) {
            error_log("ActivityLogger creation failed: " . $e->getMessage());
            $logger = null;
        }
        break;
    }
}

if (isset($_SESSION['user_id']) && $logger !== null) {
    try {
        $logger->logUserActivity(
            'logout',
            "User {$_SESSION['username']} logged out",
            $_SESSION['user_id'],
            $_SESSION['username']
        );
    } catch (Exception $e) {
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Store username for potential display message
$username = $_SESSION['username'] ?? 'User';

session_destroy();

// Redirect to login with logout message
header('Location: login.php?message=logged_out&user=' . urlencode($username));
exit;
?>
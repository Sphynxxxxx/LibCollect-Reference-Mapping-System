<?php
// login.php - Example implementation
session_start();
require_once 'config/database.php';
require_once 'classes/ActivityLogger.php';

$logger = new ActivityLogger($pdo);

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Log successful login
                $logger->logUserActivity(
                    'login',
                    "User {$user['username']} logged in successfully",
                    $user['id'],
                    $user['username']
                );
                
                header('Location: dashboard.php');
                exit;
            } else {
                // Failed login attempt
                $logger->logUserActivity(
                    'failed_login',
                    "Failed login attempt for username: {$username}",
                    null,
                    $username
                );
                
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "System error. Please try again.";
        }
    } else {
        $error = "Please fill in all fields";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - ISAT U Library</title>
    <!-- Add your CSS here -->
</head>
<body>
    <div class="login-container">
        <h2>Library System Login</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
</html>
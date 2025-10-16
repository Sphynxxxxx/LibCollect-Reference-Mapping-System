<?php
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
        die("Database connection failed: " . $e->getMessage());
    }
}

// Try to include ActivityLogger
$activity_logger_paths = [
    '../classes/ActivityLogger.php',
    'classes/ActivityLogger.php',
    '../includes/ActivityLogger.php',
    'includes/ActivityLogger.php'
];

$logger = null;
foreach ($activity_logger_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $logger = new ActivityLogger($pdo);
        break;
    }
}

// If ActivityLogger is not found, create a simple fallback
if (!$logger) {
    class SimpleLogger {
        private $pdo;
        public function __construct($pdo) { $this->pdo = $pdo; }
        public function logUserActivity($action, $description, $userId = null, $username = null) {
            error_log("Activity: $action - $description");
            return true;
        }
    }
    $logger = new SimpleLogger($pdo);
}

$error = '';
$success = '';
$debug_info = '';

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $user = $_GET['user'] ?? 'User';
    $success = "You have been successfully logged out. See you soon, " . htmlspecialchars($user) . "!";
}

// Check for session expiration message
if (isset($_GET['message']) && $_GET['message'] === 'session_expired') {
    $user = $_GET['user'] ?? 'User';
    $error = "Your session has expired for security reasons. Please login again, " . htmlspecialchars($user) . ".";
}

// Debug mode - add ?debug=1 to URL
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($debug_mode) {
        $debug_info .= "Username entered: '" . htmlspecialchars($username) . "'<br>";
        $debug_info .= "Password length: " . strlen($password) . "<br>";
    }
    
    if (!empty($username) && !empty($password)) {
        try {
            // Check if the user exists by username
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($debug_mode) {
                $debug_info .= "User found in database: " . ($user ? 'Yes' : 'No') . "<br>";
                if ($user) {
                    $debug_info .= "User ID: " . $user['id'] . "<br>";
                    $debug_info .= "Username: " . $user['username'] . "<br>";
                    $debug_info .= "User role: " . $user['role'] . "<br>";
                    $debug_info .= "Stored password hash: " . substr($user['password'], 0, 20) . "...<br>";
                    $debug_info .= "Password verification: " . (password_verify($password, $user['password']) ? 'Success' : 'Failed') . "<br>";
                }
            }
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login - set all required session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['last_activity'] = time(); // Set initial activity time
                $_SESSION['logged_in'] = true; // Additional security flag
                
                if ($debug_mode) {
                    $debug_info .= "Session variables set successfully<br>";
                    $debug_info .= "Redirecting to: ../index.php<br>";
                }
                
                // Log successful login
                try {
                    $logger->logUserActivity(
                        'login',
                        "User {$user['username']} logged in successfully",
                        $user['id'],
                        $user['username']
                    );
                } catch (Exception $e) {
                    if ($debug_mode) {
                        $debug_info .= "Logging failed: " . $e->getMessage() . "<br>";
                    }
                }
                
                if (!$debug_mode) {
                    header('Location: ../index.php');
                    exit;
                } else {
                    $success = "Login successful! (Debug mode - not redirecting)";
                }
            } else {
                // Failed login attempt
                try {
                    $logger->logUserActivity(
                        'failed_login',
                        "Failed login attempt for username: {$username}",
                        null,
                        $username
                    );
                } catch (Exception $e) {
                    if ($debug_mode) {
                        $debug_info .= "Failed login logging error: " . $e->getMessage() . "<br>";
                    }
                }
                
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "System error. Please try again.";
            if ($debug_mode) {
                $debug_info .= "Database error: " . $e->getMessage() . "<br>";
            }
        }
    } else {
        $error = "Please fill in all fields";
    }
}

// Additional debug info
if ($debug_mode) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        $debug_info .= "Total users in database: $count<br>";
        
        $stmt = $pdo->query("SELECT username, role FROM users LIMIT 5");
        $users = $stmt->fetchAll();
        $debug_info .= "Sample users: ";
        foreach ($users as $u) {
            $debug_info .= $u['username'] . " (" . $u['role'] . "), ";
        }
        $debug_info = rtrim($debug_info, ', ') . "<br>";
        
    } catch (Exception $e) {
        $debug_info .= "Debug query error: " . $e->getMessage() . "<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ISAT U LibCollect: Reference Mapping System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(180deg, #001f75, 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-section i {
            font-size: 3rem;
            color: #001f75;
            margin-bottom: 10px;
        }
        
        .logo-section h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .logo-section p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-control {
            border: 2px solid #e1e5fe;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .btn-primary {
            background: #ffb347;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(45deg, #51cf66, #40c057);
            color: white;
        }
        
        .alert-info {
            background: linear-gradient(45deg, #339af0, #228be6);
            color: white;
        }
        
        .alert-warning {
            background: linear-gradient(45deg, #ffd43b, #fab005);
            color: #333;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5fe;
        }
        
        .signup-link a {
            color: #001f75;
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            color: #1e40af;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.8rem;
            color: #495057;
        }
        
        .username-hint {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="logo-section">
                        <i class="fas fa-book-reader"></i>
                        <h2>ISAT U LibCollect</h2>
                        <p>Reference Mapping System</p>
                    </div>
                    
                    <?php if ($debug_mode && $debug_info): ?>
                        <div class="alert alert-info">
                            <strong>Debug Information:</strong><br>
                            <?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-<?php echo (strpos($error, 'session') !== false) ? 'warning' : 'danger'; ?>">
                            <i class="fas fa-<?php echo (strpos($error, 'session') !== false) ? 'clock' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your password"
                                   required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    
                    <?php if ($debug_mode): ?>
                        <div class="debug-info mt-3">
                            <strong>Test Credentials:</strong><br>
                            Username: (use the username you set during signup)<br>
                            Password: (use the password you set during signup)<br>
                            <small>Use the credentials you created during signup</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="signup-link">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="signup.php">
                                <i class="fas fa-user-plus me-1"></i>Sign up here
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide success message after 5 seconds
        <?php if ($success && !$debug_mode): ?>
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s ease-out';
                successAlert.style.opacity = '0';
                setTimeout(function() {
                    successAlert.remove();
                }, 500);
            }
        }, 5000);
        <?php endif; ?>
        
        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
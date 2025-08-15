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

$activity_logger_paths = [
    '../classes/ActivityLogger.php',
    'classes/ActivityLogger.php',
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
            // Simple logging fallback
            error_log("Activity: $action - $description");
            return true;
        }
    }
    $logger = new SimpleLogger($pdo);
}

$errors = [];
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (!str_ends_with($email, '@staff.isatu.edu.ph')) {
        $errors[] = "Email must be a valid ISAT U staff email (@staff.isatu.edu.ph)";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = "Username or email already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again.";
            error_log("Signup check error: " . $e->getMessage());
        }
    }
    
    // Create account if no errors
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("DESCRIBE users");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('full_name', $columns)) {
                // full_name column exists
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$username, $email, $hashedPassword, $fullName, $role]);
            } else {
                // full_name column doesn't exist, insert without it
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$username, $email, $hashedPassword, $role]);
            }
            
            if ($result) {
                $userId = $pdo->lastInsertId();
                
                // Log the signup activity
                $logger->logUserActivity(
                    'signup',
                    "New user registered: {$username} ({$email}) as {$role}",
                    $userId,
                    $username
                );
                
                $success = "Account created successfully! You can now log in.";
                
                // Auto-login the user
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $fullName;
                header('Location: login.php');
                exit;
            } else {
                $errors[] = "Failed to create account. Please try again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Signup error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Signup - ISAT U LibCollect: Reference Mapping System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .signup-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-section i {
            font-size: 3rem;
            color: #667eea;
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
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5fe;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            color: #764ba2;
        }
        
        .input-group-text {
            background: #f8f9ff;
            border: 2px solid #e1e5fe;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        .email-domain {
            font-size: 0.8rem;
            color: #667eea;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .form-floating {
            position: relative;
        }
        
        .role-selection {
            background: #f8f9ff;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .role-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .role-option:last-child {
            margin-bottom: 0;
        }
        
        .role-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .role-description {
            font-size: 0.85rem;
            color: #666;
            margin-left: 25px;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="signup-container">
                    <div class="logo-section">
                        <i class="fas fa-user-plus"></i>
                        <h2>Staff Registration</h2>
                        <p>ISAT U LibCollect: Reference Mapping System</p>
                    </div>
                    
                    <!-- Debug Information (remove in production) -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="debug-info">
                        <strong>Debug Info:</strong><br>
                        Database Connected: <?php echo isset($pdo) ? 'Yes' : 'No'; ?><br>
                        ActivityLogger: <?php echo class_exists('ActivityLogger') ? 'Available' : 'Using Fallback'; ?><br>
                        Current Directory: <?php echo __DIR__; ?><br>
                        Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Not set'; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="signupForm">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="full_name" class="form-label">
                                    <i class="fas fa-user me-1"></i>Full Name
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="full_name" 
                                       name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-at me-1"></i>Username
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       required>
                                <div class="password-requirements">
                                    3+ characters, letters, numbers, and underscores only
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    Staff Email
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="admin@staff.isatu.edu.ph"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                                <div class="email-domain">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Must end with @staff.isatu.edu.ph
                                </div>
                            </div>
                        </div>
                        
                        <div class="role-selection">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-1"></i>Role
                            </label>
                            <div class="role-option">
                                <input type="radio" id="staff" name="role" value="staff" 
                                       <?php echo (!isset($_POST['role']) || $_POST['role'] === 'staff') ? 'checked' : ''; ?>>
                                <label for="staff">Staff</label>
                            </div>
                            <div class="role-description">General library staff with basic access</div>
                            
                            <!--<div class="role-option">
                                <input type="radio" id="librarian" name="role" value="librarian"
                                       <?php echo (isset($_POST['role']) && $_POST['role'] === 'librarian') ? 'checked' : ''; ?>>
                                <label for="librarian">Librarian</label>
                            </div>-->
                            <div class="role-description">Library management with advanced features</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required>
                                <div class="password-requirements">
                                    Minimum 6 characters required
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Confirm Password
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Create Staff Account
                            </button>
                        </div>
                    </form>
                    
                    <div class="login-link">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login here
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const validPattern = /^[a-zA-Z0-9_]+$/;
            
            if (username.length > 0 && !validPattern.test(username)) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else if (username.length > 0 && username.length < 3) {
                this.setCustomValidity('Username must be at least 3 characters long');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-hide success message after 5 seconds
        <?php if ($success): ?>
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
    </script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

$database = new Database();
$pdo = $database->connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        
        // Validate required fields
        if (empty($username) || empty($full_name)) {
            $_SESSION['error'] = 'Username and full name are required.';
        } else {
            try {
                // Update profile information
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
                $stmt->execute([$username, $full_name, $user_id]);
                
                $_SESSION['success'] = 'Profile updated successfully!';
                $_SESSION['user_name'] = $username; // Update session username
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
            }
        }
        
        header('Location: profiles.php');
        exit;
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = 'New password must be at least 6 characters long.';
        } else {
            try {
                $user_id = $_SESSION['user_id'] ?? 1;
                
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    $_SESSION['success'] = 'Password changed successfully!';
                } else {
                    $_SESSION['error'] = 'Current password is incorrect.';
                }
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error changing password: ' . $e->getMessage();
            }
        }
        
        header('Location: profiles.php');
        exit;
    }
    
    if ($action === 'backup_database') {
        $backup_location = $_POST['backup_location'] ?? 'default';
        
        try {
            // Determine backup directory based on selection
            if ($backup_location === 'default') {
                $backup_dir = '../backups';
            } elseif ($backup_location === 'downloads') {
                $backup_dir = $_SERVER['DOCUMENT_ROOT'] . '/downloads/backups';
            } elseif ($backup_location === 'desktop') {
                $backup_dir = '../backups'; // Will still save locally but with different label
            } elseif ($backup_location === 'documents') {
                $backup_dir = '../backups';
            } else {
                $backup_dir = '../backups';
            }
            
            // Create backups directory if it doesn't exist
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            // Generate filename with timestamp
            $backup_filename = 'library_system_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_file = $backup_dir . '/' . $backup_filename;
            
            // Get database credentials from config
            $host = 'localhost';
            $dbname = 'library_system';
            $username = 'root';
            $password = '';
            
            // Start SQL content
            $sql_content = "-- LibCollect Database Backup\n";
            $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql_content .= "-- Database: " . $dbname . "\n";
            $sql_content .= "-- Backup Location: " . $backup_location . "\n\n";
            $sql_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $sql_content .= "START TRANSACTION;\n";
            $sql_content .= "SET time_zone = \"+00:00\";\n\n";
            
            // Get all tables
            $tables = array();
            $result = $pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $total_records = 0;
            
            // Backup each table
            foreach ($tables as $table) {
                $sql_content .= "\n--\n-- Table structure for table `$table`\n--\n\n";
                
                // Get CREATE TABLE statement
                $result = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch(PDO::FETCH_NUM);
                $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql_content .= $row[1] . ";\n\n";
                
                // Get table data
                $sql_content .= "--\n-- Dumping data for table `$table`\n--\n\n";
                
                $result = $pdo->query("SELECT * FROM `$table`");
                $num_rows = $result->rowCount();
                $total_records += $num_rows;
                
                if ($num_rows > 0) {
                    $result = $pdo->query("SELECT * FROM `$table`");
                    
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        $columns = array_keys($row);
                        $values = array_values($row);
                        
                        // Escape values
                        $escaped_values = array();
                        foreach ($values as $value) {
                            if ($value === null) {
                                $escaped_values[] = 'NULL';
                            } else {
                                $escaped_values[] = $pdo->quote($value);
                            }
                        }
                        
                        $sql_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (";
                        $sql_content .= implode(', ', $escaped_values);
                        $sql_content .= ");\n";
                    }
                    $sql_content .= "\n";
                }
            }
            
            $sql_content .= "COMMIT;\n";
            
            // Write to file
            file_put_contents($backup_file, $sql_content);
            
            // Log the backup
            $user_id = $_SESSION['user_id'] ?? 1;
            $user_name = $_SESSION['user_name'] ?? 'System';
            
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (action, description, user_id, user_name, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $file_size = filesize($backup_file);
            $description = "Database backup created: $backup_filename - " . count($tables) . " tables, $total_records records, Size: " . number_format($file_size / 1024, 2) . " KB, Location: $backup_location";
            
            $stmt->execute([
                'backup',
                $description,
                $user_id,
                $user_name,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            // Set success message with download link
            $_SESSION['success'] = 'Backup was successful!';
            $_SESSION['backup_file'] = $backup_filename;
            $_SESSION['backup_size'] = $file_size;
            $_SESSION['backup_tables'] = count($tables);
            $_SESSION['backup_records'] = $total_records;
            $_SESSION['backup_location'] = $backup_location;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating backup: ' . $e->getMessage();
        }
        
        header('Location: profiles.php');
        exit;
    }
}

// Fetch current user data
try {
    $user_id = $_SESSION['user_id'] ?? 1;
    $stmt = $pdo->prepare("
        SELECT username, full_name, created_at, updated_at, last_login
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Create default user data if not exists
        $user = [
            'username' => 'admin',
            'full_name' => 'System Administrator',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
            'last_login' => null
        ];
    }
    
} catch (PDOException $e) {
    $user = [
        'username' => 'admin',
        'full_name' => 'System Administrator',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
        'last_login' => null
    ];
}

// Get backup history
$backup_history = array();
$backup_dir = '../backups';
if (file_exists($backup_dir)) {
    $files = glob($backup_dir . '/library_*.sql');
    rsort($files); // Sort by most recent first
    
    foreach (array_slice($files, 0, 5) as $file) {
        $backup_history[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
}

$page_title = "User Profile - LibCollect: Reference Mapping System";
include '../includes/header.php';
?>

<style>
/* Dashboard Backup Styles */
.backup-dashboard-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.dashboard-title {
    background: #198754;
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.5rem;
    font-weight: bold;
}

.dashboard-title .icon {
    font-size: 2rem;
}

.instructions-box {
    background: #e6fdf2c7;
    border-left: 5px solid #198754;
    border-radius: 0;
    padding: 25px;
    margin: 0;
}

.instructions-title {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.instructions-list {
    font-size: 0.95rem;
    line-height: 1.8;
    margin-bottom: 15px;
    padding-left: 20px;
}

.instructions-list li {
    margin-bottom: 8px;
}

.note-box {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 5px;
    padding: 12px 15px;
    margin-top: 15px;
    font-size: 0.9rem;
}

.backup-controls {
    padding: 30px;
    text-align: center;
}

.backup-controls h5 {
    font-size: 1.2rem;
    margin-bottom: 20px;
    font-weight: 600;
}

.backup-location-select {
    max-width: 400px;
    margin: 0 auto 25px;
}

.backup-location-select select {
    width: 100%;
    padding: 12px 20px;
    font-size: 1rem;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
}

.backup-location-select select:hover {
    border-color: #2196f3;
}

.backup-location-select select:focus {
    outline: none;
    border-color: #2196f3;
    box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
}

.btn-backup-now {
    background: #2196f3;
    color: white;
    border: none;
    padding: 15px 50px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 6px rgba(33, 150, 243, 0.3);
}

.btn-backup-now:hover {
    background: #1976d2;
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
    color: white;
}

.btn-backup-now:active {
    transform: translateY(0);
}

.backup-success-info {
    background: #f8f9fa;
    border: 2px solid #28a745;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    text-align: left;
}

.backup-success-info h6 {
    color: #28a745;
    font-weight: bold;
    margin-bottom: 15px;
}

.backup-success-info p {
    margin: 8px 0;
    font-size: 0.95rem;
}

.download-backup-link {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 25px;
    background: #28a745;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    transition: all 0.3s;
}

.download-backup-link:hover {
    background: #218838;
    color: white;
    transform: translateY(-2px);
}

.spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn-backup-now.loading .spinner {
    display: inline-block;
}

.btn-backup-now.loading .btn-text {
    display: none;
}

/* Profile Styles */
.profile-avatar {
    transition: all 0.3s ease;
}

.profile-avatar:hover {
    transform: scale(1.05);
    opacity: 0.8;
}

.profile-details > div {
    padding: 0.25rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.profile-details > div:last-child {
    border-bottom: none;
}

.form-control.is-valid {
    border-color: #198754;
}

.form-control.is-invalid {
    border-color: #dc3545;
}
</style>

<!-- Display Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong><?php echo $_SESSION['success']; ?></strong>
        
        <?php if (isset($_SESSION['backup_file'])): ?>
            <div class="backup-success-info mt-3">
                <h6><i class="fas fa-info-circle me-2"></i>Backup Details:</h6>
                <p><strong>Filename:</strong> <?php echo htmlspecialchars($_SESSION['backup_file']); ?></p>
                <p><strong>Size:</strong> <?php echo number_format($_SESSION['backup_size'] / 1024, 2); ?> KB</p>
                <p><strong>Tables Backed Up:</strong> <?php echo $_SESSION['backup_tables']; ?></p>
                <p><strong>Total Records:</strong> <?php echo number_format($_SESSION['backup_records']); ?></p>
                <p><strong>Location:</strong> <?php echo ucfirst($_SESSION['backup_location']); ?></p>
                
                <a href="../backups/<?php echo $_SESSION['backup_file']; ?>" download class="download-backup-link">
                    <i class="fas fa-download me-1"></i> Download Backup File
                </a>
            </div>
            <?php 
                unset($_SESSION['backup_file']);
                unset($_SESSION['backup_size']);
                unset($_SESSION['backup_tables']);
                unset($_SESSION['backup_records']);
                unset($_SESSION['backup_location']);
            ?>
        <?php endif; ?>
        
        <?php unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1 class="h2 mb-2">User Profile</h1>
    <p class="mb-0">Manage your account information and preferences</p>
</div>

<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Profile Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Profile
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i>Reset Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Database Backup Dashboard Card -->
        <div class="backup-dashboard-card mb-4">
            <div class="dashboard-title">
                <span class="icon">📂</span>
                <span>LibCollect Database Backup Dashboard</span>
            </div>
            
            <div class="instructions-box">
                <h3 class="instructions-title">
                    <span>💾</span> How to Backup Your Database
                </h3>
                <ol class="instructions-list">
                    <li>Insert your <strong>USB flash drive</strong> or connect your <strong>external hard drive</strong>.</li>
                    <li>Choose the <strong>backup location</strong> from the list below.</li>
                    <li>Click the <strong>"Backup Now"</strong> button.</li>
                    <li>Wait for a few seconds — a message will show if the backup was successful.</li>
                    <li>Your backup file will look like: <code>library_system_backup_<?php echo date('Y-m-d_H-i-s'); ?>.sql</code></li>
                    <li>Keep this file in a safe location (USB or another computer).</li>
                </ol>
                
                <div class="note-box">
                    <strong>Note:</strong> Do not open or edit the backup file. It's only for restoring your database when needed.
                </div>
                
            </div>
            
            <div class="backup-controls">
                <!--<h5>Select Backup Location:</h5>
                
                <form method="POST" id="backupForm">
                    <input type="hidden" name="action" value="backup_database">
                    
                    <div class="backup-location-select">
                        <select name="backup_location" id="backupLocation" required>
                            <option value="default">Default (Local Folder)</option>
                            <option value="downloads">Downloads Folder</option>
                            <option value="desktop">Desktop (if accessible)</option>
                            <option value="documents">Documents Folder</option>
                        </select>
                    </div>-->
                    
                    <button type="submit" class="btn-backup-now" id="backupBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">
                            <i class="fas fa-save"></i> Backup Now
                        </span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Recent Backups -->
        <?php if (!empty($backup_history)): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Backups</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_history as $backup): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-file-archive text-success me-1"></i>
                                    <?php echo htmlspecialchars($backup['filename']); ?>
                                </td>
                                <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                <td><?php echo date('M d, Y g:i A', $backup['date']); ?></td>
                                <td>
                                    <a href="../backups/<?php echo $backup['filename']; ?>" 
                                       download class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Profile Summary -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profile Summary</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="profile-avatar mx-auto mb-2">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                    <small class="text-muted">Librarian</small>
                </div>
                
                <hr>
                
                <div class="profile-details">
                    <div class="mb-2">
                        <small class="text-muted">Member Since:</small>
                        <div><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <?php if ($user['last_login']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Last Login:</small>
                        <div><?php echo date('M d, Y g:i A', strtotime($user['last_login'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" 
                               id="newPassword" minlength="6" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" name="confirm_password" 
                               id="confirmPassword" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-lock me-1"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Profile form validation
    const profileForm = document.getElementById('profileForm');
    
    // Password form validation
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    function validatePasswords() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.classList.add('is-invalid');
                newPassword.classList.add('is-invalid');
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
                newPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
                newPassword.classList.add('is-valid');
            }
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    
    // Profile form submission
    profileForm.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
        submitBtn.disabled = true;
    });
    
    // Password form submission
    passwordForm.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('New passwords do not match!');
            return false;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Changing...';
        submitBtn.disabled = true;
    });
    
    // Backup form submission
    const backupForm = document.getElementById('backupForm');
    const backupBtn = document.getElementById('backupBtn');
    const backupLocation = document.getElementById('backupLocation');
    
    backupForm.addEventListener('submit', function(e) {
        const location = backupLocation.options[backupLocation.selectedIndex].text;
        
        // Show confirmation
        const confirmMsg = `Are you sure you want to create a backup?\n\nLocation: ${location}\n\nThis will backup all your library data including books, archives, logs, and user information.`;
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        backupBtn.classList.add('loading');
        backupBtn.disabled = true;
    });
});

// Profile picture hover effect
document.addEventListener('DOMContentLoaded', function() {
    const profileAvatar = document.querySelector('.profile-avatar');
    if (profileAvatar) {
        profileAvatar.style.cursor = 'pointer';
        profileAvatar.title = 'Click to change profile picture (Coming Soon)';
        
        profileAvatar.addEventListener('click', function() {
            alert('Profile picture upload feature coming soon!');
        });
    }
});

// Auto-hide success messages after 15 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert && !alert.querySelector('.backup-success-info')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 15000);
</script>

<?php include '../includes/footer.php'; ?>
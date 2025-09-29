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
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = $_POST['department'];
        $position = $_POST['position'];
        $bio = trim($_POST['bio']);
        
        // Validate required fields
        if (empty($username) || empty($full_name) || empty($email)) {
            $_SESSION['error'] = 'Username, full name, and email are required.';
        } else {
            try {
                // Update profile information
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, phone = ?, 
                        department = ?, position = ?, bio = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
                $stmt->execute([$username, $full_name, $email, $phone, $department, $position, $bio, $user_id]);
                
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
}

// Fetch current user data
try {
    $user_id = $_SESSION['user_id'] ?? 1;
    $stmt = $pdo->prepare("
        SELECT username, full_name, email, phone, department, position, 
               bio, created_at, updated_at, last_login
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
            'email' => 'admin@isatu.edu.ph',
            'phone' => '',
            'department' => 'Library',
            'position' => 'Librarian',
            'bio' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
            'last_login' => null
        ];
    }
    
} catch (PDOException $e) {
    $user = [
        'username' => 'admin',
        'full_name' => 'System Administrator',
        'email' => 'admin@isatu.edu.ph',
        'phone' => '',
        'department' => 'Library',
        'position' => 'Librarian',
        'bio' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
        'last_login' => null
    ];
}

$page_title = "User Profile - LibCollect: Reference Mapping System";
include '../includes/header.php';
?>

<!-- Display Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
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
    <!-- Profile Information Card -->
    <div class="col-lg-8">
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
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position" 
                                   value="Librarian" readonly style="background-color: #f8f9fa;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" 
                                   value="Library" readonly style="background-color: #f8f9fa;">
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
    </div>
    
    <!-- Profile Summary & Password Change -->
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
                    <small class="text-muted"><?php echo htmlspecialchars($user['position']); ?></small>
                </div>
                
                <hr>
                
                <div class="profile-details">
                    <div class="mb-2">
                        <small class="text-muted">Department:</small>
                        <div><?php echo htmlspecialchars($user['department']); ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Email:</small>
                        <div><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <?php if ($user['phone']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Phone:</small>
                        <div><?php echo htmlspecialchars($user['phone']); ?></div>
                    </div>
                    <?php endif; ?>
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

<!-- Activity & Statistics (Optional) -->
<!--<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Account Activity</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <i class="fas fa-book fa-2x text-primary mb-2"></i>
                            <h4 class="mb-1">0</h4>
                            <small class="text-muted">Books Added</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <i class="fas fa-archive fa-2x text-warning mb-2"></i>
                            <h4 class="mb-1">0</h4>
                            <small class="text-muted">Books Archived</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <i class="fas fa-edit fa-2x text-info mb-2"></i>
                            <h4 class="mb-1">0</h4>
                            <small class="text-muted">Records Modified</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                            <h4 class="mb-1">
                                <?php echo $user['updated_at'] ? date('M d', strtotime($user['updated_at'])) : 'N/A'; ?>
                            </h4>
                            <small class="text-muted">Last Update</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>-->

<script>
// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Profile form validation
    const profileForm = document.getElementById('profileForm');
    const bioTextarea = document.querySelector('textarea[name="bio"]');
    
    // Bio character counter
    if (bioTextarea) {
        const maxLength = 500;
        
        function updateCounter() {
            const currentLength = bioTextarea.value.length;
            let counter = document.getElementById('bioCounter');
            
            if (!counter) {
                counter = document.createElement('small');
                counter.id = 'bioCounter';
                counter.className = 'text-muted';
                bioTextarea.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${currentLength}/${maxLength} characters`;
            
            if (currentLength > maxLength) {
                counter.className = 'text-danger';
                bioTextarea.value = bioTextarea.value.substring(0, maxLength);
                counter.textContent = `${maxLength}/${maxLength} characters`;
            } else {
                counter.className = 'text-muted';
            }
        }
        
        bioTextarea.addEventListener('input', updateCounter);
        updateCounter(); // Initial call
    }
    
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
    
    // Form submission handlers
    profileForm.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
        submitBtn.disabled = true;
    });
    
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
    
    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('63')) {
                value = value.substring(2);
            }
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = `+63 ${value}`;
                } else if (value.length <= 6) {
                    value = `+63 ${value.substring(0, 3)} ${value.substring(3)}`;
                } else {
                    value = `+63 ${value.substring(0, 3)} ${value.substring(3, 6)} ${value.substring(6, 10)}`;
                }
            }
            e.target.value = value;
        });
    }
});

// Profile picture hover effect (placeholder for future implementation)
document.addEventListener('DOMContentLoaded', function() {
    const profileAvatar = document.querySelector('.profile-avatar');
    if (profileAvatar) {
        profileAvatar.style.cursor = 'pointer';
        profileAvatar.title = 'Click to change profile picture (Coming Soon)';
        
        profileAvatar.addEventListener('click', function() {
            // Future: Open file picker for profile picture
            alert('Profile picture upload feature coming soon!');
        });
    }
});
</script>

<style>
.profile-avatar {
    transition: all 0.3s ease;
}

.profile-avatar:hover {
    transform: scale(1.05);
    opacity: 0.8;
}

.stat-item {
    padding: 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background-color: rgba(0,0,0,0.05);
    transform: translateY(-2px);
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

<?php include '../includes/footer.php'; ?>
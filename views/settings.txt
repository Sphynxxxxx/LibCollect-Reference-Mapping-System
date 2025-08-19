<?php
$page_title = "Settings - ISAT U Library";
include '../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'university_info':
                $_SESSION['message'] = 'University information updated successfully!';
                $_SESSION['message_type'] = 'success';
                break;
            case 'preferences':
                $_SESSION['message'] = 'Preferences updated successfully!';
                $_SESSION['message_type'] = 'success';
                break;
        }
        header('Location: settings.php');
        exit;
    }
}
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1 class="h2 mb-2">Settings</h1>
    <p class="mb-0">Configure library system settings</p>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-university me-2"></i>University Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="university_info">
                    <div class="mb-3">
                        <label class="form-label">University Name</label>
                        <input type="text" class="form-control" value="Iloilo Science and Technology University" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Library Name</label>
                        <input type="text" class="form-control" name="library_name" value="ISAT U Main Library">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Library Code</label>
                        <input type="text" class="form-control" name="library_code" value="ISATU-LIB-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-control" name="contact_email" value="library@isatu.edu.ph">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Preferences</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="preferences">
                    <div class="mb-3">
                        <label class="form-label">Books per Page</label>
                        <select class="form-control" name="books_per_page">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Category View</label>
                        <select class="form-control" name="default_category">
                            <option value="all" selected>All Categories</option>
                            <option value="BIT">BIT</option>
                            <option value="EDUCATION">EDUCATION</option>
                            <option value="HBM">HBM</option>
                            <option value="COMPSTUD">COMPSTUD</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="autoBackup" name="auto_backup" checked>
                            <label class="form-check-label" for="autoBackup">
                                Enable Auto Backup
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notifications" name="notifications" checked>
                            <label class="form-check-label" for="notifications">
                                Enable Notifications
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-save me-1"></i>Update Preferences
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Management</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Manage database operations and maintenance</p>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-success w-100" onclick="backupDatabase()">
                            <i class="fas fa-download me-1"></i>Backup Database
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-info w-100" onclick="restoreDatabase()">
                            <i class="fas fa-upload me-1"></i>Restore Database
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-warning w-100" onclick="cleanDatabase()">
                            <i class="fas fa-broom me-1"></i>Clean Database
                        </button>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button class="btn btn-outline-primary w-100" onclick="viewStatistics()">
                            <i class="fas fa-chart-line me-1"></i>View Statistics
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function backupDatabase() {
    showToast('Database backup initiated!', 'info');
    // Implementation for database backup
}

function restoreDatabase() {
    showToast('Database restore feature coming soon!', 'info');
    // Implementation for database restore
}

function cleanDatabase() {
    showToast('Database cleanup feature coming soon!', 'info');
    // Implementation for database cleanup
}

function viewStatistics() {
    window.location.href = 'index.php';
}
</script>

<?php include '../includes/footer.php'; ?>
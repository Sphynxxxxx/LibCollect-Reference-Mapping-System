<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // User is not logged in, redirect to login page
    header("Location: auth/login.php");
    exit();
}

// Optional: Check for session timeout
$session_timeout = 30 * 60; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    // Session has expired
    $username = $_SESSION['username'] ?? 'User';
    session_destroy();
    header("Location: auth/login.php?message=session_expired&user=" . urlencode($username));
    exit();
}
// Update last activity time
$_SESSION['last_activity'] = time();

$page_title = "Dashboard - LibCollect: Reference Mapping System";
include 'includes/header.php';
include 'classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);
$stats = $book->getBookStats();

// Handle different stats formats for categories
$categoryCounts = [];

// Try expanded format first (handles multi-context books)
if (isset($stats['by_category_expanded']) && !empty($stats['by_category_expanded'])) {
    foreach ($stats['by_category_expanded'] as $stat) {
        $categoryCounts[$stat['category']] = $stat['count'];
    }
}
// Fall back to regular format
elseif (isset($stats['by_category']) && !empty($stats['by_category'])) {
    foreach ($stats['by_category'] as $stat) {
        $category = $stat['category'] ?? $stat['primary_category'] ?? 'Unknown';
        $categoryCounts[$category] = $stat['count'];
    }
}
// Manual fallback if no stats available
else {
    foreach (['BIT', 'EDUCATION', 'HBM', 'COMPSTUD'] as $category) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM books WHERE FIND_IN_SET(?, category) > 0");
            $stmt->execute([$category]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $categoryCounts[$category] = $result['count'] ?? 0;
        } catch (Exception $e) {
            $categoryCounts[$category] = 0;
        }
    }
}

// Get recent activity logs using Book class method
$recentActivities = $book->getActivityLog(10);

// Function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

// Function to get activity icon and color based on action
function getActivityIcon($action) {
    switch(strtolower($action)) {
        case 'add':
        case 'create':
            return ['icon' => 'fas fa-plus', 'color' => 'success'];
        case 'update':
        case 'edit':
            return ['icon' => 'fas fa-edit', 'color' => 'primary'];
        case 'delete':
        case 'remove':
            return ['icon' => 'fas fa-trash', 'color' => 'danger'];
        case 'archive':
            return ['icon' => 'fas fa-archive', 'color' => 'warning'];
        case 'restore':
            return ['icon' => 'fas fa-undo-alt', 'color' => 'info'];
        case 'borrow':
            return ['icon' => 'fas fa-hand-holding', 'color' => 'warning'];
        case 'return':
            return ['icon' => 'fas fa-undo', 'color' => 'info'];
        case 'login':
            return ['icon' => 'fas fa-sign-in-alt', 'color' => 'success'];
        case 'logout':
            return ['icon' => 'fas fa-sign-out-alt', 'color' => 'secondary'];
        case 'import':
        case 'export':
            return ['icon' => 'fas fa-exchange-alt', 'color' => 'primary'];
        case 'search':
            return ['icon' => 'fas fa-search', 'color' => 'info'];
        default:
            return ['icon' => 'fas fa-circle', 'color' => 'primary'];
    }
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="h2 mb-2">Dashboard</h1>
            <p class="mb-0">Overview of ISAT U Library System</p>
        </div>
        <div class="col-auto">
            <i class="fas fa-chart-line fa-3x opacity-50"></i>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-primary mb-1"><?php echo number_format($stats['total_books']); ?></h3>
                    <p class="text-muted mb-0">Active Books</p>
                    <?php if (isset($stats['multi_context_books']) && $stats['multi_context_books'] > 0): ?>
                        <small class="text-info">
                            <i class="fas fa-layer-group me-1"></i><?php echo $stats['multi_context_books']; ?> multi-context
                        </small>
                    <?php endif; ?>
                </div>
                <i class="fas fa-book fa-2x text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-success mb-1">4</h3>
                    <p class="text-muted mb-0">Departments</p>
                    <small class="text-success">
                        <i class="fas fa-graduation-cap me-1"></i>Academic Programs
                    </small>
                </div>
                <i class="fas fa-layer-group fa-2x text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-info mb-1"><?php echo number_format($stats['total_copies']); ?></h3>
                    <p class="text-muted mb-0">Total Copies</p>
                    <small class="text-info">
                        <i class="fas fa-copy me-1"></i>Physical books
                    </small>
                </div>
                <i class="fas fa-copy fa-2x text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-warning mb-1"><?php echo number_format($stats['available_copies']); ?></h3>
                    <p class="text-muted mb-0">Available</p>
                    <?php if (isset($stats['borrowed_books']) && $stats['borrowed_books'] > 0): ?>
                        <small class="text-warning">
                            <i class="fas fa-hand-holding me-1"></i><?php echo $stats['borrowed_books']; ?> borrowed
                        </small>
                    <?php else: ?>
                        <small class="text-success">
                            <i class="fas fa-check-circle me-1"></i>All available
                        </small>
                    <?php endif; ?>
                </div>
                <i class="fas fa-check-circle fa-2x text-warning"></i>
            </div>
        </div>
    </div>
</div>

<!-- Category Distribution -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Books by Department</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    $categoryColors = [
                        'BIT' => ['color' => 'warning', 'name' => 'Bachelor of Industrial Technology'], // Yellow
                        'EDUCATION' => ['color' => 'primary', 'name' => 'Education'], // Blue
                        'HBM' => ['color' => 'danger', 'name' => 'Hotel & Business Management'], // Red
                        'COMPSTUD' => ['color' => 'dark', 'name' => 'Computer Studies'] // Black
                    ];
                
                    
                    foreach ($categoryColors as $category => $info):
                        $count = isset($categoryCounts[$category]) ? $categoryCounts[$category] : 0;
                    ?>
                    <div class="col-6 mb-3">
                        <div class="category-stat p-2">
                            <h4 class="text-<?php echo $info['color']; ?> mb-1"><?php echo number_format($count); ?></h4>
                            <span class="badge bg-<?php echo $info['color']; ?> mb-2"><?php echo $category; ?></span>
                            <p class="small text-muted mb-0 px-1"><?php echo $info['name']; ?></p>
                            <?php if ($count > 0 && $stats['total_books'] > 0): ?>
                                <small class="text-<?php echo $info['color']; ?>">
                                    <?php 
                                    $percentage = round(($count / $stats['total_books']) * 100, 1);
                                    echo $percentage . '% of collection';
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Total Collection:</span>
                        <strong><?php echo number_format($stats['total_books']); ?> books</strong>
                    </div>
                    <div class="text-center">
                        <a href="views/books.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>View All Books
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                <small class="text-white-50">Last 10 activities</small>
            </div>
            <div class="card-body">
                <?php if (!empty($recentActivities)): ?>
                    <div class="activity-timeline">
                        <?php foreach ($recentActivities as $activity): 
                            $iconData = getActivityIcon($activity['action']);
                        ?>
                        <div class="activity-item mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <div class="activity-icon me-3">
                                    <i class="<?php echo $iconData['icon']; ?> text-<?php echo $iconData['color']; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-capitalize"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            
                                            <?php if (!empty($activity['book_title'])): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="fas fa-book me-1"></i>
                                                <strong>Book:</strong> <?php echo htmlspecialchars($activity['book_title']); ?>
                                                <?php if (!empty($activity['category'])): ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($activity['category']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($activity['user_name'])): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <strong>By:</strong> <?php echo htmlspecialchars($activity['user_name']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                        <p class="mb-0">No recent activity found</p>
                        <small>Activity will appear here as you use the system</small>
                    </div>
                <?php endif; ?>
                
                <!-- View All Activities Link -->
                <div class="text-center mt-3">
                    <a href="views/activity_logs.php" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-list me-1"></i>View All Activities
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Row -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <a href="views/add-book.php" class="btn btn-outline-success btn-lg w-100">
                            <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                            Add New Book
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="views/books.php" class="btn btn-outline-primary btn-lg w-100">
                            <i class="fas fa-search fa-2x d-block mb-2"></i>
                            Browse Books
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="views/archives.php" class="btn btn-outline-warning btn-lg w-100">
                            <i class="fas fa-archive fa-2x d-block mb-2"></i>
                            Manage Archives
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="views/reports.php" class="btn btn-outline-info btn-lg w-100">
                            <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item:last-child {
    border-bottom: none !important;
}

.activity-icon {
    width: 24px;
    text-align: center;
}

.stats-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.category-stat {
    transition: all 0.3s ease;
    border-radius: 8px;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.category-stat:hover {
    background-color: rgba(0,0,0,0.03);
    transform: translateY(-2px);
}

/* Custom scrollbar for activity timeline */
.activity-timeline::-webkit-scrollbar {
    width: 4px;
}

.activity-timeline::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.activity-timeline::-webkit-scrollbar-thumb {
    background: #28a745;
    border-radius: 10px;
}

.activity-timeline::-webkit-scrollbar-thumb:hover {
    background: #218838;
}

/* Card hover effects */
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Badge improvements */
.badge {
    font-weight: 500;
}

/* Quick action buttons */
.btn-lg i {
    opacity: 0.7;
}

.btn-lg:hover i {
    opacity: 1;
    transform: scale(1.1);
    transition: all 0.2s;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .activity-timeline {
        max-height: 300px;
    }
    
    .btn-lg {
        font-size: 0.9rem;
    }
    
    .btn-lg i {
        font-size: 1.5rem !important;
    }
    
    .category-stat {
        min-height: 100px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
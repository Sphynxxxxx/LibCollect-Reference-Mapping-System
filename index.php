<?php
$page_title = "Dashboard - ISAT U Library Miagao Campus";
include 'includes/header.php';
include 'classes/Book.php';

$book = new Book($pdo);
$stats = $book->getBookStats();

// Get recent activity logs
$activityQuery = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10";
$activityStmt = $pdo->prepare($activityQuery);
$activityStmt->execute();
$recentActivities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

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
        case 'borrow':
            return ['icon' => 'fas fa-hand-holding', 'color' => 'warning'];
        case 'return':
            return ['icon' => 'fas fa-undo', 'color' => 'info'];
        case 'login':
            return ['icon' => 'fas fa-sign-in-alt', 'color' => 'success'];
        case 'logout':
            return ['icon' => 'fas fa-sign-out-alt', 'color' => 'secondary'];
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
        <div class="stats-card text-center">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-primary mb-1"><?php echo $stats['total_books']; ?></h3>
                    <p class="text-muted mb-0">Total Books</p>
                </div>
                <i class="fas fa-book fa-2x text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card text-center">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-success mb-1">4</h3>
                    <p class="text-muted mb-0">Categories</p>
                </div>
                <i class="fas fa-layer-group fa-2x text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card text-center">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-info mb-1"><?php echo $stats['total_copies']; ?></h3>
                    <p class="text-muted mb-0">Total Copies</p>
                </div>
                <i class="fas fa-copy fa-2x text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stats-card text-center">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-warning mb-1"><?php echo $stats['total_copies']; ?></h3>
                    <p class="text-muted mb-0">Available</p>
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
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Books by Category</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    $categoryColors = ['BIT' => 'primary', 'EDUCATION' => 'success', 'HBM' => 'info', 'COMPSTUD' => 'warning'];
                    $categoryCounts = array_column($stats['by_category'], 'count', 'category');
                    
                    foreach (['BIT', 'EDUCATION', 'HBM', 'COMPSTUD'] as $index => $category):
                        $count = isset($categoryCounts[$category]) ? $categoryCounts[$category] : 0;
                        $color = $categoryColors[$category];
                    ?>
                    <div class="col-6 mb-3">
                        <?php if ($index % 2 == 0): ?><div class="border-end"><?php endif; ?>
                            <h4 class="text-<?php echo $color; ?>"><?php echo $count; ?></h4>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo $category; ?></span>
                        <?php if ($index % 2 == 0): ?></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
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
                                            
                                            <?php if ($activity['book_title']): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-book me-1"></i>
                                                <strong>Book:</strong> <?php echo htmlspecialchars($activity['book_title']); ?>
                                                <?php if ($activity['category']): ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo $activity['category']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($activity['user_name']): ?>
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
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-5px);
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
</style>

<?php include 'includes/footer.php'; ?>
<?php
$page_title = "Activity Logs - ISAT U Library";
include '../includes/header.php';
require_once '../classes/ActivityLogger.php';

$logger = new ActivityLogger($pdo);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($actionFilter)) {
    $whereConditions[] = "action = ?";
    $params[] = $actionFilter;
}

if (!empty($dateFilter)) {
    $whereConditions[] = "DATE(created_at) = ?";
    $params[] = $dateFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get activities
$sql = "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter
$actionsSql = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$actionsStmt = $pdo->prepare($actionsSql);
$actionsStmt->execute();
$availableActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate pagination
$totalPages = ceil($totalRecords / $limit);

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
        case 'failed_login':
            return ['icon' => 'fas fa-exclamation-triangle', 'color' => 'danger'];
        default:
            return ['icon' => 'fas fa-circle', 'color' => 'primary'];
    }
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="h2 mb-2">Activity Logs</h1>
            <p class="mb-0">System activity and audit trail</p>
        </div>
        <div class="col-auto">
            <i class="fas fa-history fa-3x opacity-50"></i>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Filter by Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($availableActions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" 
                                <?php echo ($actionFilter === $action) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($action); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Filter by Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <a href="activity_logs.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
            
            <div class="col-md-3 text-end">
                <small class="text-muted">
                    Showing <?php echo number_format($totalRecords); ?> records
                </small>
            </div>
        </form>
    </div>
</div>

<!-- Activity List -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Activity History</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($activities)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Book</th>
                            <th>User</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): 
                            $iconData = getActivityIcon($activity['action']);
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php echo $iconData['color']; ?> d-inline-flex align-items-center">
                                    <i class="<?php echo $iconData['icon']; ?> me-1"></i>
                                    <?php echo ucfirst($activity['action']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($activity['description']); ?>">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($activity['book_title']): ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['book_title']); ?></strong>
                                        <?php if ($activity['category']): ?>
                                            <br><small class="text-muted"><?php echo $activity['category']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($activity['user_name']): ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <?php if ($activity['user_id']): ?>
                                            <br><small class="text-muted">ID: <?php echo $activity['user_id']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <?php echo date('M j, Y', strtotime($activity['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($activity['created_at'])); ?></small>
                                </div>
                            </td>
                        
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No activities found</h5>
                <p class="text-muted">No activities match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-4" aria-label="Activity logs pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?>&action=<?php echo $actionFilter; ?>&date=<?php echo $dateFilter; ?>">Previous</a>
        </li>
        
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        for ($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&action=<?php echo $actionFilter; ?>&date=<?php echo $dateFilter; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        
        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?>&action=<?php echo $actionFilter; ?>&date=<?php echo $dateFilter; ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<style>
.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.875rem;
}

.table td {
    vertical-align: middle;
}

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.font-monospace {
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
}

.badge {
    font-size: 0.75rem;
}
</style>

<?php include '../includes/footer.php'; ?>
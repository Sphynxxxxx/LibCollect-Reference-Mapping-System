<?php
$page_title = "Dashboard - ISAT U Library";
include 'includes/header.php';
include 'classes/Book.php';

$book = new Book($pdo);
$stats = $book->getBookStats();
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
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 pb-2 border-bottom">
                        <small class="text-muted">2 minutes ago</small><br>
                        <span>Added "Database Systems" to BIT category</span>
                    </li>
                    <li class="mb-2 pb-2 border-bottom">
                        <small class="text-muted">15 minutes ago</small><br>
                        <span>Updated "Educational Psychology" book</span>
                    </li>
                    <li class="mb-2 pb-2">
                        <small class="text-muted">1 hour ago</small><br>
                        <span>Added 5 new books to HBM category</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
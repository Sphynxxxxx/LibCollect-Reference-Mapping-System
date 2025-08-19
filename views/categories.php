<?php
$page_title = "Categories - LibCollect: Reference Mapping System";
include '../includes/header.php';
include '../classes/Book.php';

$book = new Book($pdo);
$stats = $book->getBookStats();
$categoryCounts = array_column($stats['by_category'], 'count', 'category');
?>

<div class="page-header">
    <h1 class="h2 mb-2">Categories</h1>
    <p class="mb-0">Manage book categories for ISAT U Miagao Campus programs</p>
</div>

<div class="row">
    <?php
    $categories = [
        'BIT' => ['name' => 'Bachelor of Industrial Technology', 'icon' => 'fas fa-microchip', 'color' => 'primary'],
        'EDUCATION' => ['name' => 'Educational Resources', 'icon' => 'fas fa-graduation-cap', 'color' => 'success'],
        'HBM' => ['name' => 'Hotel & Business Management', 'icon' => 'fas fa-hotel', 'color' => 'info'],
        'COMPSTUD' => ['name' => 'Computer Studies', 'icon' => 'fas fa-laptop-code', 'color' => 'warning']
    ];
    
    foreach ($categories as $code => $category):
        $count = isset($categoryCounts[$code]) ? $categoryCounts[$code] : 0;
        $percentage = $stats['total_books'] > 0 ? round(($count / $stats['total_books']) * 100) : 0;
    ?>
    <div class="col-md-3 mb-4">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="<?php echo $category['icon']; ?> fa-3x text-<?php echo $category['color']; ?> mb-3"></i>
                <h5><?php echo $code; ?></h5>
                <p class="text-muted"><?php echo $category['name']; ?></p>
                <div class="d-flex justify-content-between">
                    <small class="text-muted"><?php echo $count; ?> books</small>
                    <small class="text-<?php echo $category['color']; ?>"><?php echo $percentage; ?>% of collection</small>
                </div>
                <div class="mt-3">
                    <a href="books.php?category=<?php echo $code; ?>" class="btn btn-outline-<?php echo $category['color']; ?> btn-sm">
                        <i class="fas fa-eye me-1"></i>View Books
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Category Statistics -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Category Statistics</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Full Name</th>
                                <th>Books</th>
                                <th>Percentage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $code => $category):
                                $count = isset($categoryCounts[$code]) ? $categoryCounts[$code] : 0;
                                $percentage = $stats['total_books'] > 0 ? round(($count / $stats['total_books']) * 100) : 0;
                            ?>
                            <tr>
                                <td><span class="badge bg-<?php echo $category['color']; ?>"><?php echo $code; ?></span></td>
                                <td><?php echo $category['name']; ?></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $category['color']; ?>" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100"><?php echo $percentage; ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <a href="books.php?category=<?php echo $code; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-list me-1"></i>View
                                    </a>
                                    <a href="add-book.php" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-plus me-1"></i>Add Book
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
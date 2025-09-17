<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

function getCategoryDisplayInfo($categoryString, $departments) {
    if (empty($categoryString)) {
        return ['primary' => 'Unknown', 'color' => 'secondary', 'display' => 'Unknown', 'count' => 0];
    }
    
    $categories = array_map('trim', explode(',', $categoryString));
    $primaryCategory = $categories[0];
    $categoryCount = count($categories);
    
    // Get color from primary category or default to secondary
    $color = isset($departments[$primaryCategory]) ? $departments[$primaryCategory]['color'] : 'secondary';
    
    // Create display text
    if ($categoryCount > 1) {
        $display = $primaryCategory . ' +' . ($categoryCount - 1);
    } else {
        $display = $primaryCategory;
    }
    
    return [
        'primary' => $primaryCategory,
        'color' => $color,
        'display' => $display,
        'count' => $categoryCount,
        'all' => $categories
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'auto_archive':
                $result = $book->bulkArchiveEligibleBooks();
                if ($result['success']) {
                    $_SESSION['message'] = "Successfully archived {$result['archived']} books older than 10 years.";
                    $_SESSION['message_type'] = 'success';
                    if (!empty($result['errors'])) {
                        $_SESSION['message'] .= " ({$result['errors']} errors)";
                    }
                } else {
                    $_SESSION['message'] = 'Failed to auto-archive books!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'restore':
                if ($book->restoreFromArchive($_POST['id'])) {
                    $_SESSION['message'] = 'Book restored successfully!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to restore book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'permanent_delete':
                // Get archived book details for logging before deletion
                $stmt = $pdo->prepare("SELECT * FROM archived_books WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $archivedBook = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($archivedBook) {
                    $stmt = $pdo->prepare("DELETE FROM archived_books WHERE id = ?");
                    if ($stmt->execute([$_POST['id']])) {
                        // Log the permanent deletion
                        $book->logCustomActivity(
                            'permanent_delete',
                            "Permanently deleted archived book: {$archivedBook['title']} by {$archivedBook['author']}",
                            $_POST['id'],
                            $archivedBook['title'],
                            $archivedBook['category']
                        );
                        $_SESSION['message'] = 'Book permanently deleted!';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to delete book!';
                        $_SESSION['message_type'] = 'danger';
                    }
                } else {
                    $_SESSION['message'] = 'Book not found!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'manual_archive':
                if ($book->archiveBook($_POST['id'], 'Manual archiving via archives interface', 'Admin')) {
                    $_SESSION['message'] = 'Book archived successfully!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to archive book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
        }
        header('Location: archives.php');
        exit;
    }
}

// Function to merge duplicate archived books (updated for is_multi_context)
function mergeArchivedBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $bookItem) {
        // Create a unique key based on title, author, ISBN, and publication year
        $key = md5(strtolower($bookItem['title'] . '|' . $bookItem['author'] . '|' . $bookItem['isbn'] . '|' . ($bookItem['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            // First occurrence of this book
            $mergedBooks[$key] = $bookItem;
            $mergedBooks[$key]['total_quantity'] = $bookItem['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$bookItem['id']];
            $mergedBooks[$key]['archive_dates'] = [date('Y-m-d', strtotime($bookItem['archived_at']))];
            $mergedBooks[$key]['archive_reasons'] = [$bookItem['archive_reason']];
        } else {
            // Merge with existing book
            $mergedBooks[$key]['total_quantity'] += $bookItem['quantity'];
            $mergedBooks[$key]['record_ids'][] = $bookItem['id'];
            $archiveDate = date('Y-m-d', strtotime($bookItem['archived_at']));
            if (!in_array($archiveDate, $mergedBooks[$key]['archive_dates'])) {
                $mergedBooks[$key]['archive_dates'][] = $archiveDate;
            }
            if (!in_array($bookItem['archive_reason'], $mergedBooks[$key]['archive_reasons'])) {
                $mergedBooks[$key]['archive_reasons'][] = $bookItem['archive_reason'];
            }
        }
        
        // Add academic context
        $context = [];
        if (!empty($bookItem['category'])) $context['category'] = $bookItem['category'];
        if (!empty($bookItem['year_level'])) $context['year_level'] = $bookItem['year_level'];
        if (!empty($bookItem['semester'])) $context['semester'] = $bookItem['semester'];
        if (!empty($bookItem['section'])) $context['section'] = $bookItem['section'];
        if (!empty($bookItem['subject_name'])) $context['subject_name'] = $bookItem['subject_name'];
        if (!empty($bookItem['course_code'])) $context['course_code'] = $bookItem['course_code'];
        
        // Only add if context has meaningful data
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

function getArchivedBooksGrouped($pdo, $category = '', $search = '', $groupBy = 'category') {
    try {
        $query = "SELECT * FROM archived_books WHERE 1=1";
        $params = [];
        
        if ($category) {
            $query .= " AND (category = ? OR FIND_IN_SET(?, category) > 0)";
            $params[] = $category;
            $params[] = $category;
        }
        
        if ($search) {
            $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR publication_year LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Add ordering
        $query .= " ORDER BY title ASC, author ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // First merge duplicate books
        $mergedBooks = mergeArchivedBooks($books);
        
        // Then group the merged results
        $grouped = [];
        foreach ($mergedBooks as $bookItem) {
            switch ($groupBy) {
                case 'year':
                    $key = $bookItem['publication_year'] ?: 'Unknown Year';
                    break;
                case 'author':
                    $key = strtoupper(substr($bookItem['author'], 0, 1));
                    break;
                case 'archive_date':
                    $key = $bookItem['archive_dates'][0]; // Use first archive date
                    break;
                default: // category
                    $key = $bookItem['category'];
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $bookItem;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        error_log("Error getting archived books: " . $e->getMessage());
        return [];
    }
}

function getBooksEligibleForArchivingGrouped($pdo, $groupBy = 'category') {
    try {
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 5;
        
        $sql = "SELECT * FROM books 
                WHERE publication_year IS NOT NULL AND publication_year <= ?
                ORDER BY title ASC, author ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffYear]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // First merge duplicate eligible books
        $mergedBooks = mergeEligibleBooks($books);
        
        // Then group the merged results
        $grouped = [];
        foreach ($mergedBooks as $bookItem) {
            switch ($groupBy) {
                case 'year':
                    $key = $bookItem['publication_year'] ?: 'Unknown Year';
                    break;
                case 'author':
                    $key = strtoupper(substr($bookItem['author'], 0, 1));
                    break;
                default: // category
                    $key = $bookItem['category'];
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $bookItem;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        error_log("Error getting books eligible for archiving: " . $e->getMessage());
        return [];
    }
}

// Function to merge duplicate eligible books
function mergeEligibleBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $bookItem) {
        // Create a unique key based on title, author, ISBN, and publication year
        $key = md5(strtolower($bookItem['title'] . '|' . $bookItem['author'] . '|' . $bookItem['isbn'] . '|' . ($bookItem['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            // First occurrence of this book
            $mergedBooks[$key] = $bookItem;
            $mergedBooks[$key]['total_quantity'] = $bookItem['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$bookItem['id']];
        } else {
            // Merge with existing book
            $mergedBooks[$key]['total_quantity'] += $bookItem['quantity'];
            $mergedBooks[$key]['record_ids'][] = $bookItem['id'];
        }
        
        // Add academic context
        $context = [];
        if (!empty($bookItem['category'])) $context['category'] = $bookItem['category'];
        if (!empty($bookItem['year_level'])) $context['year_level'] = $bookItem['year_level'];
        if (!empty($bookItem['semester'])) $context['semester'] = $bookItem['semester'];
        if (!empty($bookItem['section'])) $context['section'] = $bookItem['section'];
        if (!empty($bookItem['subject_name'])) $context['subject_name'] = $bookItem['subject_name'];
        if (!empty($bookItem['course_code'])) $context['course_code'] = $bookItem['course_code'];
        
        // Only add if context has meaningful data
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

// Get parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'archived';
$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : 'category';

// Get data for the page using Book class methods
$archivedBooks = getArchivedBooksGrouped($pdo, $category_filter, $search, $groupBy);
$eligibleBooks = getBooksEligibleForArchivingGrouped($pdo, $groupBy);
$archiveStats = $book->getArchiveStats();

// Department configuration
$departments = [
    'BIT' => ['name' => 'Bachelor in Industrial Technology', 'color' => 'primary'],
    'EDUCATION' => ['name' => 'Education Department', 'color' => 'success'],
    'HBM' => ['name' => 'Hotel & Business Management', 'color' => 'info'],
    'COMPSTUD' => ['name' => 'Computer Studies', 'color' => 'warning']
];

// Group display options
$groupOptions = [
    'category' => ['name' => 'Department', 'icon' => 'fas fa-layer-group'],
    'year' => ['name' => 'Publication Year', 'icon' => 'fas fa-calendar-alt'],
    'author' => ['name' => 'Author (A-Z)', 'icon' => 'fas fa-user'],
    'archive_date' => ['name' => 'Archive Date', 'icon' => 'fas fa-clock']
];

$page_title = "Archives - LibCollect: Reference Mapping System";
include '../includes/header.php';
?>

<style>
/* Enhanced Book Card Styles (similar to books.php) */
.book-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
    height: 100%;
    border-radius: 12px;
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15) !important;
}

.book-cover {
    position: relative;
    background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #b8860b 100%);
    height: 180px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: white;
    overflow: hidden;
}

.book-cover::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #b8860b 100%);
}

.book-spine {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.9;
    z-index: 2;
    position: relative;
}

.book-info {
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    z-index: 3;
}

.book-id {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
    backdrop-filter: blur(10px);
}

.department-badge {
    font-size: 0.7rem;
    padding: 4px 8px;
    backdrop-filter: blur(10px);
}

.total-copies-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(40, 167, 69, 0.9);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    z-index: 3;
    backdrop-filter: blur(10px);
    font-weight: bold;
}

.publication-year-badge {
    position: absolute;
    top: 70px;
    right: 8px;
    background: rgba(13, 110, 253, 0.9);
    color: white;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    z-index: 3;
    backdrop-filter: blur(10px);
}

.merged-indicator {
    position: absolute;
    top: 40px;
    right: 8px;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    z-index: 3;
}

.archive-badge-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(108, 117, 125, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 4;
    backdrop-filter: blur(10px);
}

.eligible-badge-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 193, 7, 0.9);
    color: #000;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 4;
    backdrop-filter: blur(10px);
}

.eligible-cover {
    background: linear-gradient(145deg, #f39c12 0%, #d35400 100%) !important;
}

.book-details {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    height: calc(100% - 180px);
}

.book-title {
    font-weight: 600;
    line-height: 1.3;
    height: auto;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: 1rem;
    margin-bottom: 0.75rem;
}

.book-author, .book-isbn {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.book-year {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.academic-contexts {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    max-height: 120px;
    overflow-y: auto;
    flex-grow: 1;
}

.academic-contexts::-webkit-scrollbar {
    width: 4px;
}

.academic-contexts::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.academic-contexts::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 2px;
}

.context-group {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.context-group:last-child {
    margin-bottom: 0;
}

.context-group .badge {
    font-size: 0.65rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

.context-header {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
}

.archive-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
}

.book-actions {
    margin-top: auto;
}

.book-actions .btn-group {
    width: 100%;
}

.book-actions .dropdown-menu {
    width: 100%;
}

/* Different gradients for different departments */
.book-card[data-department="BIT"] .book-cover {
    background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
}

.book-card[data-department="EDUCATION"] .book-cover {
    background: linear-gradient(145deg, #11998e 0%, #38ef7d 100%);
}

.book-card[data-department="HBM"] .book-cover {
    background: linear-gradient(145deg, #3498db 0%, #2980b9 100%);
}

.book-card[data-department="COMPSTUD"] .book-cover {
    background: linear-gradient(145deg, #f39c12 0%, #d35400 100%);
}

/* Archive-specific overrides */
.archive-card .book-cover {
    filter: grayscale(20%);
}

.eligible-card .book-cover {
    filter: saturate(1.2);
}

.stats-card {
    background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #b8860b 100%);
    color: white;
    border: none;
}

.tab-content {
    min-height: 400px;
}

.book-age {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
}

.age-ancient { background-color: #dc3545; color: white; }
.age-very-old { background-color: #fd7e14; color: white; }
.age-old { background-color: #ffc107; color: black; }

.group-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #007bff;
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.group-collapse {
    cursor: pointer;
    transition: all 0.3s ease;
}

.group-collapse:hover {
    background-color: rgba(0,123,255,0.1);
}

.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

.compact-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    background: white;
}

.compact-card:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0,123,255,0.15);
}

.view-toggle {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.25rem;
}
</style>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-archive text-secondary me-2"></i>
                Archives Management
            </h1>
            <p class="mb-0">Manage archived books and automatic archiving of books older than 5 years</p>
        </div>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="auto_archive">
            <button type="submit" class="btn btn-warning" onclick="return confirm('This will automatically archive all books published 5+ years ago. Continue?')">
                <i class="fas fa-history me-1"></i>Auto Archive Old Books
            </button>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-archive fa-2x mb-2" style="color: black;"></i>
                <h3 style="color: black;"><?php echo $archiveStats['total_archived']; ?></h3>
                <p class="mb-0" style="color: black;">Archived Books</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2 text-warning"></i>
                <h3><?php echo $archiveStats['eligible_for_archive']; ?></h3>
                <p class="mb-0">Eligible for Archive</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-2 text-info"></i>
                <h3><?php echo date('Y') - 5; ?></h3>
                <p class="mb-0">Cutoff Year</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-undo fa-2x mb-2 text-success"></i>
                <h3>∞</h3>
                <p class="mb-0">Restorable</p>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'archived' ? 'active' : ''; ?>" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived" type="button" role="tab">
            <i class="fas fa-archive me-1"></i>Archived Books (<?php echo array_sum(array_map('count', $archivedBooks)); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'eligible' ? 'active' : ''; ?>" id="eligible-tab" data-bs-toggle="tab" data-bs-target="#eligible" type="button" role="tab">
            <i class="fas fa-clock me-1"></i>Eligible for Archive (<?php echo array_sum(array_map('count', $eligibleBooks)); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'stats' ? 'active' : ''; ?>" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
            <i class="fas fa-chart-bar me-1"></i>Statistics
        </button>
    </li>
</ul>

<div class="tab-content" id="archiveTabContent">
    <!-- Archived Books Tab -->
    <div class="tab-pane fade <?php echo $tab === 'archived' ? 'show active' : ''; ?>" id="archived" role="tabpanel">
        <!-- Search, Filter and Group Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <input type="hidden" name="tab" value="archived">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Search archived books</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by title, author, ISBN, or year..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="category">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept => $info): ?>
                                <option value="<?php echo $dept; ?>" <?php echo ($category_filter == $dept) ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Group by</label>
                        <select class="form-control" name="group_by">
                            <?php foreach ($groupOptions as $key => $option): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($groupBy == $key) ? 'selected' : ''; ?>>
                                    <?php echo $option['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Apply
                        </button>
                    </div>
                    <div class="col-md-1 mb-2">
                        <a href="archives.php?tab=archived" class="btn btn-outline-secondary w-100" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="button" class="btn btn-outline-info w-100" onclick="toggleAllGroups()" title="Expand/Collapse All">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grouped Archived Books Display -->
        <?php if (empty($archivedBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No archived books found</h4>
                <p class="text-muted">Books older than 5 years will appear here when archived.</p>
            </div>
        <?php else: ?>
            <?php foreach ($archivedBooks as $groupKey => $books): ?>
                <div class="mb-4">
                    <div class="group-header card">
                        <div class="card-body py-3 group-collapse" data-bs-toggle="collapse" data-bs-target="#archived-group-<?php echo md5($groupKey); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="<?php echo $groupOptions[$groupBy]['icon']; ?> me-2"></i>
                                    <?php 
                                    if ($groupBy === 'archive_date') {
                                        echo date('F j, Y', strtotime($groupKey));
                                    } elseif ($groupBy === 'category' && isset($departments[$groupKey])) {
                                        echo $departments[$groupKey]['name'];
                                    } else {
                                        echo htmlspecialchars($groupKey);
                                    }
                                    ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($books); ?> books</span>
                                </h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="archived-group-<?php echo md5($groupKey); ?>">
                        <div class="book-grid">
                            <?php foreach ($books as $book): ?>
                                <div class="book-card archive-card" data-department="<?php echo htmlspecialchars(getCategoryDisplayInfo($book['category'], $departments)['primary']); ?>">
                                    <!-- Enhanced Book Cover (similar to books.php) -->
                                    <div class="book-cover">
                                        <div class="book-spine">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        
                                        <div class="book-info">
                                            <div class="book-id">#<?php echo $book['id']; ?></div>
                                            <?php if (!$category_filter): ?>
                                                <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                                <span class="badge bg-<?php echo $categoryInfo['color']; ?> department-badge" 
                                                    title="<?php echo htmlspecialchars($book['category']); ?>">
                                                    <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Merged book indicator -->
                                        <?php if (count($book['record_ids']) > 1): ?>
                                            <div class="merged-indicator">
                                                <i class="fas fa-layer-group me-1"></i><?php echo count($book['record_ids']); ?> Records
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Publication year badge -->
                                        <?php if (!empty($book['publication_year'])): ?>
                                            <div class="publication-year-badge">
                                                <i class="fas fa-calendar me-1"></i><?php echo $book['publication_year']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Total copies badge -->
                                        <div class="total-copies-badge">
                                            <i class="fas fa-copy me-1"></i><?php echo $book['total_quantity']; ?>
                                        </div>
                                        
                                        <!-- Archive badge -->
                                        <div class="archive-badge-overlay">
                                            ARCHIVED
                                        </div>
                                    </div>
                                    
                                    <!-- Enhanced Book Details -->
                                    <div class="book-details">
                                        <h6 class="book-title" title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </h6>
                                        
                                        <p class="book-author">
                                            <i class="fas fa-user me-1 text-muted"></i>
                                            <span class="text-dark"><?php echo htmlspecialchars($book['author']); ?></span>
                                        </p>
                                        
                                        <?php if (!empty($book['isbn'])): ?>
                                            <p class="book-isbn">
                                                <i class="fas fa-barcode me-1 text-muted"></i>
                                                <span class="text-muted"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($book['publication_year'])): ?>
                                            <?php 
                                            $age = date('Y') - $book['publication_year'];
                                            $ageClass = $age >= 50 ? 'age-ancient' : ($age >= 30 ? 'age-very-old' : 'age-old');
                                            ?>
                                            <p class="book-year">
                                                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                                <span class="text-muted">Published: <?php echo $book['publication_year']; ?></span>
                                                <span class="book-age <?php echo $ageClass; ?> ms-2"><?php echo $age; ?>y</span>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Academic Contexts - Grouped Display (like books.php) -->
                                        <?php if (!empty($book['academic_contexts'])): ?>
                                            <div class="academic-contexts">
                                                <div class="context-header">
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    Academic Uses (<?php echo count($book['academic_contexts']); ?>)
                                                </div>
                                                
                                                <?php 
                                                // Group contexts by department
                                                $contextsByDept = [];
                                                foreach ($book['academic_contexts'] as $context) {
                                                    $dept = $context['category'] ?? 'General';
                                                    if (!isset($contextsByDept[$dept])) {
                                                        $contextsByDept[$dept] = [];
                                                    }
                                                    $contextsByDept[$dept][] = $context;
                                                }
                                                ?>
                                                
                                                <?php foreach ($contextsByDept as $dept => $contexts): ?>
                                                    <div class="context-group">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <?php 
                                                            $deptInfo = getCategoryDisplayInfo($dept, $departments);
                                                            $deptColor = $deptInfo['color'];
                                                            ?>
                                                            <strong class="text-<?php echo $deptColor; ?> me-2">
                                                                <?php echo htmlspecialchars($deptInfo['display']); ?>
                                                            </strong>
                                                            <small class="text-muted">(<?php echo count($contexts); ?> context<?php echo count($contexts) > 1 ? 's' : ''; ?>)</small>
                                                        </div>
                                                        
                                                        <?php 
                                                        // Aggregate unique values for this department
                                                        $yearLevels = array_unique(array_filter(array_column($contexts, 'year_level')));
                                                        $semesters = array_unique(array_filter(array_column($contexts, 'semester')));
                                                        $sections = array_unique(array_filter(array_column($contexts, 'section')));
                                                        $subjects = array_unique(array_filter(array_column($contexts, 'subject_name')));
                                                        $courseCodes = array_unique(array_filter(array_column($contexts, 'course_code')));
                                                        ?>
                                                        
                                                        <div>
                                                            <?php if (!empty($yearLevels)): ?>
                                                                <?php foreach ($yearLevels as $yearLevel): ?>
                                                                    <span class="badge bg-success"><?php echo htmlspecialchars($yearLevel); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($semesters)): ?>
                                                                <?php foreach ($semesters as $semester): ?>
                                                                    <span class="badge bg-info"><?php echo htmlspecialchars($semester); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($sections)): ?>
                                                                <?php foreach ($sections as $section): ?>
                                                                    <span class="badge bg-warning text-dark">Sec <?php echo htmlspecialchars($section); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($subjects)): ?>
                                                                <?php foreach ($subjects as $subject): ?>
                                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($subject); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($courseCodes)): ?>
                                                                <?php foreach ($courseCodes as $courseCode): ?>
                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($courseCode); ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Archive Information -->
                                        <div class="archive-info">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                                <span class="badge bg-<?php echo $categoryInfo['color']; ?> me-1" 
                                                    title="<?php echo htmlspecialchars($book['category']); ?>">
                                                    <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                                </span>
                                                <span class="badge bg-secondary">Qty: <?php echo $book['quantity']; ?></span>
                                            </div>
                                            <small class="text-muted">
                                                Reason: <?php echo implode(', ', array_unique($book['archive_reasons'])); ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Enhanced Action Buttons with Dropdown for Multiple Records -->
                                        <div class="book-actions">
                                            <?php if (count($book['record_ids']) > 1): ?>
                                                <!-- Multiple records - show dropdown -->
                                                <div class="btn-group w-100" role="group">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="confirmRestoreAll([<?php echo implode(',', $book['record_ids']); ?>], '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-undo me-1"></i>Restore All
                                                    </button>
                                                    <button type="button" class="btn btn-success btn-sm dropdown-toggle dropdown-toggle-split" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><h6 class="dropdown-header">Manage Records</h6></li>
                                                        <?php foreach ($book['record_ids'] as $index => $recordId): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="restoreBook(<?php echo $recordId; ?>)">
                                                                    <i class="fas fa-undo me-2"></i>Restore Record #<?php echo $recordId; ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" 
                                                               onclick="confirmDeleteAll([<?php echo implode(',', $book['record_ids']); ?>], '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                                                <i class="fas fa-trash me-2"></i>Delete All Permanently
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <!-- Single record - regular buttons -->
                                                <div class="d-grid gap-1">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="restoreBook(<?php echo $book['id']; ?>)">
                                                        <i class="fas fa-undo me-1"></i>Restore
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>', permanentDeleteBook)">
                                                        <i class="fas fa-trash me-1"></i>Delete Permanently
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Eligible for Archive Tab -->
    <div class="tab-pane fade <?php echo $tab === 'eligible' ? 'show active' : ''; ?>" id="eligible" role="tabpanel">
        <!-- Group Controls for Eligible Books -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <input type="hidden" name="tab" value="eligible">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Group by</label>
                        <select class="form-control" name="group_by">
                            <?php foreach ($groupOptions as $key => $option): ?>
                                <?php if ($key !== 'archive_date'): // Archive date not relevant for eligible books ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($groupBy == $key) ? 'selected' : ''; ?>>
                                        <?php echo $option['name']; ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-layer-group me-1"></i>Group
                        </button>
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="button" class="btn btn-outline-info w-100" onclick="toggleAllGroups()" title="Expand/Collapse All">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                    </div>
                    <div class="col-md-6 mb-2 text-end">
                        <?php if (!empty($eligibleBooks)): ?>
                            <div class="alert alert-warning d-inline-block mb-0 me-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong><?php echo array_sum(array_map('count', $eligibleBooks)); ?> books</strong> eligible for archiving
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($eligibleBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h4 class="text-muted">No books eligible for archiving</h4>
                <p class="text-muted">Books published before <?php echo date('Y') - 10; ?> will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($eligibleBooks as $groupKey => $books): ?>
                <div class="mb-4">
                    <div class="group-header card border-warning">
                        <div class="card-body py-3 group-collapse" data-bs-toggle="collapse" data-bs-target="#eligible-group-<?php echo md5($groupKey); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="<?php echo $groupOptions[$groupBy]['icon']; ?> me-2"></i>
                                    <?php 
                                    if ($groupBy === 'category' && isset($departments[$groupKey])) {
                                        echo $departments[$groupKey]['name'];
                                    } else {
                                        echo htmlspecialchars($groupKey);
                                    }
                                    ?>
                                    <span class="badge bg-warning text-dark ms-2"><?php echo count($books); ?> books</span>
                                </h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="eligible-group-<?php echo md5($groupKey); ?>">
                        <div class="book-grid">
                            <?php foreach ($books as $book): ?>
                                <div class="compact-card eligible-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1 flex-grow-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                        <span class="badge bg-warning text-dark ms-2">ELIGIBLE</span>
                                    </div>
                                    
                                    <p class="text-muted mb-1 small">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($book['author']); ?>
                                    </p>
                                    
                                    <?php if ($book['publication_year']): ?>
                                        <?php 
                                        $age = date('Y') - $book['publication_year'];
                                        $ageClass = $age >= 50 ? 'age-ancient' : ($age >= 30 ? 'age-very-old' : 'age-old');
                                        ?>
                                        <p class="text-muted mb-2 small">
                                            <i class="fas fa-calendar-alt me-1"></i><?php echo $book['publication_year']; ?>
                                            <span class="book-age <?php echo $ageClass; ?> ms-2"><?php echo $age; ?>y</span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-<?php echo $departments[$book['category']]['color']; ?> me-1">
                                            <?php echo $book['category']; ?>
                                        </span>
                                        <span class="badge bg-secondary">Qty: <?php echo $book['quantity']; ?></span>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="manual_archive">
                                            <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning w-100" 
                                                    onclick="return confirm('Archive this book now?')">
                                                <i class="fas fa-archive me-1"></i>Archive Now
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Statistics Tab -->
    <div class="tab-pane fade <?php echo $tab === 'stats' ? 'show active' : ''; ?>" id="stats" role="tabpanel">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Archives by Department</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archiveStats['by_category'])): ?>
                            <p class="text-muted">No archived books yet.</p>
                        <?php else: ?>
                            <?php foreach ($archiveStats['by_category'] as $stat): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-<?php echo $departments[$stat['category']]['color']; ?> me-2">
                                        <?php echo $stat['category']; ?>
                                    </span>
                                    <span><?php echo $stat['count']; ?> books</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Archives by Year</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archiveStats['by_year'])): ?>
                            <p class="text-muted">No archiving activity yet.</p>
                        <?php else: ?>
                            <?php foreach ($archiveStats['by_year'] as $stat): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo $stat['year']; ?></span>
                                    <span class="badge bg-secondary"><?php echo $stat['count']; ?> books</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Archive Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Automatic Archiving</h6>
                                <ul class="small text-muted">
                                    <li>Books published 10+ years ago are eligible</li>
                                    <li>Archiving preserves all book data</li>
                                    <li>Archived books can be restored anytime</li>
                                    <li>Archive history is maintained</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Archive Benefits</h6>
                                <ul class="small text-muted">
                                    <li>Reduces main database size</li>
                                    <li>Improves query performance</li>
                                    <li>Maintains historical records</li>
                                    <li>Allows for data restoration</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Management Options</h6>
                                <ul class="small text-muted">
                                    <li>Manual archiving available</li>
                                    <li>Bulk auto-archive function</li>
                                    <li>Individual book restoration</li>
                                    <li>Permanent deletion option</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-lightbulb me-2"></i>Archive Policy</h6>
                            <p class="mb-0">
                                Books are automatically eligible for archiving after 10 years from their publication date. 
                                This helps maintain an efficient library database while preserving historical records. 
                                Archived books remain searchable and can be restored to active status at any time.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab navigation with URL parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'archived';
    
    // Activate the correct tab
    const tabElement = document.getElementById(activeTab + '-tab');
    if (tabElement) {
        const tab = new bootstrap.Tab(tabElement);
        tab.show();
    }
    
    // Update URL when tab changes
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', newUrl);
        });
    });
    
    // Auto-refresh notification for eligible books
    const eligibleCount = <?php echo array_sum(array_map('count', $eligibleBooks)); ?>;
    if (eligibleCount > 0) {
        showArchiveNotification(eligibleCount);
    }
    
    // Initialize collapse icons
    updateCollapseIcons();
});

// Group management functions
function toggleAllGroups() {
    const collapseElements = document.querySelectorAll('[id^="archived-group-"], [id^="eligible-group-"]');
    const allExpanded = Array.from(collapseElements).every(el => el.classList.contains('show'));
    
    collapseElements.forEach(el => {
        if (allExpanded) {
            bootstrap.Collapse.getOrCreateInstance(el).hide();
        } else {
            bootstrap.Collapse.getOrCreateInstance(el).show();
        }
    });
    
    setTimeout(updateCollapseIcons, 300);
}

// Update collapse icons
function updateCollapseIcons() {
    document.querySelectorAll('.group-collapse').forEach(header => {
        const target = header.getAttribute('data-bs-target');
        const collapseElement = document.querySelector(target);
        const icon = header.querySelector('.fas');
        
        if (collapseElement && icon) {
            if (collapseElement.classList.contains('show')) {
                icon.className = 'fas fa-chevron-up';
            } else {
                icon.className = 'fas fa-chevron-down';
            }
        }
    });
}

// Listen for collapse events
document.addEventListener('shown.bs.collapse', updateCollapseIcons);
document.addEventListener('hidden.bs.collapse', updateCollapseIcons);

// Show notification for books eligible for archiving
function showArchiveNotification(count) {
    if (count > 10) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 350px;';
        notification.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>${count} books</strong> are eligible for archiving!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }
}

// Enhanced JavaScript functions for book management
function confirmDelete(id, title, callback) {
    if (confirm(`Are you sure you want to permanently delete "${title}"?\n\nThis action cannot be undone.`)) {
        callback(id);
    }
}

function confirmDeleteAll(ids, title) {
    if (confirm(`Are you sure you want to permanently delete all ${ids.length} records of "${title}"?\n\nThis action cannot be undone.`)) {
        deleteMultipleBooks(ids);
    }
}

function confirmRestoreAll(ids, title) {
    if (confirm(`Are you sure you want to restore all ${ids.length} records of "${title}" to the active collection?`)) {
        restoreMultipleBooks(ids);
    }
}

function confirmArchiveAll(ids, title) {
    if (confirm(`Are you sure you want to archive all ${ids.length} records of "${title}"?`)) {
        archiveMultipleBooks(ids);
    }
}

function restoreBook(id) {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function permanentDeleteBook(id) {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="permanent_delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function archiveBook(id) {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="manual_archive">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function restoreMultipleBooks(ids) {
    // For now, restore them one by one
    // In a real implementation, you might want to add a bulk restore action
    ids.forEach((id, index) => {
        setTimeout(() => {
            restoreBook(id);
        }, index * 100);
    });
}

function deleteMultipleBooks(ids) {
    // For now, delete them one by one
    // In a real implementation, you might want to add a bulk delete action
    ids.forEach((id, index) => {
        setTimeout(() => {
            permanentDeleteBook(id);
        }, index * 100);
    });
}

function archiveMultipleBooks(ids) {
    // For now, archive them one by one
    // In a real implementation, you might want to add a bulk archive action
    ids.forEach((id, index) => {
        setTimeout(() => {
            archiveBook(id);
        }, index * 100);
    });
}

// Auto-archive progress tracking
function trackArchiveProgress() {
    const progressBar = document.createElement('div');
    progressBar.className = 'progress position-fixed';
    progressBar.style.cssText = 'top: 0; left: 0; right: 0; height: 4px; z-index: 1060;';
    progressBar.innerHTML = '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>';
    document.body.appendChild(progressBar);
    
    let width = 0;
    const interval = setInterval(() => {
        width += Math.random() * 15;
        if (width >= 100) {
            width = 100;
            clearInterval(interval);
            setTimeout(() => progressBar.remove(), 500);
        }
        progressBar.querySelector('.progress-bar').style.width = width + '%';
    }, 100);
}

// Search functionality enhancement
function enhanceSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Auto-submit search after 500ms of no typing
                if (this.value.length > 2 || this.value.length === 0) {
                    this.form.submit();
                }
            }, 500);
        });
    }
}

// Initialize enhancements
document.addEventListener('DOMContentLoaded', function() {
    enhanceSearch();
    
    // Add loading states to buttons
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + 
                    (submitBtn.textContent.includes('Auto Archive') ? 'Archiving...' : 'Processing...');
                
                if (submitBtn.textContent.includes('Auto Archive')) {
                    trackArchiveProgress();
                }
                
                // Re-enable button after 3 seconds (in case of page reload failure)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 3000);
            }
        });
    });
    
    // Add tooltips for academic contexts
    const contexts = document.querySelectorAll('.academic-contexts');
    contexts.forEach(context => {
        if (context.scrollHeight > context.clientHeight) {
            context.setAttribute('title', 'Scroll to see more academic contexts');
        }
    });
    
    // Add stagger animation to book cards
    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
    
    // Add smooth scrolling to group headers
    document.querySelectorAll('.group-collapse').forEach(header => {
        header.addEventListener('click', function() {
            setTimeout(() => {
                this.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        });
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Add CSS animation for fade-in effect
const style = document.createElement('style');
style.textContent = `
    .fade-in {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Export archive data
function exportArchiveData() {
    const data = {
        archived_books: <?php echo json_encode($archivedBooks); ?>,
        eligible_books: <?php echo json_encode($eligibleBooks); ?>,
        stats: <?php echo json_encode($archiveStats); ?>,
        export_date: new Date().toISOString()
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'archive_data_' + new Date().toISOString().slice(0, 10) + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                document.getElementById('archived-tab').click();
                break;
            case '2':
                e.preventDefault();
                document.getElementById('eligible-tab').click();
                break;
            case '3':
                e.preventDefault();
                document.getElementById('stats-tab').click();
                break;
            case 'e':
                e.preventDefault();
                toggleAllGroups();
                break;
        }
    }
});

// Group filtering and sorting
function filterGroups(searchTerm) {
    const groups = document.querySelectorAll('[id^="archived-group-"], [id^="eligible-group-"]');
    groups.forEach(group => {
        const header = document.querySelector(`[data-bs-target="#${group.id}"]`);
        if (header) {
            const groupTitle = header.textContent.toLowerCase();
            const shouldShow = groupTitle.includes(searchTerm.toLowerCase());
            header.closest('.mb-4').style.display = shouldShow ? 'block' : 'none';
        }
    });
}

// Bulk operations
function selectAllInGroup(groupId) {
    const checkboxes = document.querySelectorAll(`#${groupId} input[type="checkbox"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const checkedBoxes = document.querySelectorAll('input[type="checkbox"]:checked');
    const bulkActionsContainer = document.getElementById('bulk-actions');
    
    if (bulkActionsContainer) {
        bulkActionsContainer.style.display = checkedBoxes.length > 0 ? 'block' : 'none';
        document.getElementById('selected-count').textContent = checkedBoxes.length;
    }
}

// Print functionality
function printArchiveReport() {
    const printContent = document.querySelector('.tab-pane.active').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Archive Report - ${new Date().toLocaleDateString()}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .btn, .form-control, .card-header { display: none !important; }
                    .compact-card { border: 1px solid #000; margin-bottom: 10px; }
                    body { font-size: 12px; }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <h1>Archive Report - ${new Date().toLocaleDateString()}</h1>
                ${printContent.innerHTML}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Auto-save user preferences
function saveUserPreferences() {
    const preferences = {
        groupBy: document.querySelector('select[name="group_by"]')?.value || 'category',
        expandedGroups: Array.from(document.querySelectorAll('.collapse.show')).map(el => el.id)
    };
    
    localStorage.setItem('archivePreferences', JSON.stringify(preferences));
}

function loadUserPreferences() {
    const preferences = JSON.parse(localStorage.getItem('archivePreferences') || '{}');
    
    // Restore group by selection
    if (preferences.groupBy) {
        const groupBySelect = document.querySelector('select[name="group_by"]');
        if (groupBySelect) {
            groupBySelect.value = preferences.groupBy;
        }
    }
    
    // Restore expanded groups
    if (preferences.expandedGroups) {
        setTimeout(() => {
            preferences.expandedGroups.forEach(groupId => {
                const element = document.getElementById(groupId);
                if (element && !element.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(element).show();
                }
            });
        }, 100);
    }
}

// Save preferences on page unload
window.addEventListener('beforeunload', saveUserPreferences);

// Load preferences on page load
document.addEventListener('DOMContentLoaded', loadUserPreferences);
</script>

<?php include '../includes/footer.php'; ?>
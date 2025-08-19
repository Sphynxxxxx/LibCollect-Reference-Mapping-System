<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'auto_archive':
                $archivedCount = autoArchiveOldBooks($pdo);
                $_SESSION['message'] = "Successfully archived {$archivedCount} books older than 10 years.";
                $_SESSION['message_type'] = 'success';
                break;
                
            case 'restore':
                if (restoreBook($pdo, $_POST['id'])) {
                    $_SESSION['message'] = 'Book restored successfully!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to restore book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'permanent_delete':
                if (permanentDeleteBook($pdo, $_POST['id'])) {
                    $_SESSION['message'] = 'Book permanently deleted!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'manual_archive':
                if (manualArchiveBook($pdo, $_POST['id'])) {
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

// Functions for archive management
function autoArchiveOldBooks($pdo) {
    try {
        // Create archived_books table if it doesn't exist
        createArchivedBooksTable($pdo);
        
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 10;
        
        $pdo->beginTransaction();
        
        // Move books older than 10 years to archive
        $sql = "INSERT INTO archived_books (original_id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year, 
                book_copy_number, total_quantity, is_multi_record, same_book_series, 
                original_created_at, original_updated_at, archived_at, archive_reason)
                SELECT id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_record, same_book_series,
                created_at, updated_at, NOW(), 'Automatic archiving - 10+ years old'
                FROM books 
                WHERE publication_year IS NOT NULL AND publication_year <= ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffYear]);
        $archivedCount = $stmt->rowCount();
        
        // Delete the archived books from main table
        if ($archivedCount > 0) {
            $deleteSql = "DELETE FROM books WHERE publication_year IS NOT NULL AND publication_year <= ?";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([$cutoffYear]);
        }
        
        $pdo->commit();
        
        // Log the activity
        $book = new Book($pdo);
        $book->logCustomActivity('archive', "Auto-archived {$archivedCount} books older than 10 years");
        
        return $archivedCount;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Auto archive failed: " . $e->getMessage());
        return 0;
    }
}

function createArchivedBooksTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS archived_books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        isbn VARCHAR(50) DEFAULT NULL,
        category VARCHAR(100) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        description TEXT DEFAULT NULL,
        subject_name VARCHAR(255) DEFAULT NULL,
        semester VARCHAR(50) DEFAULT NULL,
        section VARCHAR(50) DEFAULT NULL,
        year_level VARCHAR(50) DEFAULT NULL,
        course_code VARCHAR(100) DEFAULT NULL,
        publication_year INT(4) DEFAULT NULL,
        book_copy_number INT DEFAULT NULL,
        total_quantity INT DEFAULT NULL,
        is_multi_record TINYINT(1) DEFAULT 0,
        same_book_series TINYINT(1) DEFAULT 0,
        original_created_at TIMESTAMP NULL,
        original_updated_at TIMESTAMP NULL,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        archive_reason VARCHAR(255) DEFAULT 'Manual archiving',
        archived_by VARCHAR(100) DEFAULT 'System',
        INDEX idx_original_id (original_id),
        INDEX idx_category (category),
        INDEX idx_publication_year (publication_year),
        INDEX idx_archived_at (archived_at)
    )";
    $pdo->exec($sql);
}

function getArchivedBooks($pdo, $category = '', $search = '') {
    try {
        $query = "SELECT * FROM archived_books WHERE 1=1";
        $params = [];
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($search) {
            $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR publication_year LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $query .= " ORDER BY archived_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting archived books: " . $e->getMessage());
        return [];
    }
}

function getBooksEligibleForArchiving($pdo) {
    try {
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 10;
        
        $sql = "SELECT * FROM books 
                WHERE publication_year IS NOT NULL AND publication_year <= ? 
                ORDER BY publication_year ASC, title ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting books eligible for archiving: " . $e->getMessage());
        return [];
    }
}

function restoreBook($pdo, $archiveId) {
    try {
        $pdo->beginTransaction();
        
        // Get archived book details
        $stmt = $pdo->prepare("SELECT * FROM archived_books WHERE id = ?");
        $stmt->execute([$archiveId]);
        $archivedBook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$archivedBook) {
            throw new Exception("Archived book not found");
        }
        
        // Insert back into main books table
        $sql = "INSERT INTO books (title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_record, same_book_series, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $archivedBook['title'],
            $archivedBook['author'],
            $archivedBook['isbn'],
            $archivedBook['category'],
            $archivedBook['quantity'],
            $archivedBook['description'],
            $archivedBook['subject_name'],
            $archivedBook['semester'],
            $archivedBook['section'],
            $archivedBook['year_level'],
            $archivedBook['course_code'],
            $archivedBook['publication_year'],
            $archivedBook['book_copy_number'],
            $archivedBook['total_quantity'],
            $archivedBook['is_multi_record'],
            $archivedBook['same_book_series'],
            $archivedBook['original_created_at']
        ]);
        
        // Remove from archive
        $stmt = $pdo->prepare("DELETE FROM archived_books WHERE id = ?");
        $stmt->execute([$archiveId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Restore book failed: " . $e->getMessage());
        return false;
    }
}

function permanentDeleteBook($pdo, $archiveId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM archived_books WHERE id = ?");
        return $stmt->execute([$archiveId]);
    } catch (PDOException $e) {
        error_log("Permanent delete failed: " . $e->getMessage());
        return false;
    }
}

function manualArchiveBook($pdo, $bookId) {
    try {
        createArchivedBooksTable($pdo);
        
        $pdo->beginTransaction();
        
        // Get book details
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book) {
            throw new Exception("Book not found");
        }
        
        // Insert into archive
        $sql = "INSERT INTO archived_books (original_id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_record, same_book_series,
                original_created_at, original_updated_at, archived_at, archive_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Manual archiving')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $book['id'], $book['title'], $book['author'], $book['isbn'], $book['category'],
            $book['quantity'], $book['description'], $book['subject_name'], $book['semester'],
            $book['section'], $book['year_level'], $book['course_code'], $book['publication_year'],
            $book['book_copy_number'], $book['total_quantity'], $book['is_multi_record'],
            $book['same_book_series'], $book['created_at'], $book['updated_at']
        ]);
        
        // Delete from main table
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$bookId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Manual archive failed: " . $e->getMessage());
        return false;
    }
}

function getArchiveStats($pdo) {
    try {
        $stats = [];
        
        // Total archived books
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM archived_books");
        $stats['total_archived'] = $stmt->fetch()['total'] ?? 0;
        
        // Books by category
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM archived_books GROUP BY category");
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Books eligible for archiving
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 10;
        $stmt = $pdo->prepare("SELECT COUNT(*) as eligible FROM books WHERE publication_year IS NOT NULL AND publication_year <= ?");
        $stmt->execute([$cutoffYear]);
        $stats['eligible_for_archive'] = $stmt->fetch()['eligible'] ?? 0;
        
        // Archive by year
        $stmt = $pdo->query("SELECT YEAR(archived_at) as year, COUNT(*) as count FROM archived_books GROUP BY YEAR(archived_at) ORDER BY year DESC LIMIT 5");
        $stats['by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting archive stats: " . $e->getMessage());
        return [
            'total_archived' => 0,
            'by_category' => [],
            'eligible_for_archive' => 0,
            'by_year' => []
        ];
    }
}

// Get data for the page
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'archived';

$archivedBooks = getArchivedBooks($pdo, $category_filter, $search);
$eligibleBooks = getBooksEligibleForArchiving($pdo);
$archiveStats = getArchiveStats($pdo);

// Department configuration
$departments = [
    'BIT' => ['name' => 'Bachelor in Industrial Technology', 'color' => 'primary'],
    'EDUCATION' => ['name' => 'Education Department', 'color' => 'success'],
    'HBM' => ['name' => 'Hotel & Business Management', 'color' => 'info'],
    'COMPSTUD' => ['name' => 'Computer Studies', 'color' => 'warning']
];

$page_title = "Archives - ISAT U Library Miagao Campus";
include '../includes/header.php';
?>

<style>
.archive-card {
    transition: all 0.3s ease;
    border-left: 4px solid #6c757d;
}

.archive-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.archive-badge {
    background: linear-gradient(45deg, #6c757d, #5a6268);
    color: white;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
}

.eligible-card {
    border-left: 4px solid #ffc107;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            <p class="mb-0">Manage archived books and automatic archiving of books older than 10 years</p>
        </div>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="auto_archive">
            <button type="submit" class="btn btn-warning" onclick="return confirm('This will automatically archive all books published 10+ years ago. Continue?')">
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
                <i class="fas fa-archive fa-2x mb-2"></i>
                <h3><?php echo $archiveStats['total_archived']; ?></h3>
                <p class="mb-0">Archived Books</p>
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
                <h3><?php echo date('Y') - 10; ?></h3>
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
            <i class="fas fa-archive me-1"></i>Archived Books (<?php echo count($archivedBooks); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'eligible' ? 'active' : ''; ?>" id="eligible-tab" data-bs-toggle="tab" data-bs-target="#eligible" type="button" role="tab">
            <i class="fas fa-clock me-1"></i>Eligible for Archive (<?php echo count($eligibleBooks); ?>)
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
        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <input type="hidden" name="tab" value="archived">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Search archived books</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by title, author, ISBN, or year..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="category">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept => $info): ?>
                                <option value="<?php echo $dept; ?>" <?php echo ($category_filter == $dept) ? 'selected' : ''; ?>>
                                    <?php echo $info['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                    <div class="col-md-1 mb-2">
                        <a href="archives.php?tab=archived" class="btn btn-outline-secondary w-100" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Archived Books Display -->
        <?php if (empty($archivedBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No archived books found</h4>
                <p class="text-muted">Books older than 10 years will appear here when archived.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($archivedBooks as $book): ?>
                    <div class="col-lg-6">
                        <div class="card archive-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                    <span class="archive-badge">ARCHIVED</span>
                                </div>
                                
                                <p class="text-muted mb-1">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($book['author']); ?>
                                </p>
                                
                                <?php if ($book['publication_year']): ?>
                                    <?php 
                                    $age = date('Y') - $book['publication_year'];
                                    $ageClass = $age >= 50 ? 'age-ancient' : ($age >= 30 ? 'age-very-old' : 'age-old');
                                    ?>
                                    <p class="text-muted mb-1">
                                        <i class="fas fa-calendar-alt me-1"></i><?php echo $book['publication_year']; ?>
                                        <span class="book-age <?php echo $ageClass; ?> ms-2"><?php echo $age; ?> years old</span>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="text-muted mb-2">
                                    <span class="badge bg-<?php echo $departments[$book['category']]['color']; ?>">
                                        <?php echo $book['category']; ?>
                                    </span>
                                    <span class="badge bg-secondary ms-1">Qty: <?php echo $book['quantity']; ?></span>
                                </p>
                                
                                <small class="text-muted">
                                    Archived: <?php echo date('M j, Y', strtotime($book['archived_at'])); ?>
                                    <br>Reason: <?php echo htmlspecialchars($book['archive_reason']); ?>
                                </small>
                                
                                <div class="mt-3">
                                    <form method="POST" class="d-inline me-2">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" 
                                                onclick="return confirm('Restore this book to active collection?')">
                                            <i class="fas fa-undo me-1"></i>Restore
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="permanent_delete">
                                        <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Permanently delete this book? This cannot be undone!')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Eligible for Archive Tab -->
    <div class="tab-pane fade <?php echo $tab === 'eligible' ? 'show active' : ''; ?>" id="eligible" role="tabpanel">
        <?php if (empty($eligibleBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h4 class="text-muted">No books eligible for archiving</h4>
                <p class="text-muted">Books published before <?php echo date('Y') - 10; ?> will appear here.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo count($eligibleBooks); ?> books</strong> are eligible for automatic archiving (published before <?php echo date('Y') - 10; ?>).
            </div>
            
            <div class="row g-3">
                <?php foreach ($eligibleBooks as $book): ?>
                    <div class="col-lg-6">
                        <div class="card eligible-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                    <span class="badge bg-warning text-dark">ELIGIBLE</span>
                                </div>
                                
                                <p class="text-muted mb-1">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($book['author']); ?>
                                </p>
                                
                                <?php if ($book['publication_year']): ?>
                                    <?php 
                                    $age = date('Y') - $book['publication_year'];
                                    $ageClass = $age >= 50 ? 'age-ancient' : ($age >= 30 ? 'age-very-old' : 'age-old');
                                    ?>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar-alt me-1"></i><?php echo $book['publication_year']; ?>
                                        <span class="book-age <?php echo $ageClass; ?> ms-2"><?php echo $age; ?> years old</span>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="text-muted mb-2">
                                    <span class="badge bg-<?php echo $departments[$book['category']]['color']; ?>">
                                        <?php echo $book['category']; ?>
                                    </span>
                                    <span class="badge bg-secondary ms-1">Qty: <?php echo $book['quantity']; ?></span>
                                </p>
                                
                                <div class="mt-3">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="manual_archive">
                                        <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                onclick="return confirm('Archive this book now?')">
                                            <i class="fas fa-archive me-1"></i>Archive Now
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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

<!-- Confirmation Modals -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
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
    const eligibleCount = <?php echo $archiveStats['eligible_for_archive']; ?>;
    if (eligibleCount > 0) {
        showArchiveNotification(eligibleCount);
    }
});

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

// Enhanced confirmation for critical actions
function confirmAction(message, callback) {
    document.getElementById('confirmMessage').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    
    document.getElementById('confirmAction').onclick = function() {
        callback();
        modal.hide();
    };
    
    modal.show();
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + 
                    (submitBtn.textContent.includes('Auto Archive') ? 'Archiving...' : 'Processing...');
                
                if (submitBtn.textContent.includes('Auto Archive')) {
                    trackArchiveProgress();
                }
            }
        });
    });
    
    // Add tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

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
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
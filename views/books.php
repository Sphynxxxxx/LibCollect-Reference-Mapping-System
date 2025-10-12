<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if ($book->deleteBook($_POST['id'])) {
                    $_SESSION['message'] = 'Book deleted successfully!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'delete_multiple':
                $deleted_count = 0;
                if (isset($_POST['ids']) && is_array($_POST['ids'])) {
                    foreach ($_POST['ids'] as $id) {
                        if ($book->deleteBook($id)) {
                            $deleted_count++;
                        }
                    }
                }
                if ($deleted_count > 0) {
                    $_SESSION['message'] = "Successfully deleted {$deleted_count} book record(s)!";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete books!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'send_to_pending':
                // Move book to pending archives
                if (isset($_POST['id'])) {
                    // Get book data
                    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $bookData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($bookData) {
                        try {
                            // Insert into pending_archives
                            $sql = "INSERT INTO pending_archives (title, author, isbn, category, quantity, description, 
                                    subject_name, semester, section, year_level, course_code, publication_year, 
                                    book_copy_number, total_quantity, is_multi_context, same_book_series) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([
                                $bookData['title'],
                                $bookData['author'],
                                $bookData['isbn'],
                                $bookData['category'],
                                $bookData['quantity'],
                                $bookData['description'],
                                $bookData['subject_name'],
                                $bookData['semester'],
                                $bookData['section'],
                                $bookData['year_level'],
                                $bookData['course_code'],
                                $bookData['publication_year'],
                                $bookData['book_copy_number'] ?? 1,
                                $bookData['total_quantity'] ?? 1,
                                $bookData['is_multi_context'] ?? 0,
                                $bookData['same_book_series'] ?? 0
                            ]);
                            
                            if ($result) {
                                // Delete from books table
                                $book->deleteBook($_POST['id']);
                                
                                $_SESSION['message'] = 'Book sent to Pending Archives! Visit Archives page to select archive reason.';
                                $_SESSION['message_type'] = 'info';
                            } else {
                                $_SESSION['message'] = 'Failed to move book to pending archives!';
                                $_SESSION['message_type'] = 'danger';
                            }
                        } catch (PDOException $e) {
                            error_log("Error moving to pending archives: " . $e->getMessage());
                            $_SESSION['message'] = 'Failed to move book to pending archives!';
                            $_SESSION['message_type'] = 'danger';
                        }
                    }
                }
                break;
                
            case 'send_multiple_to_pending':
                // Move multiple books to pending archives
                $archived_count = 0;
                if (isset($_POST['ids']) && is_array($_POST['ids'])) {
                    foreach ($_POST['ids'] as $id) {
                        // Get book data
                        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                        $stmt->execute([$id]);
                        $bookData = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($bookData) {
                            try {
                                // Insert into pending_archives
                                $sql = "INSERT INTO pending_archives (title, author, isbn, category, quantity, description, 
                                        subject_name, semester, section, year_level, course_code, publication_year, 
                                        book_copy_number, total_quantity, is_multi_context, same_book_series) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                
                                $stmt = $pdo->prepare($sql);
                                $result = $stmt->execute([
                                    $bookData['title'],
                                    $bookData['author'],
                                    $bookData['isbn'],
                                    $bookData['category'],
                                    $bookData['quantity'],
                                    $bookData['description'],
                                    $bookData['subject_name'],
                                    $bookData['semester'],
                                    $bookData['section'],
                                    $bookData['year_level'],
                                    $bookData['course_code'],
                                    $bookData['publication_year'],
                                    $bookData['book_copy_number'] ?? 1,
                                    $bookData['total_quantity'] ?? 1,
                                    $bookData['is_multi_context'] ?? 0,
                                    $bookData['same_book_series'] ?? 0
                                ]);
                                
                                if ($result) {
                                    // Delete from books table
                                    $book->deleteBook($id);
                                    $archived_count++;
                                }
                            } catch (PDOException $e) {
                                error_log("Error moving to pending archives: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                if ($archived_count > 0) {
                    $_SESSION['message'] = "Successfully sent {$archived_count} book(s) to Pending Archives! Visit Archives page to select archive reasons.";
                    $_SESSION['message_type'] = 'info';
                } else {
                    $_SESSION['message'] = 'Failed to move books to pending archives!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
        }
        header('Location: books.php');
        exit;
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Department configuration
$departments = [
    'BIT' => [
        'name' => 'Bachelor in Industrial Technology',
        'color' => 'warning',
        'icon' => 'fas fa-industry',
        'description' => 'Manufacturing, Engineering, Technology'
    ],
    'EDUCATION' => [
        'name' => 'Education Department',
        'color' => 'primary', 
        'icon' => 'fas fa-graduation-cap',
        'description' => 'Teaching, Learning, Pedagogy'
    ],
    'HBM' => [
        'name' => 'Hotel & Business Management',
        'color' => 'danger',
        'icon' => 'fas fa-building',
        'description' => 'Business, Management, Hospitality'
    ],
    'COMPSTUD' => [
        'name' => 'Computer Studies',
        'color' => 'dark',
        'icon' => 'fas fa-desktop',
        'description' => 'Information Tech, Software Dev'
    ]
];

// Function to merge duplicate books
function mergeBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $book) {
        // Create a unique key based on title, author, ISBN, and publication year
        $key = md5(strtolower($book['title'] . '|' . $book['author'] . '|' . $book['isbn'] . '|' . ($book['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            // First occurrence of this book
            $mergedBooks[$key] = $book;
            $mergedBooks[$key]['total_quantity'] = $book['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$book['id']];
        } else {
            // Merge with existing book
            $mergedBooks[$key]['total_quantity'] += $book['quantity'];
            $mergedBooks[$key]['record_ids'][] = $book['id'];
        }
        
        // Add academic context
        $context = [];
        if (!empty($book['category'])) $context['category'] = $book['category'];
        if (!empty($book['year_level'])) $context['year_level'] = $book['year_level'];
        if (!empty($book['semester'])) $context['semester'] = $book['semester'];
        if (!empty($book['section'])) $context['section'] = $book['section'];
        if (!empty($book['subject_name'])) $context['subject_name'] = $book['subject_name'];
        if (!empty($book['course_code'])) $context['course_code'] = $book['course_code'];
        
        // Only add if context has meaningful data
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

// Get book counts for each department
$bookCounts = [];
foreach ($departments as $dept => $info) {
    $deptBooks = $book->getAllBooks($dept, '');
    $mergedDeptBooks = mergeBooks($deptBooks);
    $bookCounts[$dept] = count($mergedDeptBooks);
}

// Get books if a category is selected
$books = [];
if ($category_filter || $search) {
    $rawBooks = $book->getAllBooks($category_filter, $search);
    $books = mergeBooks($rawBooks);
}

$page_title = "LibCollect: Reference Mapping System - Library Books";
include '../includes/header.php';
?>

<style>
.department-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    height: 100%;
}

.department-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.department-card.active {
    border-color: var(--bs-primary);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.department-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.book-count {
    font-size: 2rem;
    font-weight: bold;
}

.search-section {
    background: #ffd700;
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.back-button {
    position: sticky;
    top: 20px;
    z-index: 1000;
    margin-bottom: 1rem;
}

/* Enhanced Book Card Styles */
.book-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
    height: 100%;
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15) !important;
}

.book-cover {
    position: relative;
    background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
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
    background: repeating-linear-gradient(
        90deg,
        transparent,
        transparent 2px,
        rgba(255,255,255,0.03) 2px,
        rgba(255,255,255,0.03) 4px
    );
}

.book-spine {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.9;
    z-index: 2;
    position: relative;
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

.quantity-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    z-index: 3;
    backdrop-filter: blur(10px);
}

.copy-number-badge {
    position: absolute;
    bottom: 8px;
    left: 8px;
    background: rgba(255,255,255,0.15);
    padding: 3px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
    z-index: 3;
    backdrop-filter: blur(10px);
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

.book-description {
    font-size: 0.85rem;
    line-height: 1.4;
    color: #6c757d;
    flex-grow: 1;
    margin-bottom: 0.75rem;
}

.academic-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
}

.academic-info .badge {
    font-size: 0.65rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

/* Enhanced Academic Context Styles */
.academic-contexts {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    max-height: 120px;
    overflow-y: auto;
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

.book-year {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.book-actions .btn-group {
    width: 100%;
}

.book-actions .dropdown-menu {
    width: 100%;
}

/* Different gradients for different departments */
.book-card[data-department="BIT"] .book-cover {
    background: rgb(255 193 7) !important;
}

.book-card[data-department="EDUCATION"] .book-cover {
    background: rgb(13 110 253) !important;
}

.book-card[data-department="HBM"] .book-cover {
    background: rgb(220 53 69) !important;
}

.book-card[data-department="COMPSTUD"] .book-cover {
    background: rgb(33 37 41) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .book-cover {
        height: 150px;
    }
    
    .book-spine {
        font-size: 2rem;
    }
    
    .book-title {
        font-size: 0.9rem;
    }
    
    .book-author, .book-isbn {
        font-size: 0.8rem;
    }
}

@media (max-width: 576px) {
    .book-cover {
        height: 130px;
    }
    
    .book-spine {
        font-size: 1.8rem;
    }
}
</style>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$category_filter && !$search): ?>
    <!-- Library Welcome Section -->
    <div class="search-section">
        <div class="container">
            <div class="text-center">
                <h1 class="display-4 mb-3" style="color: black;">
                    <i class="fas fa-book-open me-3" style="color: black;"></i>ISAT U Digital Library
                </h1>
                <p class="lead mb-4" style="color: black;">Browse books by department or search our entire collection</p>                
                <!-- Quick Search -->
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control form-control-lg me-2" 
                                   name="search" placeholder="Search all books..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Cards -->
    <div class="container mb-5">
        <div class="row g-4 justify-content-center">
            <?php foreach ($departments as $dept => $info): ?>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card department-card h-100 text-center" 
                         onclick="window.location.href='?category=<?php echo $dept; ?>'">
                        <div class="card-body d-flex flex-column">
                            <div class="department-icon text-<?php echo $info['color']; ?>">
                                <i class="<?php echo $info['icon']; ?>"></i>
                            </div>
                            <h4 class="card-title mb-3"><?php echo $info['name']; ?></h4>
                            <p class="card-text text-muted mb-3"><?php echo $info['description']; ?></p>
                            <div class="mt-auto">
                                <div class="book-count text-<?php echo $info['color']; ?> mb-2">
                                    <?php echo $bookCounts[$dept]; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo $bookCounts[$dept] == 1 ? 'Book' : 'Books'; ?> Available
                                </small>
                            </div>
                        </div>
                        <div class="card-footer bg-<?php echo $info['color']; ?> text-white">
                            <small><i class="fas fa-arrow-right me-1"></i>Browse <?php echo $dept; ?> Books</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="container">
        <div class="card bg-light" style="max-width: 1200px; margin: 0 auto;">
            <div class="card-body text-center">
                <div class="row justify-content-center">
                    <div class="col-md-3 col-6">
                        <h3 class="text-primary"><?php echo array_sum($bookCounts); ?></h3>
                        <p class="mb-0 text-muted">Total Books</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <h3 class="text-success"><?php echo count($departments); ?></h3>
                        <p class="mb-0 text-muted">Departments</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <h3 class="text-info">24/7</h3>
                        <p class="mb-0 text-muted">Digital Access</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="add-book.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus me-1"></i>Add New Book
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Books Listing Section -->
    <div class="back-button">
        <a href="books.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Back to Departments
        </a>
    </div>

    <div class="page-header mb-4">
        <?php if ($category_filter): ?>
            <?php $deptInfo = $departments[$category_filter]; ?>
            <div class="d-flex align-items-center mb-3">
                <div class="department-icon text-<?php echo $deptInfo['color']; ?> me-3" style="font-size: 2.5rem;">
                    <i class="<?php echo $deptInfo['icon']; ?>"></i>
                </div>
                <div>
                    <h1 class="h2 mb-1"><?php echo $deptInfo['name']; ?></h1>
                </div>
            </div>
        <?php else: ?>
            <h1 class="h2 mb-2">Search Results</h1>
            <p class="mb-0">Results for "<?php echo htmlspecialchars($search); ?>"</p>
        <?php endif; ?>
    </div>

    <!-- Enhanced Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <?php if ($category_filter): ?>
                    <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                <?php endif; ?>
                
                <div class="col-md-6 mb-2">
                    <label class="form-label">Search within <?php echo $category_filter ? $departments[$category_filter]['name'] : 'all books'; ?></label>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by title, author, ISBN, or year..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <?php if (!$category_filter): ?>
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
                <?php endif; ?>
                
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
                <div class="col-md-1 mb-2">
                    <a href="books.php<?php echo $category_filter ? '?category=' . $category_filter : ''; ?>" 
                       class="btn btn-outline-secondary w-100" title="Clear Search">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Display -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">
                    <i class="fas fa-books me-2"></i>
                    <?php if ($category_filter): ?>
                        <?php echo $departments[$category_filter]['name']; ?> Books
                    <?php else: ?>
                        Search Results
                    <?php endif; ?>
                </h5>
                <small class="text-muted">
                    <?php echo count($books); ?> book(s) found
                    <?php if ($search): ?>
                        matching "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </small>
            </div>
            <a href="add-book.php<?php echo $category_filter ? '?category=' . $category_filter : ''; ?>" 
               class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Add New Book
            </a>
        </div>
        
        <div class="card-body">
            <?php if (empty($books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No books found</h4>
                    <?php if ($search): ?>
                        <p class="text-muted">Try adjusting your search criteria.</p>
                        <a href="books.php<?php echo $category_filter ? '?category=' . $category_filter : ''; ?>" 
                           class="btn btn-outline-primary me-2">
                            <i class="fas fa-times me-1"></i>Clear Search
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Be the first to add books to this department!</p>
                    <?php endif; ?>
                    <a href="add-book.php<?php echo $category_filter ? '?category=' . $category_filter : ''; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add First Book
                    </a>
                </div>
            <?php else: ?>
                <!-- Enhanced Books Grid Layout -->
                <div class="row g-3">
                    <?php foreach ($books as $book_item): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                            <?php
                            // Determine which department color to use based on current filter
                            $displayDepartment = $category_filter ? $category_filter : $book_item['category'];
                            ?>
                            <div class="card book-card shadow-sm" data-department="<?php echo $displayDepartment; ?>">
                                <!-- Enhanced Book Cover -->
                                <div class="book-cover">
                                    <div class="book-spine">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    
                                    
                                    
                                    <!-- Merged book indicator -->
                                    <?php if (count($book_item['academic_contexts']) > 1): ?>
                                        <div class="merged-indicator">
                                            <i class="fas fa-layer-group me-1"></i><?php echo count($book_item['academic_contexts']); ?> Uses
                                        </div>
                                    <?php endif; ?>

                                    <!-- Multi-department indicator -->
                                    <?php
                                    // Check if this book has multiple departments across all contexts
                                    $allDepartments = [];
                                    foreach ($book_item['academic_contexts'] as $context) {
                                        if (!empty($context['category']) && !in_array($context['category'], $allDepartments)) {
                                            $allDepartments[] = $context['category'];
                                        }
                                    }
                                    $hasMultipleDepartments = count($allDepartments) > 1;
                                    ?>

                                    <?php if ($hasMultipleDepartments): ?>
                                        <div class="multi-department-indicator" style="position: absolute; top: 8px; left: 8px; background: rgba(255,255,255,0.9); color: #6c757d; padding: 2px 6px; border-radius: 8px; font-size: 0.65rem; z-index: 3; backdrop-filter: blur(10px);">
                                            <i class="fas fa-building me-1"></i>Multi-Dept
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Publication year badge -->
                                    <?php if (!empty($book_item['publication_year'])): ?>
                                        <div class="publication-year-badge">
                                            <i class="fas fa-calendar me-1"></i><?php echo $book_item['publication_year']; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Total copies badge -->
                                    <div class="total-copies-badge">
                                        <i class="fas fa-copy me-1"></i><?php echo $book_item['total_quantity']; ?>
                                    </div>
                                </div>
                                
                                <!-- Enhanced Book Details -->
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title book-title" title="<?php echo htmlspecialchars($book_item['title']); ?>">
                                        <?php echo htmlspecialchars($book_item['title']); ?>
                                    </h6>
                                    
                                    <p class="card-text book-author">
                                        <i class="fas fa-user me-1 text-muted"></i>
                                        <span class="text-dark"><?php echo htmlspecialchars($book_item['author']); ?></span>
                                    </p>
                                    
                                    <?php if (!empty($book_item['isbn'])): ?>
                                        <p class="card-text book-isbn">
                                            <i class="fas fa-barcode me-1 text-muted"></i>
                                            <span class="text-muted"><?php echo htmlspecialchars($book_item['isbn']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($book_item['publication_year'])): ?>
                                        <p class="card-text book-year">
                                            <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                            <span class="text-muted">Published: <?php echo htmlspecialchars($book_item['publication_year']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Academic Contexts - Grouped Display -->
                                    <?php if (!empty($book_item['academic_contexts'])): ?>
                                        <div class="academic-contexts">
                                            <div class="context-header">
                                                <i class="fas fa-graduation-cap me-1"></i>
                                                Academic Uses (<?php echo count($book_item['academic_contexts']); ?>)
                                            </div>
                                            
                                            <?php 
                                            // Group contexts by department
                                            $contextsByDept = [];
                                            foreach ($book_item['academic_contexts'] as $context) {
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
                                                        <?php if (isset($departments[$dept])): ?>
                                                            <span class="badge bg-<?php echo $departments[$dept]['color']; ?> me-2">
                                                                <i class="<?php echo $departments[$dept]['icon']; ?> me-1"></i>
                                                                <?php echo $dept; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <strong class="text-primary me-2">
                                                                <?php echo $dept; ?>
                                                            </strong>
                                                        <?php endif; ?>
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
                                    
                                    <?php if (!empty($book_item['description'])): ?>
                                        <p class="card-text book-description">
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars(substr($book_item['description'], 0, 80)); ?>
                                                <?php if (strlen($book_item['description']) > 80): ?>...<?php endif; ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Enhanced Action Buttons with Dropdown for Multiple Records -->
                                    <div class="mt-auto book-actions">
                                        <?php if (count($book_item['record_ids']) > 1): ?>
                                            <!-- Multiple records - show dropdown -->
                                            <div class="btn-group w-100" role="group">
                                                <a href="view-book.php?id=<?php echo $book_item['id']; ?>" 
                                                class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle dropdown-toggle-split" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><h6 class="dropdown-header">Manage Records</h6></li>
                                                    <?php foreach ($book_item['record_ids'] as $index => $recordId): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="edit-book.php?id=<?php echo $recordId; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit Record #<?php echo $recordId; ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="#" 
                                                        onclick="event.preventDefault(); confirmArchiveAll([<?php echo implode(',', $book_item['record_ids']); ?>], '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-archive me-2"></i>Archive All Records
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" 
                                                        onclick="event.preventDefault(); confirmDeleteAll([<?php echo implode(',', $book_item['record_ids']); ?>], '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-trash me-2"></i>Delete All Records
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <!-- Single record - regular buttons -->
                                            <div class="d-grid gap-1">
                                                <!--<a href="view-book.php?id=<?php echo $book_item['id']; ?>" 
                                                class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>-->
                                                <a href="edit-book.php?id=<?php echo $book_item['id']; ?>" 
                                                class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-warning btn-sm" 
                                                        onclick="confirmArchive(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-archive me-1"></i>Archive
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmDelete(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>', deleteBook)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-archive me-2"></i>Archive Book
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Note:</strong> This book will be moved to Pending Archives where you can select an archive reason.
                </div>
                
                <p>Are you sure you want to archive this book?</p>
                <div class="alert alert-warning">
                    <strong id="archiveBookTitle"></strong>
                </div>
                <small class="text-muted">The book will remain searchable and can be restored anytime from Archives.</small>
                
                <form id="archiveBookForm" method="POST">
                    <input type="hidden" name="action" value="send_to_pending">
                    <input type="hidden" name="id" id="archiveBookId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmArchiveBtn">
                    <i class="fas fa-archive me-1"></i>Send to Pending Archives
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this book?</p>
                <div class="alert alert-warning">
                    <strong id="bookTitle"></strong>
                </div>
                <small class="text-muted">This action cannot be undone.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i>Delete Book
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteBookId = null;
let deleteCallback = null;
let archiveBookId = null;

function confirmDelete(id, title, callback) {
    deleteBookId = id;
    deleteCallback = callback;
    document.getElementById('bookTitle').textContent = title;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function confirmDeleteAll(ids, title) {
    if (confirm(`Are you sure you want to delete all ${ids.length} records of "${title}"?\n\nThis action cannot be undone.`)) {
        deleteMultipleBooks(ids);
    }
}

function confirmArchive(id, title) {
    archiveBookId = id;
    document.getElementById('archiveBookTitle').textContent = title;
    document.getElementById('archiveBookId').value = id;
    
    const modal = new bootstrap.Modal(document.getElementById('archiveModal'));
    modal.show();
}

function confirmArchiveAll(ids, title) {
    if (confirm(`Are you sure you want to send all ${ids.length} records of "${title}" to Pending Archives?\n\nYou'll need to select archive reasons in the Archives page.`)) {
        archiveMultipleBooks(ids);
    }
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteBookId && deleteCallback) {
        deleteCallback(deleteBookId);
    }
});

document.getElementById('confirmArchiveBtn').addEventListener('click', function() {
    if (archiveBookId) {
        document.getElementById('archiveBookForm').submit();
    }
});

function deleteBook(id) {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteMultipleBooks(ids) {
    // Create a form to delete multiple books
    const form = document.createElement('form');
    form.method = 'POST';
    
    let inputs = '<input type="hidden" name="action" value="delete_multiple">';
    ids.forEach(id => {
        inputs += `<input type="hidden" name="ids[]" value="${id}">`;
    });
    
    form.innerHTML = inputs;
    document.body.appendChild(form);
    form.submit();
}

function archiveMultipleBooks(ids) {
    // Create a form to archive multiple books
    const form = document.createElement('form');
    form.method = 'POST';
    
    let inputs = '<input type="hidden" name="action" value="send_multiple_to_pending">';
    ids.forEach(id => {
        inputs += `<input type="hidden" name="ids[]" value="${id}">`;
    });
    
    form.innerHTML = inputs;
    document.body.appendChild(form);
    form.submit();
}

// Add smooth scrolling and animations
document.addEventListener('DOMContentLoaded', function() {
    // Add active state to selected department
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category');
    if (category) {
        const cards = document.querySelectorAll('.department-card');
        cards.forEach(card => {
            if (card.onclick && card.onclick.toString().includes(category)) {
                card.classList.add('active');
            }
        });
    }
    
    // Add stagger animation to book cards
    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
    
    // Initialize tooltips for academic contexts
    const contexts = document.querySelectorAll('.academic-contexts');
    contexts.forEach(context => {
        if (context.scrollHeight > context.clientHeight) {
            context.setAttribute('title', 'Scroll to see more academic contexts');
        }
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
    
    /* Custom scrollbar for academic contexts */
    .academic-contexts {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>
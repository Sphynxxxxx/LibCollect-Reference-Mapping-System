<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Handle form submissions BEFORE any HTML output
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
        'color' => 'primary',
        'icon' => 'fas fa-industry',
        'description' => 'Manufacturing, Engineering, Technology'
    ],
    'EDUCATION' => [
        'name' => 'Education Department',
        'color' => 'success', 
        'icon' => 'fas fa-graduation-cap',
        'description' => 'Teaching, Learning, Pedagogy'
    ],
    'HBM' => [
        'name' => 'Hotel & Business Management',
        'color' => 'info',
        'icon' => 'fas fa-building',
        'description' => 'Business, Management, Hospitality'
    ],
    'COMPSTUD' => [
        'name' => 'Computer Studies',
        'color' => 'warning',
        'icon' => 'fas fa-desktop',
        'description' => 'Information Tech, Software Dev'
    ]
];

// Get book counts for each department
$bookCounts = [];
foreach ($departments as $dept => $info) {
    $deptBooks = $book->getAllBooks($dept, '');
    $bookCounts[$dept] = count($deptBooks);
}

// Get books if a category is selected
$books = [];
if ($category_filter || $search) {
    $books = $book->getAllBooks($category_filter, $search);
}

$page_title = "Library Books - ISAT U Library Miagao Campus";
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

/* Book Card Styles */
.book-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15) !important;
}

.book-cover {
    position: relative;
    background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
    height: 150px;
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
    font-size: 2rem;
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
    padding: 3px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: bold;
}

.department-badge {
    font-size: 0.65rem;
    padding: 3px 6px;
}

.quantity-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(255,255,255,0.2);
    padding: 3px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
    z-index: 3;
}

.book-title {
    font-weight: 600;
    line-height: 1.2;
    height: 2.4em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: 1rem;
}

.book-author, .book-isbn {
    font-size: 0.9rem;
    font-weight: 500;
}

.book-description {
    font-size: 0.85rem;
    line-height: 1.3;
    color: #6c757d;
    flex-grow: 1;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .book-cover {
        height: 120px;
    }
    
    .book-spine {
        font-size: 1.8rem;
    }
    
    .book-title {
        font-size: 0.9rem;
    }
    
    .book-author, .book-isbn {
        font-size: 0.8rem;
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
                <h1 class="display-4 mb-3">
                    <i class="fas fa-book-open me-3"></i>ISAT U Digital Library
                </h1>
                <p class="lead mb-4">Browse books by department or search our entire collection</p>
                
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
                <div class="col-lg-3 col-md-6 col-sm-6" style="width: 600px;">
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
                    <p class="mb-0 text-muted"><?php echo $deptInfo['description']; ?></p>
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
                           placeholder="Search by title, author, or ISBN..." 
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
                <!-- Books Grid Layout -->
                <div class="row g-3">
                    <?php foreach ($books as $book_item): ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="card book-card h-100 shadow-sm" data-department="<?php echo $book_item['category']; ?>">
                                <!-- Book Cover/Icon -->
                                <div class="book-cover">
                                    <div class="book-spine">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="book-info">
                                        <div class="book-id">#<?php echo $book_item['id']; ?></div>
                                        <?php if (!$category_filter): ?>
                                            <?php $deptInfo = $departments[$book_item['category']]; ?>
                                            <span class="badge bg-<?php echo $deptInfo['color']; ?> department-badge">
                                                <?php echo $book_item['category']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="quantity-badge">
                                        <i class="fas fa-copy me-1"></i><?php echo $book_item['quantity']; ?>
                                    </div>
                                </div>
                                
                                <!-- Book Details -->
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title book-title mb-2" title="<?php echo htmlspecialchars($book_item['title']); ?>">
                                        <?php echo htmlspecialchars($book_item['title']); ?>
                                    </h6>
                                    
                                    <p class="card-text book-author mb-1">
                                        <i class="fas fa-user me-1"></i>
                                        <span class="text-dark"><?php echo htmlspecialchars($book_item['author']); ?></span>
                                    </p>
                                    
                                    <?php if (!empty($book_item['isbn'])): ?>
                                        <p class="card-text book-isbn mb-2">
                                            <i class="fas fa-barcode me-1"></i>
                                            <span class="text-muted"><?php echo htmlspecialchars($book_item['isbn']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($book_item['description'])): ?>
                                        <p class="card-text book-description mb-2">
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars(substr($book_item['description'], 0, 60)); ?>
                                                <?php if (strlen($book_item['description']) > 60): ?>...<?php endif; ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-auto">
                                        <div class="d-grid gap-1">
                                            <a href="edit-book.php?id=<?php echo $book_item['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>', deleteBook)">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
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

<script>
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

// Add smooth scrolling for better UX
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
});
</script>

<?php include '../includes/footer.php'; ?>
<?php
// Handle any POST operations first, before any output
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

// Get books from database
$books = $book->getAllBooks($category_filter, $search);

// Debug: Add this temporarily to see what's happening
error_log("Books found: " . count($books));
error_log("Category filter: " . $category_filter);
error_log("Search term: " . $search);

// Now set page title and include header
$page_title = "Manage Books - ISAT U Library";
include '../includes/header.php';
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1 class="h2 mb-2">Manage Books</h1>
    <p class="mb-0">View, search, and manage all library books</p>
</div>


<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-4 mb-2">
                <input type="text" class="form-control" name="search" placeholder="Search books..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3 mb-2">
                <select class="form-control" name="category">
                    <option value="">All Categories</option>
                    <?php foreach (['BIT', 'EDUCATION', 'HBM', 'COMPSTUD'] as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Search
                </button>
            </div>
            <div class="col-md-2 mb-2">
                <a href="books.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-refresh me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Books Table -->
<div class="card">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Book Library</h5>
        <a href="add-book.php" class="btn btn-light btn-sm">
            <i class="fas fa-plus me-1"></i>Add New Book
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($books)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No books found</h4>
                <?php if ($category_filter || $search): ?>
                    <p class="text-muted">Try adjusting your search criteria or clear the filters.</p>
                    <a href="books.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-refresh me-1"></i>Clear Filters
                    </a>
                <?php else: ?>
                    <p class="text-muted">Add some books to get started!</p>
                <?php endif; ?>
                <a href="add-book.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Add First Book
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>ISBN</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book_item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book_item['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($book_item['title']); ?></strong>
                                    <?php if (!empty($book_item['description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($book_item['description'], 0, 50)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($book_item['author']); ?></td>
                                <td>
                                    <?php
                                    $categoryColors = [
                                        'BIT' => 'primary', 
                                        'EDUCATION' => 'success', 
                                        'HBM' => 'info', 
                                        'COMPSTUD' => 'warning'
                                    ];
                                    $color = isset($categoryColors[$book_item['category']]) ? $categoryColors[$book_item['category']] : 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($book_item['category']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($book_item['isbn'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo $book_item['quantity']; ?></span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit-book.php?id=<?php echo $book_item['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Edit Book">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $book_item['id']; ?>, '<?php echo htmlspecialchars($book_item['title'], ENT_QUOTES); ?>', deleteBook)"
                                                title="Delete Book">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination info -->
            <div class="mt-3">
                <small class="text-muted">
                    Showing <?php echo count($books); ?> book(s)
                    <?php if ($category_filter): ?>
                        in category "<?php echo htmlspecialchars($category_filter); ?>"
                    <?php endif; ?>
                    <?php if ($search): ?>
                        matching "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

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
</script>

<?php include '../includes/footer.php'; ?>
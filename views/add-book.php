<?php
// Handle any POST operations first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Handle form submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'title' => $_POST['title'],
        'author' => $_POST['author'],
        'isbn' => $_POST['isbn'],
        'category' => $_POST['category'],
        'quantity' => $_POST['quantity'],
        'description' => $_POST['description'],
        'subject_name' => $_POST['subject_name'] ?? '',
        'semester' => $_POST['semester'] ?? '',
        'section' => $_POST['section'] ?? '',
        'year_level' => $_POST['year_level'] ?? '',
        'course_code' => $_POST['course_code'] ?? ''
    ];
    
    if ($book->addBook($data)) {
        $_SESSION['message'] = 'Book added successfully!';
        $_SESSION['message_type'] = 'success';
        header('Location: books.php');
        exit;
    } else {
        $error_message = 'Failed to add book. Please try again.';
    }
}

// Now set page title and include header
$page_title = "Add Book - ISAT U Library";
include '../includes/header.php';
?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1 class="h2 mb-2">Add New Book</h1>
    <p class="mb-0">Add academic resources to the ISAT U library collection</p>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Book Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addBookForm">
                    <!-- Basic Book Information -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-book me-2"></i>Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Book Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       placeholder="Enter complete book title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" required 
                                       placeholder="Author's full name">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ISBN</label>
                                <input type="text" class="form-control" name="isbn">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Department/Category *</label>
                                <select class="form-control" name="category" required id="categorySelect">
                                    <option value="">Select Department</option>
                                    <option value="BIT">Bachelor of Industrial Technology (BIT)</option>
                                    <option value="EDUCATION">Education</option>
                                    <option value="HBM">Hotel and Business Management (HBM)</option>
                                    <option value="COMPSTUD">Computer Studies (COMPSTUD)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Name</label>
                                <input type="text" class="form-control" name="subject_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Code</label>
                                <input type="text" class="form-control" name="course_code">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Year Level</label>
                                <select class="form-control" name="year_level">
                                    <option value="">Select Year</option>
                                    <option value="First Year">First Year</option>
                                    <option value="Second Year">Second Year</option>
                                    <option value="Third Year">Third Year</option>
                                    <option value="Fourth Year">Fourth Year</option>
                                    <option value="Graduate">Graduate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Semester</label>
                                <select class="form-control" name="semester">
                                    <option value="">Select Semester</option>
                                    <option value="First Semester">First Semester</option>
                                    <option value="Second Semester">Second Semester</option>
                                    <option value="Summer">Summer</option>
                                    <option value="Midyear">Midyear</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Section</label>
                                <input type="text" class="form-control" name="section" 
                                       placeholder="e.g., A, B, 1A, 2B">
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <h6 class="text-info mb-3"><i class="fas fa-align-left me-2"></i>Description & Notes</h6>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Brief description of the book content, learning objectives, or course relevance..."></textarea>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Add Book to Collection
                        </button>
                        <button type="reset" class="btn btn-outline-warning">
                            <i class="fas fa-redo me-1"></i>Reset Form
                        </button>
                        <a href="books.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Books
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Guidelines Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Department Guidelines</h5>
            </div>
            <div class="card-body">
                <h6>Academic Departments:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <strong class="text-primary">BIT:</strong> 
                        <small class="d-block text-muted">Industrial Technology, Electronics, Mechanical Engineering</small>
                    </li>
                    <li class="mb-2">
                        <strong class="text-success">EDUCATION:</strong> 
                        <small class="d-block text-muted">Teaching materials, Pedagogy, Educational Psychology</small>
                    </li>
                    <li class="mb-2">
                        <strong class="text-info">HBM:</strong> 
                        <small class="d-block text-muted">Hotel Management, Business Administration, Tourism</small>
                    </li>
                    <li class="mb-2">
                        <strong class="text-warning">COMPSTUD:</strong> 
                        <small class="d-block text-muted">Computer Science, Information Technology, Programming</small>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Input Tips Card -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Input Tips</h5>
            </div>
            <div class="card-body">
                <h6>Required Fields:</h6>
                <ul class="small text-muted">
                    <li>Book Title and Author are mandatory</li>
                    <li>Select appropriate department/category</li>
                    <li>Quantity must be at least 1</li>
                </ul>
                
                <h6 class="mt-3">Best Practices:</h6>
                <ul class="small text-muted">
                    <li>Use complete, official book titles</li>
                    <li>Include subject codes when available</li>
                    <li>Add detailed descriptions for better searchability</li>
                    <li>Verify ISBN format (10 or 13 digits)</li>
                </ul>

                <h6 class="mt-3">Academic Info:</h6>
                <ul class="small text-muted">
                    <li>Subject name helps with course alignment</li>
                    <li>Year/Semester for curriculum mapping</li>
                    <li>Section info for class-specific resources</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
// Form validation and enhancement
document.getElementById('addBookForm').addEventListener('submit', function(e) {
    const title = document.querySelector('input[name="title"]').value.trim();
    const author = document.querySelector('input[name="author"]').value.trim();
    const category = document.querySelector('select[name="category"]').value;
    
    if (!title || !author || !category) {
        e.preventDefault();
        alert('Please fill in all required fields (Title, Author, and Category).');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding Book...';
    submitBtn.disabled = true;
});

// Auto-populate course code based on category selection
document.getElementById('categorySelect').addEventListener('change', function() {
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const selectedCategory = this.value;
    
    // Clear existing value
    courseCodeInput.value = '';
    
    // Set placeholder based on category
    switch(selectedCategory) {
        case 'BIT':
            courseCodeInput.placeholder = 'e.g., BIT-OSH-101, BIT-ELECT-201';
            break;
        case 'EDUCATION':
            courseCodeInput.placeholder = 'e.g., EDUC-PSYC-101, EDUC-METH-201';
            break;
        case 'HBM':
            courseCodeInput.placeholder = 'e.g., HBM-MGMT-101, HBM-TOUR-201';
            break;
        case 'COMPSTUD':
            courseCodeInput.placeholder = 'e.g., COMP-PROG-101, COMP-DBMS-201';
            break;
        default:
            courseCodeInput.placeholder = 'Enter course code';
    }
});

// Auto-format ISBN input
document.querySelector('input[name="isbn"]').addEventListener('input', function() {
    let value = this.value.replace(/[^\d]/g, ''); // Remove non-digits
    
    if (value.length === 10) {
        // Format as ISBN-10: XXX-X-XXX-XXXX-X
        this.value = value.replace(/(\d{3})(\d{1})(\d{3})(\d{3})(\d{1})/, '$1-$2-$3-$4-$5');
    } else if (value.length === 13) {
        // Format as ISBN-13: XXX-X-XX-XXXXXX-X
        this.value = value.replace(/(\d{3})(\d{1})(\d{2})(\d{6})(\d{1})/, '$1-$2-$3-$4-$5');
    }
});

// Character counter for description
document.querySelector('textarea[name="description"]').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    
    // Create or update character counter
    let counter = document.getElementById('descriptionCounter');
    if (!counter) {
        counter = document.createElement('small');
        counter.id = 'descriptionCounter';
        counter.className = 'text-muted';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} characters`;
    
    if (currentLength > maxLength) {
        counter.className = 'text-danger';
        this.value = this.value.substring(0, maxLength);
    } else {
        counter.className = 'text-muted';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
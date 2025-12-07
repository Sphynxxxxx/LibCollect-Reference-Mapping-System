<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book_data = $book->getBookById($book_id);

if (!$book_data) {
    $_SESSION['message'] = 'Book not found!';
    $_SESSION['message_type'] = 'error';
    header('Location: books.php');
    exit;
}

// Handle form submission
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
    
    if ($book->updateBook($book_id, $data)) {
        $_SESSION['message'] = 'Book updated successfully!';
        $_SESSION['message_type'] = 'success';
        header('Location: books.php');
        exit;
    } else {
        $error_message = 'Failed to update book. Please try again.';
    }
}

$page_title = "Edit Book - ISAT U Library Miagao Campus";
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
    <h1 class="h2 mb-2">Edit Book</h1>
    <p class="mb-0">Update academic resource information in the ISAT U library collection</p>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Book Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editBookForm">
                    <!-- Basic Book Information -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-book me-2"></i>Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Book Title *</label>
                                <input type="text" class="form-control" name="title" 
                                       value="<?php echo htmlspecialchars($book_data['title']); ?>" 
                                       required placeholder="Enter complete book title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" 
                                       value="<?php echo htmlspecialchars($book_data['author']); ?>" 
                                       required placeholder="Author's full name">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Call No.</label>
                                <input type="text" class="form-control" name="isbn" 
                                       value="<?php echo htmlspecialchars($book_data['isbn']); ?>" 
                                       placeholder="978-XXXXXXXXXX">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Council/Category *</label>
                                <select class="form-control" name="category" required id="categorySelect">
                                    <option value="">Select Council</option>
                                    <option value="BIT" <?php echo ($book_data['category'] == 'BIT') ? 'selected' : ''; ?>>Bachelor of Industrial Technology (BIT)</option>
                                    <option value="EDUCATION" <?php echo ($book_data['category'] == 'EDUCATION') ? 'selected' : ''; ?>>Education</option>
                                    <option value="HBM" <?php echo ($book_data['category'] == 'HBM') ? 'selected' : ''; ?>>Hotel and Business Management (HBM)</option>
                                    <option value="COMPSTUD" <?php echo ($book_data['category'] == 'COMPSTUD') ? 'selected' : ''; ?>>Computer Studies (COMPSTUD)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity" min="1" 
                                       value="<?php echo $book_data['quantity']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject Name</label>
                                <input type="text" class="form-control" name="subject_name" 
                                       value="<?php echo htmlspecialchars($book_data['subject_name'] ?? ''); ?>" 
                                       placeholder="e.g., Occupational Safety and Health">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Code</label>
                                <input type="text" class="form-control" name="course_code" 
                                       value="<?php echo htmlspecialchars($book_data['course_code'] ?? ''); ?>" 
                                       placeholder="e.g., BIT-OSH, EDUC-101">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Year Level</label>
                                <select class="form-control" name="year_level">
                                    <option value="">Select Year</option>
                                    <option value="First Year" <?php echo (($book_data['year_level'] ?? '') == 'First Year') ? 'selected' : ''; ?>>First Year</option>
                                    <option value="Second Year" <?php echo (($book_data['year_level'] ?? '') == 'Second Year') ? 'selected' : ''; ?>>Second Year</option>
                                    <option value="Third Year" <?php echo (($book_data['year_level'] ?? '') == 'Third Year') ? 'selected' : ''; ?>>Third Year</option>
                                    <option value="Fourth Year" <?php echo (($book_data['year_level'] ?? '') == 'Fourth Year') ? 'selected' : ''; ?>>Fourth Year</option>
                                    <option value="Graduate" <?php echo (($book_data['year_level'] ?? '') == 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Semester</label>
                                <select class="form-control" name="semester">
                                    <option value="">Select Semester</option>
                                    <option value="First Semester" <?php echo (($book_data['semester'] ?? '') == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                                    <option value="Second Semester" <?php echo (($book_data['semester'] ?? '') == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                                    <option value="Summer" <?php echo (($book_data['semester'] ?? '') == 'Summer') ? 'selected' : ''; ?>>Summer</option>
                                    <option value="Midyear" <?php echo (($book_data['semester'] ?? '') == 'Midyear') ? 'selected' : ''; ?>>Midyear</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Section</label>
                                <input type="text" class="form-control" name="section" 
                                       value="<?php echo htmlspecialchars($book_data['section'] ?? ''); ?>" 
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
                                      placeholder="Brief description of the book content, learning objectives, or course relevance..."><?php echo htmlspecialchars($book_data['description']); ?></textarea>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="fas fa-save me-1"></i>Update Book
                        </button>
                        <button type="reset" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-1"></i>Reset Changes
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
        <!-- Book Details Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Book Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Book ID:</strong> #<?php echo str_pad($book_data['id'], 4, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="mb-3">
                    <strong>Created:</strong> <?php echo date('M d, Y', strtotime($book_data['created_at'])); ?>
                </div>
                <div class="mb-3">
                    <strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($book_data['updated_at'])); ?>
                </div>
                
                <?php if (!empty($book_data['subject_name'])): ?>
                <div class="mb-3">
                    <strong>Current Subject:</strong><br>
                    <span class="badge bg-success"><?php echo htmlspecialchars($book_data['subject_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($book_data['course_code'])): ?>
                <div class="mb-3">
                    <strong>Course Code:</strong><br>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($book_data['course_code']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($book_data['year_level']) && !empty($book_data['semester'])): ?>
                <div class="mb-3">
                    <strong>Academic Period:</strong><br>
                    <span class="badge bg-info"><?php echo htmlspecialchars($book_data['year_level']) . ' - ' . htmlspecialchars($book_data['semester']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Editing Guidelines Card -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Editing Guidelines</h5>
            </div>
            <div class="card-body">
                <h6>Important Notes:</h6>
                <ul class="small text-muted">
                    <li>Verify all information before saving changes</li>
                    <li>ISBN should be valid and unique in the system</li>
                    <li>Category should accurately match book content</li>
                    <li>Update quantity if copies were added/removed</li>
                    <li>Academic info helps with course alignment</li>
                </ul>
                
                <h6 class="mt-3">Academic Fields:</h6>
                <ul class="small text-muted">
                    <li>Subject name should match official curriculum</li>
                    <li>Use standard course code format</li>
                    <li>Year/Semester for proper categorization</li>
                    <li>Section helps identify class-specific resources</li>
                </ul>
            </div>
        </div>

        <!-- Change History Card -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Changes</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <small class="text-muted">
                            <i class="fas fa-plus-circle text-success"></i>
                            Created: <?php echo date('M d, Y g:i A', strtotime($book_data['created_at'])); ?>
                        </small>
                    </div>
                    <?php if ($book_data['updated_at'] != $book_data['created_at']): ?>
                    <div class="timeline-item mt-2">
                        <small class="text-muted">
                            <i class="fas fa-edit text-warning"></i>
                            Last Modified: <?php echo date('M d, Y g:i A', strtotime($book_data['updated_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-primary"></i>
                        All changes are tracked for audit purposes
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and enhancement
document.getElementById('editBookForm').addEventListener('submit', function(e) {
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
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating Book...';
    submitBtn.disabled = true;
    
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

// Auto-populate course code based on category selection
document.getElementById('categorySelect').addEventListener('change', function() {
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const selectedCategory = this.value;
    
    // Only update placeholder if field is empty
    if (!courseCodeInput.value) {
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
    }
});

// Reset form to original values
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will restore the original values.')) {
        location.reload();
    }
}

// Auto-format ISBN input
document.querySelector('input[name="isbn"]').addEventListener('input', function() {
    let value = this.value.replace(/[^\d]/g, ''); 
    
    if (value.length === 10) {
        // Format as ISBN-10
        this.value = value.replace(/(\d{3})(\d{1})(\d{3})(\d{3})(\d{1})/, '$1-$2-$3-$4-$5');
    } else if (value.length === 13) {
        // Format as ISBN-13
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

// Highlight changes
const originalValues = {};
document.querySelectorAll('input, select, textarea').forEach(field => {
    if (field.name) {
        originalValues[field.name] = field.value;
        
        field.addEventListener('input', function() {
            if (this.value !== originalValues[this.name]) {
                this.classList.add('border-warning');
                this.style.backgroundColor = '#fff3cd';
            } else {
                this.classList.remove('border-warning');
                this.style.backgroundColor = '';
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
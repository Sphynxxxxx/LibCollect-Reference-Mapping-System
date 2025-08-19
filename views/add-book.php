<?php
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
    // Handle multiple selections
    $categories = isset($_POST['category']) ? $_POST['category'] : [];
    $year_levels = isset($_POST['year_level']) ? $_POST['year_level'] : [];
    $semesters = isset($_POST['semester']) ? $_POST['semester'] : [];
    $sections = isset($_POST['section']) ? (is_array($_POST['section']) ? $_POST['section'] : explode(',', $_POST['section'])) : [];
    
    // Clean up sections array (remove empty values and trim)
    $sections = array_filter(array_map('trim', $sections));
    
    $success_count = 0;
    $error_count = 0;
    $archived_count = 0; // Track archived books
    $total_quantity = $_POST['quantity'];
    
    // Handle ISBN data - could be single ISBN or array of ISBNs
    $isbn_data = [];
    if (isset($_POST['isbn'])) {
        if (is_array($_POST['isbn'])) {
            $isbn_data = array_filter($_POST['isbn']); // Remove empty ISBNs
        } else {
            $isbn_data = [$_POST['isbn']];
        }
    }
    
    // If no multiple selections, create single entry
    if (empty($categories)) $categories = [''];
    if (empty($year_levels)) $year_levels = [''];
    if (empty($semesters)) $semesters = [''];
    if (empty($sections)) $sections = [''];
    
    // Calculate total combinations
    $total_combinations = count($categories) * count($year_levels) * count($semesters) * count($sections);
    
    // Determine how to handle the books
    $same_book = isset($_POST['same_book']) && $_POST['same_book'] === 'true';
    $unique_isbns = array_unique(array_filter($isbn_data));
    $unique_book_count = count($unique_isbns);
    
    // Create book records
    for ($i = 0; $i < $total_quantity; $i++) {
        // Determine ISBN for this book copy
        $current_isbn = '';
        if ($same_book && !empty($unique_isbns)) {
            // All books have the same ISBN (first unique ISBN)
            $current_isbn = $unique_isbns[0];
        } else if (!empty($isbn_data)) {
            // Use specific ISBN for this book index (cycle through if needed)
            $isbn_index = $i % count($isbn_data);
            $current_isbn = $isbn_data[$isbn_index];
        }
        
        // Create combinations for each selected option for this book copy
        foreach ($categories as $category) {
            foreach ($year_levels as $year_level) {
                foreach ($semesters as $semester) {
                    foreach ($sections as $section) {
                        $data = [
                            'title' => $_POST['title'],
                            'author' => $_POST['author'],
                            'isbn' => $current_isbn,
                            'category' => $category,
                            'quantity' => 1, // Each record represents 1 physical book
                            'description' => $_POST['description'],
                            'subject_name' => $_POST['subject_name'] ?? '',
                            'semester' => $semester,
                            'section' => $section,
                            'year_level' => $year_level,
                            'course_code' => $_POST['course_code'] ?? '',
                            'publication_year' => $_POST['publication_year'] ?? null, // Add publication year
                            'book_copy_number' => $i + 1, // Track which copy this is
                            'total_quantity' => $total_quantity, // Reference to total
                            'is_multi_record' => ($total_combinations > 1) ? 1 : 0,
                            'same_book_series' => $same_book ? 1 : 0
                        ];
                        
                        $result = $book->addBook($data);
                        
                        // Handle different return values from addBook
                        if ($result === 'archived') {
                            $archived_count++;
                        } elseif ($result) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
            }
        }
    }
    
    // Generate appropriate success message based on results
    if ($success_count > 0 || $archived_count > 0) {
        $book_type = $same_book ? "copies of the same book" : "individual books";
        
        if ($archived_count > 0 && $success_count > 0) {
            // Both active and archived books were added
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection and {$archived_count} to archives (10+ years old)!";
            $_SESSION['message_type'] = 'warning'; // Use warning to indicate mixed results
        } elseif ($archived_count > 0) {
            // Only archived books were added
            $total_books_message = "Successfully added {$archived_count} {$book_type} to archives (books are 10+ years old)!";
            $_SESSION['message_type'] = 'info'; // Use info for archive-only additions
        } else {
            // Only active books were added
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection!";
            $_SESSION['message_type'] = 'success';
        }
        
        $_SESSION['message'] = $total_books_message;
        
        // Add error information if any
        if ($error_count > 0) {
            $_SESSION['message'] .= " ({$error_count} failed)";
        }
        
        // Add archive notification if applicable
        if ($archived_count > 0) {
            $_SESSION['archive_info'] = [
                'count' => $archived_count,
                'message' => "Note: {$archived_count} books were automatically archived due to their publication year being 10+ years old."
            ];
        }
        
        header('Location: books.php');
        exit;
    } else {
        $error_message = 'Failed to add book. Please try again.';
    }
}


// Now set page title and include header
$page_title = "Add Book - ISAT U Library Miagao Campus";
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
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity" id="quantityInput" min="1" value="1" required>
                                <small class="text-muted">Number of books to add</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Publication Year</label>
                                <input type="number" class="form-control" name="publication_year" id="publicationYear" 
                                       min="1800" max="2030" placeholder="e.g., 2024">
                                <small class="text-muted">Year the book was published</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Department/Category *</label>
                                <div class="border rounded p-2" style="max-height: 120px; overflow-y: auto;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category[]" value="BIT" id="cat_bit">
                                        <label class="form-check-label small" for="cat_bit">
                                            Bachelor of Industrial Technology (BIT)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category[]" value="EDUCATION" id="cat_edu">
                                        <label class="form-check-label small" for="cat_edu">
                                            Education
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category[]" value="HBM" id="cat_hbm">
                                        <label class="form-check-label small" for="cat_hbm">
                                            Hotel and Business Management (HBM)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category[]" value="COMPSTUD" id="cat_comp">
                                        <label class="form-check-label small" for="cat_comp">
                                            Computer Studies (COMPSTUD)
                                        </label>
                                    </div>
                                </div>
                                <small class="text-muted">Select one or more departments</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Same Book for All?</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sameBookToggle" checked>
                                    <label class="form-check-label" for="sameBookToggle">
                                        All copies are the same book
                                    </label>
                                </div>
                                <small class="text-muted">Toggle if you're adding different books</small>
                            </div>
                        </div>
                        
                        <!-- Dynamic ISBN Section -->
                        <div class="row" id="isbnSection">
                            <div class="col-12 mb-3">
                                <label class="form-label">ISBN(s)</label>
                                <div id="isbnContainer">
                                    <!-- ISBN inputs will be generated here -->
                                </div>
                                <small class="text-muted" id="isbnHelperText">
                                    <i class="fas fa-info-circle me-1"></i>
                                    ISBN fields generated based on quantity and book type
                                </small>
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
                                       placeholder="e.g., Mathematics, Physics, Programming">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Code</label>
                                <input type="text" class="form-control" name="course_code"
                                       placeholder="e.g., MATH-101, PHYS-201, COMP-301">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Year Level</label>
                                <div class="border rounded p-2" style="max-height: 100px; overflow-y: auto;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_level[]" value="First Year" id="year_1">
                                        <label class="form-check-label small" for="year_1">First Year</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_level[]" value="Second Year" id="year_2">
                                        <label class="form-check-label small" for="year_2">Second Year</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_level[]" value="Third Year" id="year_3">
                                        <label class="form-check-label small" for="year_3">Third Year</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_level[]" value="Fourth Year" id="year_4">
                                        <label class="form-check-label small" for="year_4">Fourth Year</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="year_level[]" value="Graduate" id="year_grad">
                                        <label class="form-check-label small" for="year_grad">Graduate</label>
                                    </div>
                                </div>
                                <small class="text-muted">Select applicable year levels</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Semester</label>
                                <div class="border rounded p-2" style="max-height: 100px; overflow-y: auto;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="semester[]" value="First Semester" id="sem_1">
                                        <label class="form-check-label small" for="sem_1">First Semester</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="semester[]" value="Second Semester" id="sem_2">
                                        <label class="form-check-label small" for="sem_2">Second Semester</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="semester[]" value="Summer" id="sem_summer">
                                        <label class="form-check-label small" for="sem_summer">Summer</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="semester[]" value="Midyear" id="sem_midyear">
                                        <label class="form-check-label small" for="sem_midyear">Midyear</label>
                                    </div>
                                </div>
                                <small class="text-muted">Select applicable semesters</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Section(s)</label>
                                <input type="text" class="form-control" name="section" 
                                       placeholder="e.g., A, B, 1A, 2B (comma-separated)">
                                <small class="text-muted">Separate multiple sections with commas</small>
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

                    <!-- Preview Section -->
                    <div class="mb-4" id="previewSection" style="display: none;">
                        <h6 class="text-warning mb-3"><i class="fas fa-eye me-2"></i>Preview: Records to be Created</h6>
                        <div class="alert alert-info">
                            <small>Based on your selections, <span id="recordCount">0</span> book record(s) will be created.</small>
                        </div>
                        <div id="previewList" class="small"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Add Book to Collection
                        </button>
                        <button type="button" class="btn btn-info" id="previewBtn">
                            <i class="fas fa-eye me-1"></i>Preview Records
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
        <!--<div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Dynamic ISBN Guide</h5>
            </div>
            <div class="card-body">
                <h6>How it works:</h6>
                <ul class="small">
                    <li><strong>Same Book:</strong> All copies have the same ISBN - only 1 ISBN field shown</li>
                    <li><strong>Different Books:</strong> Each copy can have different ISBN - multiple ISBN fields shown</li>
                    <li><strong>Quantity:</strong> Number of ISBN fields matches the quantity entered</li>
                </ul>
                
                <div class="alert alert-success alert-sm mt-3">
                    <small><i class="fas fa-toggle-on me-1"></i>
                    <strong>Same Book Example:</strong> 5 copies of "Physics Textbook" 
                    = 1 ISBN field (all copies share same ISBN)</small>
                </div>
                
                <div class="alert alert-warning alert-sm">
                    <small><i class="fas fa-toggle-off me-1"></i>
                    <strong>Different Books Example:</strong> 3 different reference books 
                    = 3 ISBN fields (each book has unique ISBN)</small>
                </div>
            </div>
        </div>-->

        <!-- Year Input Guide -->
        <!--<div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Publication Year Guide</h5>
            </div>
            <div class="card-body">
                <h6>Publication Year Tips:</h6>
                <ul class="small">
                    <li><strong>Optional Field:</strong> Not required but helpful for cataloging</li>
                    <li><strong>Format:</strong> 4-digit year (e.g., 2024, 2023)</li>
                    <li><strong>Range:</strong> 1800 to 2030 accepted</li>
                    <li><strong>Benefits:</strong> Helps identify book editions and relevance</li>
                </ul>
                
                <div class="alert alert-info alert-sm mt-3">
                    <small><i class="fas fa-lightbulb me-1"></i>
                    <strong>Tip:</strong> Recent publications (last 5 years) are often preferred 
                    for technical and scientific subjects.</small>
                </div>
            </div>
        </div>-->

        <!-- Department Guidelines Card -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Department Guidelines</h5>
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
                    <li>Select at least one department/category</li>
                    <li>Quantity must be at least 1</li>
                </ul>
                
                <h6 class="mt-3">Best Practices:</h6>
                <ul class="small text-muted">
                    <li>Use complete, official book titles</li>
                    <li>Include publication year when known</li>
                    <li>Include subject codes when available</li>
                    <li>Add detailed descriptions for better searchability</li>
                    <li>Use preview to check record combinations</li>
                </ul>

                <h6 class="mt-3">Examples:</h6>
                <ul class="small text-muted">
                    <li><strong>Section:</strong> <code>A, B, C</code> or <code>1A, 1B, 2A</code></li>
                    <li><strong>Course Code:</strong> <code>MATH-101</code> or <code>COMP-301</code></li>
                    <li><strong>Year:</strong> <code>2024</code> or <code>2023</code></li>
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
    const categories = document.querySelectorAll('input[name="category[]"]:checked');
    
    if (!title || !author || categories.length === 0) {
        e.preventDefault();
        alert('Please fill in all required fields (Title, Author, and at least one Category).');
        return false;
    }
    
    // Validate publication year if provided
    const pubYear = document.getElementById('publicationYear').value;
    if (pubYear && (pubYear < 1800 || pubYear > 2030)) {
        e.preventDefault();
        alert('Publication year must be between 1800 and 2030.');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding Book(s)...';
    submitBtn.disabled = true;
});

// Auto-fill current year for publication year
document.getElementById('publicationYear').addEventListener('focus', function() {
    if (!this.value) {
        const currentYear = new Date().getFullYear();
        this.placeholder = `Current year: ${currentYear}`;
    }
});

// Preview functionality with publication year
document.getElementById('previewBtn').addEventListener('click', function() {
    const categories = Array.from(document.querySelectorAll('input[name="category[]"]:checked')).map(cb => cb.value);
    const yearLevels = Array.from(document.querySelectorAll('input[name="year_level[]"]:checked')).map(cb => cb.value);
    const semesters = Array.from(document.querySelectorAll('input[name="semester[]"]:checked')).map(cb => cb.value);
    const sectionsInput = document.querySelector('input[name="section"]').value.trim();
    const sections = sectionsInput ? sectionsInput.split(',').map(s => s.trim()).filter(s => s) : [''];
    const totalQuantity = parseInt(document.querySelector('input[name="quantity"]').value) || 1;
    const sameBook = document.getElementById('sameBookToggle').checked;
    const publicationYear = document.getElementById('publicationYear').value;
    
    // Get ISBN data
    const isbnInputs = document.querySelectorAll('.isbn-input');
    const isbnData = Array.from(isbnInputs).map(input => input.value.trim()).filter(isbn => isbn);
    
    // Use default values if nothing selected
    const finalCategories = categories.length > 0 ? categories : [''];
    const finalYearLevels = yearLevels.length > 0 ? yearLevels : [''];
    const finalSemesters = semesters.length > 0 ? semesters : [''];
    const finalSections = sections.length > 0 ? sections : [''];
    
    let combinations = [];
    let recordCount = 0;
    
    // Calculate total academic combinations
    const academicCombinations = finalCategories.length * finalYearLevels.length * finalSemesters.length * finalSections.length;
    
    // Total records = quantity × academic combinations (each book copy gets record for each academic context)
    recordCount = totalQuantity * academicCombinations;
    
    // Generate preview combinations
    for (let bookIndex = 1; bookIndex <= totalQuantity; bookIndex++) {
        const bookISBN = sameBook && isbnData.length > 0 ? isbnData[0] : 
                        (isbnData[bookIndex - 1] || `Book ${bookIndex}`);
        
        finalCategories.forEach(category => {
            finalYearLevels.forEach(yearLevel => {
                finalSemesters.forEach(semester => {
                    finalSections.forEach(section => {
                        combinations.push({
                            bookNumber: bookIndex,
                            isbn: bookISBN,
                            category: category || 'Not specified',
                            yearLevel: yearLevel || 'Not specified',
                            semester: semester || 'Not specified',
                            section: section || 'Not specified',
                            publicationYear: publicationYear || 'Not specified'
                        });
                    });
                });
            });
        });
    }
    
    // Update preview
    document.getElementById('recordCount').textContent = recordCount;
    
    // Update preview section text
    const previewInfo = document.querySelector('#previewSection .alert-info small');
    previewInfo.innerHTML = `<strong>${recordCount}</strong> individual book record(s) will be created
        <br><small class="text-muted">
        ${totalQuantity} ${sameBook ? 'copies of the same book' : 'different books'} × 
        ${academicCombinations} academic context(s) = ${recordCount} total records
        ${publicationYear ? `<br>Publication Year: ${publicationYear}` : ''}
        </small>`;
    
    const previewList = document.getElementById('previewList');
    if (recordCount <= 20) {
        previewList.innerHTML = '<ul class="list-unstyled mb-0">' + 
            combinations.map((combo, index) => 
                `<li class="mb-1 p-2 bg-light rounded">
                    <span class="badge ${sameBook ? 'bg-success' : 'bg-primary'} me-2">
                        ${sameBook ? 'Copy' : 'Book'} ${combo.bookNumber}
                    </span>
                    <strong>${combo.category}</strong> - ${combo.yearLevel} - ${combo.semester} - Section ${combo.section}
                    ${combo.isbn !== `Book ${combo.bookNumber}` ? 
                        `<span class="badge bg-secondary ms-2">${combo.isbn}</span>` : ''}
                    ${combo.publicationYear !== 'Not specified' ? 
                        `<span class="badge bg-info ms-1">${combo.publicationYear}</span>` : ''}
                </li>`
            ).join('') + '</ul>';
    } else {
        previewList.innerHTML = `<p class="text-muted">Too many records to display (${recordCount} total). Preview shows first 10:</p>
            <ul class="list-unstyled mb-0">` + 
            combinations.slice(0, 10).map(combo => 
                `<li class="mb-1 p-2 bg-light rounded">
                    <span class="badge ${sameBook ? 'bg-success' : 'bg-primary'} me-2">
                        ${sameBook ? 'Copy' : 'Book'} ${combo.bookNumber}
                    </span>
                    <strong>${combo.category}</strong> - ${combo.yearLevel} - ${combo.semester} - Section ${combo.section}
                    ${combo.isbn !== `Book ${combo.bookNumber}` ? 
                        `<span class="badge bg-secondary ms-2">${combo.isbn}</span>` : ''}
                    ${combo.publicationYear !== 'Not specified' ? 
                        `<span class="badge bg-info ms-1">${combo.publicationYear}</span>` : ''}
                </li>`
            ).join('') + 
            `<li class="text-muted">... and ${recordCount - 10} more records</li></ul>`;
    }
    
    document.getElementById('previewSection').style.display = 'block';
    document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth' });
});

// Dynamic ISBN field generation
function generateISBNFields() {
    const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
    const sameBook = document.getElementById('sameBookToggle').checked;
    const container = document.getElementById('isbnContainer');
    
    // Clear existing fields
    container.innerHTML = '';
    
    if (sameBook) {
        // Single ISBN for all copies
        container.innerHTML = `
            <div class="isbn-field mb-2">
                <div class="input-group">
                    <span class="input-group-text bg-success text-white">
                        <i class="fas fa-barcode me-1"></i>All ${quantity} copies
                    </span>
                    <input type="text" class="form-control isbn-input" name="isbn" 
                           placeholder="Enter ISBN for all copies" data-book-index="all">
                </div>
            </div>
        `;
        
        // Add hidden field to indicate same book
        container.innerHTML += '<input type="hidden" name="same_book" value="true">';
        
        document.getElementById('isbnHelperText').innerHTML = 
            `<i class="fas fa-check-circle text-success me-1"></i>
             All ${quantity} copies will share the same ISBN`;
    } else {
        // Multiple ISBN fields for different books
        let fieldsHTML = '';
        for (let i = 1; i <= quantity; i++) {
            fieldsHTML += `
                <div class="isbn-field mb-2">
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">
                            <i class="fas fa-book me-1"></i>Book ${i}
                        </span>
                        <input type="text" class="form-control isbn-input" name="isbn[]" 
                               placeholder="Enter ISBN for book ${i}" data-book-index="${i}">
                    </div>
                </div>
            `;
        }
        container.innerHTML = fieldsHTML;
        
        // Add hidden field to indicate different books
        container.innerHTML += '<input type="hidden" name="same_book" value="false">';
        
        document.getElementById('isbnHelperText').innerHTML = 
            `<i class="fas fa-books text-warning me-1"></i>
             ${quantity} different books - each can have unique ISBN`;
    }
    
    // Add ISBN formatting to new fields
    container.querySelectorAll('.isbn-input').forEach(input => {
        input.addEventListener('input', formatISBN);
    });
}

// ISBN formatting function
function formatISBN(event) {
    let value = event.target.value.replace(/[^\d]/g, ''); // Remove non-digits
    
    if (value.length === 10) {
        // Format as ISBN-10
        event.target.value = value.replace(/(\d{3})(\d{1})(\d{3})(\d{3})(\d{1})/, '$1-$2-$3-$4-$5');
    } else if (value.length === 13) {
        // Format as ISBN-13
        event.target.value = value.replace(/(\d{3})(\d{1})(\d{2})(\d{6})(\d{1})/, '$1-$2-$3-$4-$5');
    }
}

// Event listeners
document.getElementById('quantityInput').addEventListener('input', generateISBNFields);
document.getElementById('sameBookToggle').addEventListener('change', generateISBNFields);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    generateISBNFields();
    
    // Set current year as default for publication year
    const currentYear = new Date().getFullYear();
    document.getElementById('publicationYear').setAttribute('placeholder', `e.g., ${currentYear}`);
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

// Publication year validation
document.getElementById('publicationYear').addEventListener('input', function() {
    const year = parseInt(this.value);
    const currentYear = new Date().getFullYear();
    
    // Remove any existing validation message
    let existingMsg = this.parentNode.querySelector('.year-validation');
    if (existingMsg) {
        existingMsg.remove();
    }
    
    if (this.value && (year < 1800 || year > 2030)) {
        const errorMsg = document.createElement('small');
        errorMsg.className = 'text-danger year-validation';
        errorMsg.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Year must be between 1800 and 2030';
        this.parentNode.appendChild(errorMsg);
        this.classList.add('is-invalid');
    } else if (this.value && year > currentYear) {
        const warningMsg = document.createElement('small');
        warningMsg.className = 'text-warning year-validation';
        warningMsg.innerHTML = '<i class="fas fa-info-circle me-1"></i>Future publication year detected';
        this.parentNode.appendChild(warningMsg);
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else if (this.value) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Reset form handler
document.querySelector('button[type="reset"]').addEventListener('click', function() {
    document.getElementById('previewSection').style.display = 'none';
    // Clear all checkboxes
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    // Reset same book toggle
    document.getElementById('sameBookToggle').checked = true;
    // Clear publication year validation
    document.getElementById('publicationYear').classList.remove('is-invalid', 'is-valid');
    const existingMsg = document.querySelector('.year-validation');
    if (existingMsg) {
        existingMsg.remove();
    }
    // Regenerate ISBN fields
    setTimeout(generateISBNFields, 100);
});

// Update preview when selections change
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox' || e.target.name === 'section' || e.target.id === 'quantityInput' || e.target.id === 'publicationYear') {
        // Hide preview when selections change
        document.getElementById('previewSection').style.display = 'none';
        
        // Regenerate ISBN fields if quantity changed
        if (e.target.id === 'quantityInput') {
            generateISBNFields();
        }
    }
});

// Quick fill suggestions for academic fields
const quickFillSuggestions = {
    'BIT': {
        subjects: ['Industrial Safety', 'Electronics', 'Mechanical Engineering', 'Manufacturing Technology', 'Quality Control'],
        courseCodes: ['BIT-101', 'BIT-201', 'BIT-301', 'BIT-401']
    },
    'EDUCATION': {
        subjects: ['Educational Psychology', 'Teaching Methods', 'Curriculum Development', 'Assessment Strategies', 'Child Development'],
        courseCodes: ['EDUC-101', 'EDUC-201', 'EDUC-301', 'EDUC-401']
    },
    'HBM': {
        subjects: ['Hotel Management', 'Business Administration', 'Tourism', 'Event Management', 'Customer Service'],
        courseCodes: ['HBM-101', 'HBM-201', 'HBM-301', 'HBM-401']
    },
    'COMPSTUD': {
        subjects: ['Programming', 'Database Systems', 'Web Development', 'Data Structures', 'Computer Networks'],
        courseCodes: ['COMP-101', 'COMP-201', 'COMP-301', 'COMP-401']
    }
};

// Add suggestions when category is selected
document.addEventListener('change', function(e) {
    if (e.target.name === 'category[]' && e.target.checked) {
        const category = e.target.value;
        const subjectInput = document.querySelector('input[name="subject_name"]');
        const courseCodeInput = document.querySelector('input[name="course_code"]');
        
        if (quickFillSuggestions[category]) {
            // Update placeholders with suggestions
            subjectInput.setAttribute('list', 'subject-suggestions');
            courseCodeInput.setAttribute('list', 'coursecode-suggestions');
            
            // Create or update datalists
            let subjectDatalist = document.getElementById('subject-suggestions');
            let courseCodeDatalist = document.getElementById('coursecode-suggestions');
            
            if (!subjectDatalist) {
                subjectDatalist = document.createElement('datalist');
                subjectDatalist.id = 'subject-suggestions';
                document.body.appendChild(subjectDatalist);
            }
            
            if (!courseCodeDatalist) {
                courseCodeDatalist = document.createElement('datalist');
                courseCodeDatalist.id = 'coursecode-suggestions';
                document.body.appendChild(courseCodeDatalist);
            }
            
            // Clear and populate datalists
            subjectDatalist.innerHTML = '';
            courseCodeDatalist.innerHTML = '';
            
            quickFillSuggestions[category].subjects.forEach(subject => {
                const option = document.createElement('option');
                option.value = subject;
                subjectDatalist.appendChild(option);
            });
            
            quickFillSuggestions[category].courseCodes.forEach(code => {
                const option = document.createElement('option');
                option.value = code;
                courseCodeDatalist.appendChild(option);
            });
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
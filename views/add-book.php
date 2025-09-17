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
    // Check if this is an archive action
    $is_archive_action = isset($_POST['archive_reason']) && !empty($_POST['archive_reason']);
    
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
    
    // Get archive reason if provided
    $archive_reason = isset($_POST['archive_reason']) ? $_POST['archive_reason'] : '';
    
    // Handle ISBN data - could be single ISBN or array of ISBNs
    $isbn_data = [];
    if (isset($_POST['isbn'])) {
        if (is_array($_POST['isbn'])) {
            $isbn_data = array_filter($_POST['isbn']); // Remove empty ISBNs
        } else {
            $isbn_data = [$_POST['isbn']];
        }
    }
    
    // Determine how to handle the books
    $same_book = isset($_POST['same_book']) && $_POST['same_book'] === 'true';
    $unique_isbns = array_unique(array_filter($isbn_data));
    $unique_book_count = count($unique_isbns);
    
    // Check if book should be archived (5+ years old)
    $publication_year = $_POST['publication_year'] ?? null;
    $current_year = date('Y');
    $should_archive = false;
    $archive_reason_display = '';
    
    if ($publication_year && ($current_year - $publication_year) >= 5) {
        $should_archive = true;
        $archive_reason_display = "Publication year: $publication_year (5+ years old)";
        
        // If archive reason was provided, use it
        if (!empty($archive_reason)) {
            $archive_reason_display = $archive_reason;
        }
    }
    
    // Create book records - ONE record per physical book copy
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
        
        // Convert arrays to comma-separated strings for storage
        $categories_str = !empty($categories) ? implode(',', $categories) : '';
        $year_levels_str = !empty($year_levels) ? implode(',', $year_levels) : '';
        $semesters_str = !empty($semesters) ? implode(',', $semesters) : '';
        $sections_str = !empty($sections) ? implode(',', $sections) : '';
        
        // Create single record per physical book with all applicable contexts
        $data = [
            'title' => $_POST['title'],
            'author' => $_POST['author'],
            'isbn' => $current_isbn,
            'category' => $categories_str, // Store as comma-separated string
            'quantity' => 1, // Each record represents 1 physical book
            'description' => $_POST['description'],
            'subject_name' => $_POST['subject_name'] ?? '',
            'semester' => $semesters_str, // Store as comma-separated string
            'section' => $sections_str, // Store as comma-separated string
            'year_level' => $year_levels_str, // Store as comma-separated string
            'course_code' => $_POST['course_code'] ?? '',
            'publication_year' => $publication_year,
            'book_copy_number' => $i + 1, // Track which copy this is
            'total_quantity' => $total_quantity, // Reference to total
            'is_multi_context' => (count($categories) > 1 || count($year_levels) > 1 || count($semesters) > 1 || count($sections) > 1) ? 1 : 0,
            'same_book_series' => $same_book ? 1 : 0
        ];
        
        // If book should be archived and user provided a reason, archive it
        if ($should_archive && !empty($archive_reason)) {
            $result = $book->archiveBook($data, $archive_reason, $_SESSION['user_name'] ?? 'System');
            
            if ($result) {
                $archived_count++;
            } else {
                $error_count++;
            }
        } else {
            // Add to regular collection
            $result = $book->addBook($data);
            
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }
    
    // Generate appropriate success message based on results
    if ($success_count > 0 || $archived_count > 0) {
        $book_type = $same_book ? "copies of the same book" : "individual books";
        
        // Calculate total academic contexts
        $total_contexts = count($categories) * count($year_levels) * count($semesters) * count($sections);
        $context_info = "";
        if ($total_contexts > 1) {
            $context_info = " (applicable to {$total_contexts} academic contexts)";
        }
        
        if ($archived_count > 0 && $success_count > 0) {
            // Both active and archived books were added
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection and {$archived_count} to archives{$context_info}!";
            $_SESSION['message_type'] = 'warning'; 
        } elseif ($archived_count > 0) {
            // Only archived books were added
            $total_books_message = "Successfully added {$archived_count} {$book_type} to archives{$context_info}!";
            $_SESSION['message_type'] = 'info'; 
        } else {
            // Only active books were added
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection{$context_info}!";
            $_SESSION['message_type'] = 'success';
        }
        
        $_SESSION['message'] = $total_books_message;
        
        // Add error information if any
        if ($error_count > 0) {
            $_SESSION['message'] .= " ({$error_count} failed)";
        }
        
        // Add context information
        if ($total_contexts > 1) {
            $context_details = [];
            if (count($categories) > 1) $context_details[] = count($categories) . " departments";
            if (count($year_levels) > 1) $context_details[] = count($year_levels) . " year levels";
            if (count($semesters) > 1) $context_details[] = count($semesters) . " semesters";
            if (count($sections) > 1) $context_details[] = count($sections) . " sections";
            
            $_SESSION['context_info'] = [
                'details' => implode(', ', $context_details),
                'message' => "Each book copy is applicable to: " . implode(', ', $context_details)
            ];
        }
        
        // Add archive notification if applicable
        if ($archived_count > 0) {
            $_SESSION['archive_info'] = [
                'count' => $archived_count,
                'message' => "Note: {$archived_count} books were manually archived with reason: '{$archive_reason}'"
            ];
        }
        
        header('Location: books.php');
        exit;
    } else {
        $error_message = 'Failed to add book. Please try again.';
    }
}

// Now set page title and include header
$page_title = "Add Book - LibCollect: Reference Mapping System";
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
                                <small class="text-muted">Number of physical book copies</small>
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
                                <small class="text-muted">Select applicable departments</small>
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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Multiple Selection Support:</strong> You can select multiple options in each category. 
                            Each book copy will be applicable to ALL selected combinations.
                        </div>
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
                        <h6 class="text-warning mb-3"><i class="fas fa-eye me-2"></i>Preview: Book Copies and Contexts</h6>
                        <div class="alert alert-info">
                            <small><span id="physicalCopies">0</span> physical book copies will be added, each applicable to <span id="contextCount">0</span> academic context(s).</small>
                        </div>
                        <div id="previewList" class="small"></div>
                    </div>

                    <!-- Archive Reason Section  -->
                    <div class="mb-4" id="archiveSection" style="display: none;">
                        <h6 class="text-warning mb-3"><i class="fas fa-archive me-2"></i>Archive This Book</h6>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>This book is 5+ years old.</strong> Please select a reason for archiving it instead of adding to the active collection.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Archive Reason *</label>
                            <div class="border rounded p-3 bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Donated" id="reason_donated">
                                            <label class="form-check-label" for="reason_donated">
                                                <i class="fas fa-hands-helping text-success me-1"></i>Donated
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Outdated" id="reason_outdated">
                                            <label class="form-check-label" for="reason_outdated">
                                                <i class="fas fa-calendar-times text-warning me-1"></i>Outdated
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Obsolete" id="reason_obsolete">
                                            <label class="form-check-label" for="reason_obsolete">
                                                <i class="fas fa-ban text-secondary me-1"></i>Obsolete
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Damaged" id="reason_damaged">
                                            <label class="form-check-label" for="reason_damaged">
                                                <i class="fas fa-exclamation-triangle text-danger me-1"></i>Damaged
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Lost" id="reason_lost">
                                            <label class="form-check-label" for="reason_lost">
                                                <i class="fas fa-search text-muted me-1"></i>Lost
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Low usage" id="reason_low_usage">
                                            <label class="form-check-label" for="reason_low_usage">
                                                <i class="fas fa-chart-line text-info me-1"></i>Low usage
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Availability of more recent edition" id="reason_recent_edition">
                                            <label class="form-check-label" for="reason_recent_edition">
                                                <i class="fas fa-sync-alt text-primary me-1"></i>Availability of more recent edition
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="Title ceased publication" id="reason_ceased">
                                            <label class="form-check-label" for="reason_ceased">
                                                <i class="fas fa-stop-circle text-dark me-1"></i>Title ceased publication
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Others option with custom text input -->
                                <div class="form-check mb-2">
                                    <input class="form-check-input archive-reason-radio" type="radio" name="archive_reason" value="custom" id="reason_others">
                                    <label class="form-check-label" for="reason_others">
                                        <i class="fas fa-edit text-secondary me-1"></i>Others:
                                    </label>
                                </div>
                                
                                <!-- Custom reason text area (hidden by default) -->
                                <div class="mt-3" id="customReasonSection" style="display: none;">
                                    <label for="customArchiveReason" class="form-label small text-muted">
                                        <i class="fas fa-pencil-alt me-1"></i>Please specify the reason:
                                    </label>
                                    <textarea class="form-control form-control-sm" 
                                            id="customArchiveReason" 
                                            name="custom_archive_reason" 
                                            rows="2" 
                                            placeholder="Enter your custom archive reason here..."
                                            maxlength="200"></textarea>
                                    <small class="text-muted">Maximum 200 characters</small>
                                    <div class="small text-muted mt-1" id="customReasonCounter">0/200 characters</div>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Required for books published 5+ years ago. This helps maintain proper library records.
                            </small>
                        </div>
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
                    <li>Select all applicable academic contexts</li>
                    <li>Add detailed descriptions for better searchability</li>
                    <li>Use preview to verify before adding</li>
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
// Handle archive reason selection and custom input
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('archive-reason-radio')) {
        const customSection = document.getElementById('customReasonSection');
        const customTextArea = document.getElementById('customArchiveReason');
        
        if (e.target.value === 'custom') {
            // Show custom input section
            customSection.style.display = 'block';
            customTextArea.setAttribute('required', 'required');
            customSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            // Hide custom input section
            customSection.style.display = 'none';
            customTextArea.removeAttribute('required');
            customTextArea.value = '';
        }
        
        // Update the visual feedback
        const selectedLabel = e.target.nextElementSibling;
        const allLabels = document.querySelectorAll('.archive-reason-radio + label');
        
        // Reset all labels
        allLabels.forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
        
        // Highlight selected label
        if (selectedLabel) {
            selectedLabel.classList.add('text-primary', 'fw-bold');
        }
    }
});

// Character counter for custom archive reason
document.addEventListener('DOMContentLoaded', function() {
    const customArchiveReason = document.getElementById('customArchiveReason');
    if (customArchiveReason) {
        customArchiveReason.addEventListener('input', function() {
            const maxLength = 200;
            const currentLength = this.value.length;
            const counter = document.getElementById('customReasonCounter');
            
            if (counter) {
                counter.textContent = `${currentLength}/${maxLength} characters`;
                
                if (currentLength > maxLength) {
                    counter.className = 'small text-danger mt-1';
                    this.classList.add('is-invalid');
                } else if (currentLength > 160) {
                    counter.className = 'small text-warning mt-1';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    counter.className = 'small text-muted mt-1';
                    this.classList.remove('is-invalid', 'is-valid');
                }
            }
        });
    }
});

// Form validation and enhancement (updated to handle archive reason checklist)
document.getElementById('addBookForm').addEventListener('submit', function(e) {
    const archiveSection = document.getElementById('archiveSection');
    
    // If archive section is visible, validate archive reason selection
    if (archiveSection && archiveSection.style.display !== 'none') {
        const selectedReason = document.querySelector('input[name="archive_reason"]:checked');
        
        if (!selectedReason) {
            e.preventDefault();
            alert('Please select an archive reason for this book.');
            return false;
        }
        
        // If "Others" is selected, validate custom reason
        if (selectedReason.value === 'custom') {
            const customReason = document.getElementById('customArchiveReason').value.trim();
            if (!customReason) {
                e.preventDefault();
                alert('Please provide a custom archive reason.');
                document.getElementById('customArchiveReason').focus();
                return false;
            }
            
            // Update the form to send the custom reason as the archive_reason
            selectedReason.value = customReason;
        }
    }
    
    // Continue with existing validation...
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

// Updated Preview functionality
document.getElementById('previewBtn').addEventListener('click', function() {
    const categories = Array.from(document.querySelectorAll('input[name="category[]"]:checked')).map(cb => cb.value);
    const yearLevels = Array.from(document.querySelectorAll('input[name="year_level[]"]:checked')).map(cb => cb.value);
    const semesters = Array.from(document.querySelectorAll('input[name="semester[]"]:checked')).map(cb => cb.value);
    const sectionsInput = document.querySelector('input[name="section"]').value.trim();
    const sections = sectionsInput ? sectionsInput.split(',').map(s => s.trim()).filter(s => s) : [];
    const totalQuantity = parseInt(document.querySelector('input[name="quantity"]').value) || 1;
    const sameBook = document.getElementById('sameBookToggle').checked;
    const publicationYear = document.getElementById('publicationYear').value;
    
    // Get ISBN data
    const isbnInputs = document.querySelectorAll('.isbn-input');
    const isbnData = Array.from(isbnInputs).map(input => input.value.trim()).filter(isbn => isbn);
    
    // Calculate academic contexts
    const finalCategories = categories.length > 0 ? categories : ['Not specified'];
    const finalYearLevels = yearLevels.length > 0 ? yearLevels : ['Not specified'];
    const finalSemesters = semesters.length > 0 ? semesters : ['Not specified'];
    const finalSections = sections.length > 0 ? sections : ['Not specified'];
    
    const totalContexts = finalCategories.length * finalYearLevels.length * finalSemesters.length * finalSections.length;
    
    // Update preview
    document.getElementById('physicalCopies').textContent = totalQuantity;
    document.getElementById('contextCount').textContent = totalContexts;
    
    // Generate preview list showing book copies and their applicable contexts
    const previewList = document.getElementById('previewList');
    let previewHTML = '<div class="row">';
    
    // Show book copies
    previewHTML += '<div class="col-md-6"><h6 class="text-primary">Physical Book Copies:</h6><ul class="list-unstyled">';
    for (let i = 1; i <= totalQuantity; i++) {
        const bookISBN = sameBook && isbnData.length > 0 ? isbnData[0] : 
                        (isbnData[i - 1] || 'No ISBN');
        previewHTML += `
            <li class="mb-2 p-2 bg-light rounded">
                <span class="badge ${sameBook ? 'bg-success' : 'bg-primary'} me-2">
                    ${sameBook ? 'Copy' : 'Book'} ${i}
                </span>
                ${bookISBN !== 'No ISBN' ? 
                    `<span class="badge bg-secondary ms-1">${bookISBN}</span>` : ''}
                ${publicationYear ? 
                    `<span class="badge bg-info ms-1">${publicationYear}</span>` : ''}
            </li>`;
    }
    previewHTML += '</ul></div>';
    
    // Show applicable contexts
    previewHTML += '<div class="col-md-6"><h6 class="text-success">Applicable Academic Contexts:</h6>';
    if (totalContexts <= 10) {
        previewHTML += '<ul class="list-unstyled small">';
        finalCategories.forEach(category => {
            finalYearLevels.forEach(yearLevel => {
                finalSemesters.forEach(semester => {
                    finalSections.forEach(section => {
                        previewHTML += `
                            <li class="mb-1 p-1 bg-success bg-opacity-10 rounded">
                                <strong>${category}</strong> - ${yearLevel} - ${semester} - Section ${section}
                            </li>`;
                    });
                });
            });
        });
        previewHTML += '</ul>';
    } else {
        previewHTML += `<div class="alert alert-info">
                <small>Too many contexts to display individually (${totalContexts} total)</small>
            </div>
            <ul class="list-unstyled small">
                <li><strong>Departments:</strong> ${finalCategories.join(', ')}</li>
                <li><strong>Year Levels:</strong> ${finalYearLevels.join(', ')}</li>
                <li><strong>Semesters:</strong> ${finalSemesters.join(', ')}</li>
                <li><strong>Sections:</strong> ${finalSections.join(', ')}</li>
            </ul>`;
    }
    previewHTML += '</div></div>';
    
    // Add summary information
    previewHTML += `<div class="col-12 mt-3">
        <div class="alert alert-success">
            <h6 class="mb-2"><i class="fas fa-check-circle me-2"></i>Summary</h6>
            <ul class="mb-0 small">
                <li><strong>${totalQuantity}</strong> physical book ${totalQuantity === 1 ? 'copy' : 'copies'} will be added</li>
                <li>Each copy applicable to <strong>${totalContexts}</strong> academic context${totalContexts === 1 ? '' : 's'}</li>
                <li><strong>No duplicate copies</strong> - only real physical books are created</li>
                <li><strong>Maximum availability</strong> - books usable across all selected contexts</li>
            </ul>
        </div>
    </div>`;
    
    previewHTML += '</div>';
    previewList.innerHTML = previewHTML;
    
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

// Updated checkArchiveStatus function to handle the new checklist UI
function checkArchiveStatus() {
    const publicationYear = document.getElementById('publicationYear').value;
    const currentYear = new Date().getFullYear();
    const archiveSection = document.getElementById('archiveSection');
    
    if (publicationYear && (currentYear - parseInt(publicationYear)) >= 5) {
        archiveSection.style.display = 'block';
        // Reset any previously selected archive reasons
        document.querySelectorAll('.archive-reason-radio').forEach(radio => {
            radio.checked = false;
        });
        const customSection = document.getElementById('customReasonSection');
        const customTextArea = document.getElementById('customArchiveReason');
        if (customSection) customSection.style.display = 'none';
        if (customTextArea) customTextArea.value = '';
        
        // Reset label styles
        const allLabels = document.querySelectorAll('.archive-reason-radio + label');
        allLabels.forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
    } else {
        archiveSection.style.display = 'none';
        // Clear any selected archive reasons
        document.querySelectorAll('.archive-reason-radio').forEach(radio => {
            radio.checked = false;
        });
        const customSection = document.getElementById('customReasonSection');
        if (customSection) customSection.style.display = 'none';
    }
}

// Event listener for publication year changes
document.getElementById('publicationYear').addEventListener('input', function() {
    checkArchiveStatus();
    
    // Also update the preview if it's visible
    if (document.getElementById('previewSection').style.display !== 'none') {
        document.getElementById('previewBtn').click();
    }
});

// ISBN formatting function
function formatISBN(event) {
    let value = event.target.value.replace(/[^\d]/g, ''); 
    
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
    
    // Add helpful tooltips to checkboxes
    const checkboxGroups = {
        'category[]': 'Books will be available to all selected departments',
        'year_level[]': 'Books will be suitable for all selected year levels',
        'semester[]': 'Books will be available during all selected semesters'
    };
    
    Object.keys(checkboxGroups).forEach(name => {
        const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
        checkboxes.forEach(checkbox => {
            checkbox.setAttribute('title', checkboxGroups[name]);
        });
    });
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

// Updated reset form handler to handle archive reason checkboxes
document.querySelector('button[type="reset"]').addEventListener('click', function() {
    // Reset archive reason selections
    document.querySelectorAll('.archive-reason-radio').forEach(radio => {
        radio.checked = false;
    });
    const customSection = document.getElementById('customReasonSection');
    const customTextArea = document.getElementById('customArchiveReason');
    if (customSection) customSection.style.display = 'none';
    if (customTextArea) customTextArea.value = '';
    
    // Reset label styles
    const allLabels = document.querySelectorAll('.archive-reason-radio + label');
    allLabels.forEach(label => {
        label.classList.remove('text-primary', 'fw-bold');
    });
    
    // Hide archive section
    const archiveSection = document.getElementById('archiveSection');
    if (archiveSection) archiveSection.style.display = 'none';
    
    // Continue with existing reset functionality...
    document.getElementById('previewSection').style.display = 'none';
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    document.getElementById('sameBookToggle').checked = true;
    document.getElementById('publicationYear').classList.remove('is-invalid', 'is-valid');
    const existingMsg = document.querySelector('.year-validation');
    if (existingMsg) {
        existingMsg.remove();
    }
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

// Visual feedback for multi-selection
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox' && e.target.name.includes('[]')) {
        const groupName = e.target.name;
        const checkedCount = document.querySelectorAll(`input[name="${groupName}"]:checked`).length;
        const container = e.target.closest('.border.rounded') || e.target.closest('.col-md-3, .col-md-4');
        
        if (container) {
            const label = container.querySelector('label.form-label');
            if (label && checkedCount > 0) {
                label.innerHTML = label.textContent.split(' (')[0] + ` (${checkedCount} selected)`;
            } else if (label && checkedCount === 0) {
                label.innerHTML = label.textContent.split(' (')[0];
            }
        }
    }
});

// Highlight academic information section when selections are made
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox') {
        const academicSection = document.querySelector('h6.text-success');
        if (academicSection) {
            academicSection.style.transition = 'all 0.3s ease';
            academicSection.style.color = '#198754';
            academicSection.style.textShadow = '0 0 10px rgba(25, 135, 84, 0.3)';
            
            setTimeout(() => {
                academicSection.style.textShadow = 'none';
            }, 1000);
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
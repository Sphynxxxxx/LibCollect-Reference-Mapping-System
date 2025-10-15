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
    
    $success_count = 0;
    $error_count = 0;
    $pending_count = 0;
    $total_quantity = $_POST['quantity'];
    
    // Handle ISBN data - could be single ISBN or array of ISBNs
    $isbn_data = [];
    if (isset($_POST['isbn'])) {
        if (is_array($_POST['isbn'])) {
            $isbn_data = array_filter($_POST['isbn']);
        } else {
            $isbn_data = [$_POST['isbn']];
        }
    }
    
    // Determine how to handle the books
    $same_book = isset($_POST['same_book']) && $_POST['same_book'] === 'true';
    $unique_isbns = array_unique(array_filter($isbn_data));
    
    // Check if book should go to pending archives (5+ years old)
    $publication_year = $_POST['publication_year'] ?? null;
    $current_year = date('Y');
    $should_pending_archive = false;
    
    if ($publication_year && ($current_year - $publication_year) >= 5) {
        $should_pending_archive = true;
    }
    
    // Create book records - ONE record per physical book copy
    for ($i = 0; $i < $total_quantity; $i++) {
        // Determine ISBN for this book copy
        $current_isbn = '';
        if ($same_book && !empty($unique_isbns)) {
            $current_isbn = $unique_isbns[0];
        } else if (!empty($isbn_data)) {
            $isbn_index = $i % count($isbn_data);
            $current_isbn = $isbn_data[$isbn_index];
        }
        
        // Convert arrays to comma-separated strings for storage
        $categories_str = !empty($categories) ? implode(',', $categories) : '';
        $year_levels_str = !empty($year_levels) ? implode(',', $year_levels) : '';
        $semesters_str = !empty($semesters) ? implode(',', $semesters) : '';
        
        // Get program value from form
        $program = isset($_POST['selected_program']) ? $_POST['selected_program'] : '';
        
        // Create single record per physical book with all applicable contexts
        $data = [
            'title' => $_POST['title'],
            'author' => $_POST['author'],
            'isbn' => $current_isbn,
            'category' => $categories_str,
            'program' => $program,  // ADD THIS LINE
            'quantity' => 1,
            'description' => $_POST['description'],
            'subject_name' => $_POST['subject_name'] ?? '',
            'semester' => $semesters_str,
            'section' => '',
            'year_level' => $year_levels_str,
            'course_code' => $_POST['course_code'] ?? '',
            'publication_year' => $publication_year,
            'book_copy_number' => $i + 1,
            'total_quantity' => $total_quantity,
            'is_multi_context' => (count($categories) > 1 || count($year_levels) > 1 || count($semesters) > 1) ? 1 : 0,
            'same_book_series' => $same_book ? 1 : 0
        ];
        
        // If book is 5+ years old, add to PENDING archives
        if ($should_pending_archive) {
            try {
                $sql = "INSERT INTO pending_archives (title, author, isbn, category, program, quantity, description, 
                        subject_name, semester, section, year_level, course_code, publication_year, 
                        book_copy_number, total_quantity, is_multi_context, same_book_series) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  // ADD ? for program
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $data['title'],
                    $data['author'],
                    $data['isbn'],
                    $data['category'],
                    $data['program'],  // ADD THIS LINE
                    $data['quantity'],
                    $data['description'],
                    $data['subject_name'],
                    $data['semester'],
                    $data['section'],
                    $data['year_level'],
                    $data['course_code'],
                    $data['publication_year'],
                    $data['book_copy_number'],
                    $data['total_quantity'],
                    $data['is_multi_context'],
                    $data['same_book_series']
                ]);
                
                if ($result) {
                    $pending_count++;
                } else {
                    $error_count++;
                }
            } catch (PDOException $e) {
                error_log("Error adding to pending archives: " . $e->getMessage());
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
    if ($success_count > 0 || $pending_count > 0) {
        $book_type = $same_book ? "copies of the same book" : "individual books";
        
        // Calculate total academic contexts
        $total_contexts = count($categories) * count($year_levels) * count($semesters);
        $context_info = "";
        if ($total_contexts > 1) {
            $context_info = " (applicable to {$total_contexts} academic contexts)";
        }
        
        if ($pending_count > 0 && $success_count > 0) {
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection and {$pending_count} to pending archives{$context_info}!";
            $message_type = 'warning'; 
        } elseif ($pending_count > 0) {
            $total_books_message = "Successfully added {$pending_count} {$book_type} to pending archives (5+ years old){$context_info}!";
            $message_type = 'info'; 
        } else {
            $total_books_message = "Successfully added {$success_count} {$book_type} to active collection{$context_info}!";
            $message_type = 'success';
        }
        
        // Store success message (don't redirect)
        $success_message = $total_books_message;
        
        if ($error_count > 0) {
            $success_message .= " ({$error_count} failed)";
        }
        
        // Additional messages
        if ($total_contexts > 1) {
            $context_details = [];
            if (count($categories) > 1) $context_details[] = count($categories) . " departments";
            if (count($year_levels) > 1) $context_details[] = count($year_levels) . " year levels";
            if (count($semesters) > 1) $context_details[] = count($semesters) . " semesters";
            
            $context_details_message = "Each book copy is applicable to: " . implode(', ', $context_details);
        }
        
        if ($pending_count > 0) {
            $pending_message = "⚠️ {$pending_count} books sent to Pending Archives (5+ years old). Visit <a href='archives.php?tab=pending'>Archives page</a> to select archive reasons and complete the archiving process.";
        }
        
        // REMOVED: header('Location: books.php'); exit;
        // Form will stay filled with the same data
    } else {
        $error_message = 'Failed to add book. Please try again.';
    }
}

$page_title = "LibCollect: Reference Mapping System - Add Book";
include '../includes/header.php';
?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($success_message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Success!</strong> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    
    <?php if (isset($context_details_message)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <?php echo $context_details_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($pending_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php echo $pending_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
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
                        
                        <!-- Auto-archive notification -->
                        <div class="alert alert-info" id="autoArchiveNotice" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Books published 5+ years ago will be automatically archived upon addition.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Book Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                    placeholder="Enter complete book title"
                                    value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" required 
                                    placeholder="Author's full name"
                                    value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity" id="quantityInput" min="1" 
                                    value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>" required>
                                <small class="text-muted">Number of physical book copies</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Publication Year</label>
                                <input type="number" class="form-control" name="publication_year" id="publicationYear" 
                                    min="1800" max="2030" placeholder="e.g., 2024"
                                    value="<?php echo isset($_POST['publication_year']) ? htmlspecialchars($_POST['publication_year']) : ''; ?>">
                                <small class="text-muted">Year the book was published</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Same Book for All?</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sameBookToggle" 
                                        <?php echo (!isset($_POST['same_book']) || $_POST['same_book'] === 'true') ? 'checked' : ''; ?>>
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
                                <label class="form-label">Call no.</label>
                                <div id="isbnContainer">
                                    <!-- ISBN inputs will be generated here -->
                                </div>
                                <small class="text-muted" id="isbnHelperText">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Call no. fields generated based on quantity and book type
                                </small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Department/Category *</label>
                                <div class="border rounded p-2" style="max-height: 120px; overflow-y: auto;">
                                    <?php
                                    $selected_categories = isset($_POST['category']) ? $_POST['category'] : [];
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input department-checkbox" type="checkbox" name="category[]" value="BIT" id="cat_bit"
                                            <?php echo in_array('BIT', $selected_categories) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="cat_bit">
                                            Bachelor of Industrial Technology (BIT)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input department-checkbox" type="checkbox" name="category[]" value="EDUCATION" id="cat_edu"
                                            <?php echo in_array('EDUCATION', $selected_categories) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="cat_edu">
                                            Education
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input department-checkbox" type="checkbox" name="category[]" value="HBM" id="cat_hbm"
                                            <?php echo in_array('HBM', $selected_categories) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="cat_hbm">
                                            Hotel and Business Management (HBM)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input department-checkbox" type="checkbox" name="category[]" value="COMPSTUD" id="cat_comp"
                                            <?php echo in_array('COMPSTUD', $selected_categories) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small" for="cat_comp">
                                            Computer Studies (COMPSTUD)
                                        </label>
                                    </div>
                                </div>
                                <small class="text-muted">Select one department to see courses</small>
                            </div>
                        </div>
                        
                    </div>

                    <!-- Academic Information -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                        
                        <?php
                        // Get submitted values
                        $submitted_program = isset($_POST['course_code']) ? $_POST['course_code'] : '';
                        $submitted_year_filter = '';
                        $submitted_semester_filter = '';
                        
                        // Extract year and semester from submitted data
                        if (isset($_POST['year_level']) && is_array($_POST['year_level']) && !empty($_POST['year_level'])) {
                            $submitted_year_filter = $_POST['year_level'][0];
                        }
                        if (isset($_POST['semester']) && is_array($_POST['semester']) && !empty($_POST['semester'])) {
                            $submitted_semester_filter = $_POST['semester'][0];
                        }
                        ?>
                        
                        <!-- Program Selection - Initially Hidden -->
                        <div id="programSection" style="display: <?php echo !empty($selected_categories) ? 'block' : 'none'; ?>;">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Select Program *</label>
                                    <select class="form-select" id="programSelect" name="selected_program">
                                        <option value="">-- Select a program --</option>
                                        <!-- Options will be populated by JavaScript and selection will be restored -->
                                    </select>
                                    <small class="text-muted">Select a program to continue</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Year Level and Semester Selection - Initially Hidden -->
                        <div id="yearSemesterFilterSection" style="display: <?php echo !empty($submitted_program) ? 'block' : 'none'; ?>;">
                            <div class="alert alert-info">
                                <i class="fas fa-filter me-2"></i>
                                <strong>Filter Subjects:</strong> Select year level and semester to see available subjects
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select Year Level *</label>
                                    <select class="form-select" id="yearLevelFilter" name="selected_year_level">
                                        <option value="">-- Select year level --</option>
                                        <option value="First Year" <?php echo $submitted_year_filter === 'First Year' ? 'selected' : ''; ?>>First Year</option>
                                        <option value="Second Year" <?php echo $submitted_year_filter === 'Second Year' ? 'selected' : ''; ?>>Second Year</option>
                                        <option value="Third Year" <?php echo $submitted_year_filter === 'Third Year' ? 'selected' : ''; ?>>Third Year</option>
                                        <option value="Fourth Year" <?php echo $submitted_year_filter === 'Fourth Year' ? 'selected' : ''; ?>>Fourth Year</option>
                                        <option value="Graduate" <?php echo $submitted_year_filter === 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select Semester *</label>
                                    <select class="form-select" id="semesterFilter" name="selected_semester">
                                        <option value="">-- Select semester --</option>
                                        <option value="First Semester" <?php echo $submitted_semester_filter === 'First Semester' ? 'selected' : ''; ?>>First Semester</option>
                                        <option value="Second Semester" <?php echo $submitted_semester_filter === 'Second Semester' ? 'selected' : ''; ?>>Second Semester</option>
            
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subject Selection - Initially Hidden -->
                        <div id="subjectSection" style="display: <?php echo !empty($submitted_year_filter) && !empty($submitted_semester_filter) ? 'block' : 'none'; ?>;">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Select Subject *</label>
                                    <select class="form-select" id="subjectSelect" name="selected_subject">
                                        <option value="">-- Select a subject --</option>
                                        <!-- Options will be populated by JavaScript -->
                                    </select>
                                    <small class="text-muted" id="subjectCount"></small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Course Code</label>
                                    <input type="text" class="form-control" name="course_code" readonly
                                        placeholder="Course code will appear here"
                                        value="<?php echo isset($_POST['course_code']) ? htmlspecialchars($_POST['course_code']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Name</label>
                                    <input type="text" class="form-control" name="subject_name" readonly
                                        placeholder="Subject name will appear here"
                                        value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Year Level and Semester Display (Hidden inputs for form submission) -->
                        <div id="yearSemesterSection" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-2" id="yearLevelContainer" style="display: none;">
                                        <!-- Year level checkbox will be populated here -->
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-2" id="semesterContainer" style="display: none;">
                                        <!-- Semester checkbox will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4" 
                                placeholder="Brief description of the book content, learning objectives, or course relevance..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <!-- Preview Section -->
                    <div class="mb-4" id="previewSection" style="display: none;">
                        <h6 class="text-warning mb-3"><i class="fas fa-eye me-2"></i>Preview: Book Copies and Contexts</h6>
                        <div class="alert alert-info">
                            <small><span id="physicalCopies">0</span> physical book copies will be added, each applicable to <span id="contextCount">0</span> academic context(s).</small>
                        </div>
                        <div id="previewList" class="small"></div>
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
                    <li>Select ONE department to see courses</li>
                    <li>Quantity must be at least 1</li>
                </ul>
                
                <h6 class="mt-3">Auto-Archive Policy:</h6>
                <ul class="small text-muted">
                    <li>Books 5+ years old are automatically archived</li>
                    <li>Archived books remain searchable</li>
                    <li>Can be restored from archives anytime</li>
                </ul>

                <h6 class="mt-3">Course Selection:</h6>
                <ul class="small text-muted">
                    <li>Select ONE department first</li>
                    <li>Then select a course to see details</li>
                    <li>Year level and semester will auto-populate</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let currentDepartment = null;
let currentProgram = null;

const courseData = {
    'COMPSTUD': {
        'BSIS': {
            name: 'Bachelor of Science in Information Systems',
            courses: [
                {code: 'CS 1', name: 'Programming Logic Formulation', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 102', name: 'Introduction to Computing', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'National Service Training Program 1', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 103', name: 'Fundamentals of Programming', year: 'First Year', semester: 'Second Semester'},
                {code: 'ICT 141', name: 'Principles of Accounting', year: 'First Year', semester: 'Second Semester'},
                {code: 'IS 101', name: 'Fundamentals of Information Systems', year: 'First Year', semester: 'Second Semester'},
                {code: 'IS 102', name: 'Organization and Management Concepts', year: 'First Year', semester: 'Second Semester'},
                {code: 'IS 103', name: 'Professional Issues in Information Systems', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise-Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'National Service Training Program 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'ICT 104', name: 'Intermediate Programming', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 116', name: 'Human Computer Interaction 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 107', name: 'Data Structure and Algorithms', year: 'Second Year', semester: 'First Semester'},
                {code: 'IS 104', name: 'IT Infrastructure and Network Technologies', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENG 3', name: 'Technical Writing with Oral Communication', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 109', name: 'Information Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 138', name: 'Systems Analysis and Design', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IS 105', name: 'Financial Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 107', name: 'Quantitative Methods', year: 'Third Year', semester: 'First Semester'},
                {code: 'IS 106', name: 'Business Process Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'IS 107', name: 'Web Development', year: 'Third Year', semester: 'First Semester'},
                {code: 'IS 108', name: 'IT Security and Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science, Technology and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'IS 109', name: 'Enterprise Systems', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IS 110', name: 'Evaluation or Business Performance', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IS 111', name: 'IS Project Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IS 128', name: 'Capstone Project 1 Track 4', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Third Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ICT 110', name: 'Applications Development and Emerging Technologies', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 112', name: 'Enterprise Architecture', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 113', name: 'IS Strategy Management and Acquisition', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 114', name: 'Customer Relationship Management', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 126', name: 'Capstone Project 2', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 127', name: 'Internship/Practicum', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BSIT': {
            name: 'Bachelor of Science in Information Technology',
            courses: [
                {code: 'CS 1', name: 'Programming Logic Formulation', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 102', name: 'Introduction to Computing', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT136', name: 'Social Issues and Professional Practice 1', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1/ LTS 1 / CWTS', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 103', name: 'Fundamentals of Programming', year: 'First Year', semester: 'Second Semester'},
                {code: 'IT 102', name: 'Computer Electronics and Digital Circuits', year: 'First Year', semester: 'Second Semester'},
                {code: 'MATH 122', name: 'Discrete Mathematics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise-Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'ICT 104', name: 'Intermediate Programming 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 141', name: 'Principles of Accounting', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 107', name: 'Data Structure and Algorithms', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENG 3', name: 'Technical Writing with Oral Communication', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'ICT 109', name: 'Information Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 111', name: 'Object Oriented Programming', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 138', name: 'Systems Analysis and Design', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 103', name: 'Networking 1', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IT 104', name: 'Platform Technologies', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IT 109', name: 'Integrative Programming and Technologies 1', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 137', name: 'Quantitative Methods', year: 'Third Year', semester: 'First Semester'},
                {code: 'ICT 105', name: 'Web Systems and Technologies 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'IS 107', name: 'Networking 2', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'ICT 108', name: 'Information Assurance and Security 1', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ICT 116', name: 'Human Computer Interactions 1', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IT 108', name: 'Advanced Database Systems', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IS 1', name: 'Capstone Project 1', year: 'Third Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ICT 110', name: 'Applications Development and Emerging Technologies', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ICT 139', name: 'Information Assurance and Security 2', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IT 110', name: 'System Administration and Maintainance', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IT 116', name: 'Human Interaction 2', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 127', name: 'Capstone Project 2', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science, Technology and Society', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'IS 125', name: 'Internship/Practicum', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        }
    },
    'HBM': {
        'BSHMCA': {
            name: 'Bachelor of Science in Hospitality Management Culinary Arts',
            courses: [
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 103', name: 'Risk Management as Applied to Safety Security and Sanitation', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 102', name: 'Quality Service Management in Tourism and Hospitality', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 101', name: 'Macro Perspective of Tourism and Hospitality', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 116', name: 'Professional Development and Applied Ethics', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'HTC 115', name: 'Micro Perspective of Tourism and Hospitality', year: 'First Year', semester: 'Second Semester'},
                {code: 'HM 102', name: 'Personality Development', year: 'First Year', semester: 'Second Semester'},
                {code: 'HPC 121', name: 'Fundamentals in Food Service Operations', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE ELEC 6', name: 'Philippine Popular Culture', year: 'Second Year', semester: 'First Semester'},
                {code: 'HTC 207', name: 'Multicultural Diversity in Workplace for the Tourism Professional', year: 'Second Year', semester: 'First Semester'},
                {code: 'HTC 209', name: 'Philippine Tourism Geography and Culture', year: 'Second Year', semester: 'First Semester'},
                {code: 'HPC 203', name: 'Fundamentals in Lodging Operations', year: 'Second Year', semester: 'First Semester'},
                {code: 'HPC 206', name: 'Supply Chain Management in Hospitality Industry', year: 'Second Year', semester: 'First Semester'},
                {code: 'HPC 208', name: 'Foreign Language I', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports / Dance / Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Second Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ENG 3', name: 'Technical Writing with Oral Communication', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BME 211', name: 'Operations Management in Tourism and Hospitality Industry', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HTC 214', name: 'Legal Aspects in Tourism and Hospitality', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HPC 212', name: 'Kitchen Essentials and Basic Food Preparation', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HPC 214', name: 'Applied Business Tools and Technologies', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HPC 219', name: 'Foreign Language 2', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports / Dance / Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BHM 302', name: 'Strategic Management in Tourism and Hospitality', year: 'Third Year', semester: 'First Semester'},
                {code: 'HPC 301', name: 'Research Fundamentals', year: 'Third Year', semester: 'First Semester'},
                {code: 'CUL 301', name: 'Culinary Fundamentals', year: 'Third Year', semester: 'First Semester'},
                {code: 'CUL 302', name: 'Garde Manger', year: 'Third Year', semester: 'First Semester'},
                {code: 'CUE 302', name: 'Gastronomy', year: 'Third Year', semester: 'First Semester'},
                {code: 'CUE 303', name: 'Food and Beverage Cost Control', year: 'Third Year', semester: 'First Semester'},
                {code: 'HTC 318', name: 'Tourism and Hospitality Marketing', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HPC 310', name: 'Research in Hospitality', year: 'Third Year', semester: 'Second Semester'},
                {code: 'CUL 313', name: 'Philippine Regional Cuisine', year: 'Third Year', semester: 'Second Semester'},
                {code: 'CUL 404', name: 'Bread and Pastry', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HPC 407', name: 'Ergonomics and Facilities Planning for the Hospitality Industry', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'HTC 401', name: 'Entrepreneurship in Tourism and Hospitality', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'CUL 405', name: 'Catering Management', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'CUL 314', name: 'Specialty Cuisine', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'HPC 406', name: 'Meetings, Incentives, Conferences and Events Management', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'PRC 412', name: 'Student Internship Program 102 600 hours', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BSEntrep': {
            name: 'Bachelor of Science in Entrepreneurship',
            courses: [
                {code: 'GE 4', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'First Semester'},
                {code: 'ENT 101', name: 'Entrepreneurial Behavior', year: 'First Year', semester: 'First Semester'},
                {code: 'ENT 102', name: 'Business Organization and Management', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1', name: 'Understanding the Self', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 2', name: 'Readings in Philippine History', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 4', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'ENT 103', name: 'Fundamentals of Financial Accounting', year: 'First Year', semester: 'Second Semester'},
                {code: 'ENT 104', name: 'Microeconomics', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise-Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENT 125', name: 'Marketing Management', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENT 117', name: 'Human Resource Management', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENT 106', name: 'Entrepreneurial Leadership and Organization', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports / Dance / Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BME 211', name: 'Operations and Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ENT 105', name: 'Opportunity Seeking', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ENT 108', name: 'Market Research and Consumer Behavior', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ENT 134', name: 'Personality Development', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ENT 109', name: 'Innovation Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'ENT 110', name: 'Pricing and Costing', year: 'Third Year', semester: 'First Semester'},
                {code: 'ENT 112', name: 'Programs and Policies on Enterprise Development', year: 'Third Year', semester: 'First Semester'},
                {code: 'ENT 127', name: 'Managing a Service Enterprise', year: 'Third Year', semester: 'First Semester'},
                {code: 'ENT 131', name: 'Management of Technology', year: 'Third Year', semester: 'First Semester'},
                {code: 'ENT 130', name: 'Event Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'BME 302', name: 'Strategic Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 114', name: 'Business Plan Preparation', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 116', name: 'Social Entrepreneurship', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 130', name: 'Franchising', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 131', name: 'Negotiation', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 111', name: 'Business Law and Taxation', year: 'Third Year', semester: 'Second Semester'},
                {code: 'Ent 126', name: 'Student Internship Program 105', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'Ent 115', name: 'Business Plan and Implementation I', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'Ent 132', name: 'Business Development Service', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'Ent 113', name: 'International Business and Trade', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'Ent 118', name: 'Business Plan Implementation II', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'Ent 133', name: 'E-commerce', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BSTM': {
            name: 'Bachelor of Science in Tourism Management',
            courses: [
                {code: 'HTC 101', name: 'Macro Perspective of Tourism and Hospitality', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 103', name: 'Risk Management as Applied to Safety Security and Sanitation', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'HTC 115', name: 'Micro Perspective of Tourism and Hospitality', year: 'First Year', semester: 'Second Semester'},
                {code: 'HTC 102', name: 'Quality Service Management in Tourism and Hospitality', year: 'First Year', semester: 'Second Semester'},
                {code: 'HTC 116', name: 'Professional Development and Applied Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'HM 102', name: 'Personality Development', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'HPC 205', name: 'Supply Chain Management in Hospitality Industry', year: 'Second Year', semester: 'First Semester'},
                {code: 'HTC 207', name: 'Multicultural Diversity in Workplace for the Tourism Professional', year: 'Second Year', semester: 'First Semester'},
                {code: 'ENG 3', name: 'Technical Writing with Oral Communication', year: 'Second Year', semester: 'First Semester'},
                {code: 'TME 103', name: 'Tour Guiding', year: 'Second Year', semester: 'First Semester'},
                {code: 'HTC 209', name: 'Philippine Tourism Geography and Culture', year: 'Second Year', semester: 'First Semester'},
                {code: 'TME 110', name: 'Business Computer Application', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 6', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 7', name: 'Science Technology and Society', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'TME 104', name: 'Philippine Gastronomical Tourism', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HPC 4', name: 'Applied Business Tools and Technologies', year: 'Second Year', semester: 'Second Semester'},
                {code: 'TPC 101', name: 'Tour and Travel Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'TPC 102', name: 'Global Culture and Tourism Geography', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BME 211', name: 'Operations Management in Tourism and Hospitality Industry', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Second Year', semester: 'Second Semester'},
                {code: 'TME 109', name: 'Business Communication', year: 'Second Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HTC 318', name: 'Tourism and Hospitality Marketing', year: 'Third Year', semester: 'First Semester'},
                {code: 'HPC 301', name: 'Research Fundamentals', year: 'Third Year', semester: 'First Semester'},
                {code: 'HTC 214', name: 'Legal Aspects in Tourism and Hospitality', year: 'Third Year', semester: 'First Semester'},
                {code: 'TPC 104', name: 'Transportation Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'TME 102', name: 'Heritage Tourism', year: 'Third Year', semester: 'First Semester'},
                {code: 'HPC 208', name: 'Foreign Language 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'BME 302', name: 'Strategic Management in Tourism and Hospitality', year: 'Third Year', semester: 'First Semester'},
                {code: 'TME 108', name: 'Ecotourism Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HPC 213', name: 'Travel Writing and Leisure Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'CRE 1', name: 'Recreational and Leisure Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'TPC 103', name: 'Sustainable Tourism', year: 'Third Year', semester: 'Second Semester'},
                {code: 'TPC 106', name: 'Research in Tourism', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HPC 219', name: 'Foreign Language 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'PRC 414', name: 'Student Internship Program 104', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'CRE 2', name: 'Medical and Wellness Tourism', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'CRE 3', name: 'Cruise Tourism', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'TPC 105', name: 'Tourism Policy Planning and Development', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'HTC 401', name: 'Entrepreneurship in Tourism and Hospitality', year: 'Fourth Year', semester: 'Second Semester'},
                {code: 'HC 406B', name: 'Introduction to Meetings Incentives Conferences and Events Management (MICE)', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        }
    },
    'EDUCATION': {
        'BTLEd-IA': {
            name: 'Bachelor of Technology and Livelihood Education - Industrial Arts',
            courses: [
                {code: 'IA 1', name: 'Introduction to Industrial Arts Part 1', year: 'First Year', semester: 'First Semester'},
                {code: 'AT 101A', name: 'Fundamentals of Automotive Technology', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT ED 2', name: 'Introduction to ICT Part 1', year: 'First Year', semester: 'First Semester'},
                {code: 'ED 1', name: 'The Child and Adolescent Learners and Learning Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'IA 3', name: 'Introduction to Industrial Arts Part II', year: 'First Year', semester: 'Second Semester'},
                {code: 'AT 102', name: 'Applied Automotive Technology', year: 'First Year', semester: 'Second Semester'},
                {code: 'ICT ED 3', name: 'Introduction to ICT Part II', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PathFit 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'IA 103', name: 'Civil Technology 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'IA 105', name: 'Fundamentals of Electronics Technology', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 2', name: 'The Teaching Profession', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 4B', name: 'Building and Enhancing New Literacies Across the Curriculum', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 5', name: 'Emphasis on Trainee Methodology I', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 6', name: 'Foundation of Special and Inclusive Education', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 13', name: 'Assessment in Learning 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'HE 1', name: 'Home Economics Literacy', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'IA 106', name: 'Digital Electronic Technology', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IA 104', name: 'Civil Technology II', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HE 3', name: 'Family and Consumer Life Skills', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HE 5', name: 'Entrepreneurship', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 3B', name: 'The Teacher and the Community School Culture and Organizational Leadership', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 9', name: 'Curriculum Development and Evaluation with Emphasis on Trainee Methodology II', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 10', name: 'Technology for Teaching and Learning 1', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 6', name: 'Art Appreciation', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IA 107', name: 'Fundamentals of Electrical Technology', year: 'Third Year', semester: 'First Semester'},
                {code: 'IA 109', name: 'Metal Works', year: 'Third Year', semester: 'First Semester'},
                {code: 'IA 110', name: 'Domestic Refrigeration and Air Conditioning', year: 'Third Year', semester: 'First Semester'},
                {code: 'AFA 1', name: 'Introduction of Agri Fishery Arts Part I', year: 'Third Year', semester: 'First Semester'},
                {code: 'TED 1', name: 'Research 1 (Methods of Research)', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 15B', name: 'Assessment in Learning II with Focus on Trainee Methodology', year: 'Third Year', semester: 'First Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'First Semester'},
                {code: 'IA 108', name: 'Applied Electrical Technology', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IA 111', name: 'Commercial Refrigeration and Air Conditioning', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IA 112', name: 'Graphic Arts', year: 'Third Year', semester: 'Second Semester'},
                {code: 'AFA 2', name: 'Introduction to Agri Fishery Arts Part II', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 12', name: 'Technology for Teaching and Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 8', name: 'Ethics', year: 'Third Year', semester: 'Second Semester'},
                {code: 'TED 2', name: 'Research 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FS 1', name: 'Observations of Teaching Learning in actual school environment', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'FS 2', name: 'Participation and Teaching Assistantship', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 17', name: 'REFRESHER', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 18', name: 'Teaching Internship', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BTLEd-HE': {
            name: 'Bachelor of Technology and Livelihood Education - Home Economics',
            courses: [
                {code: 'HE 1', name: 'Home Economics Literacy', year: 'First Year', semester: 'First Semester'},
                {code: 'HE 101', name: 'Household Resource Management', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT ED 2', name: 'Introduction to ICT Part 1', year: 'First Year', semester: 'First Semester'},
                {code: 'ED 1', name: 'The Child and Adolescent Learners and Learning Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'HE 3', name: 'Family and Consumer Life Skills', year: 'First Year', semester: 'Second Semester'},
                {code: 'HE 3', name: 'Consumer Education', year: 'First Year', semester: 'Second Semester'},
                {code: 'ICT ED 3', name: 'Introduction to ICT Part II', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'HE 102', name: 'Principles of Food Preparation', year: 'Second Year', semester: 'First Semester'},
                {code: 'HE 104', name: 'Food and Nutrition', year: 'Second Year', semester: 'First Semester'},
                {code: 'IA 4', name: 'Introduction to Industrial Arts Part 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 2', name: 'The Teaching Profession', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 4B', name: 'Building and Enhancing New Literacies Across the Curriculum', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 5', name: 'Facilitating Learner centered Teaching', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 6', name: 'Foundation of Special and Inclusive Education', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 13', name: 'Assessment in Learning 1', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'Rhythmic Activities', year: 'Second Year', semester: 'First Semester'},
                {code: 'HE 5', name: 'Entrepreneurship', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HE 106', name: 'Fundamentals of Food Technology', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IA 3', name: 'Introduction to Industrial Arts Part II', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 3B', name: 'The Teacher and the Community School Culture and Organizational Leadership', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 9', name: 'Curriculum Development and Evaluation with Emphasis on Trainee Methodology', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 10', name: 'Technology for Teaching and Learning 1', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 6', name: 'Art Appreciation', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'HE 105', name: 'Arts in Daily Living', year: 'Third Year', semester: 'First Semester'},
                {code: 'HE 107', name: 'School Food Service Management with 150 hours of practicum', year: 'Third Year', semester: 'First Semester'},
                {code: 'HE 108', name: 'Clothing Selection Purchase and Care', year: 'Third Year', semester: 'First Semester'},
                {code: 'HE 109', name: 'Crafts Design Handicraft', year: 'Third Year', semester: 'First Semester'},
                {code: 'AFA 1', name: 'Introduction to Agri Fishery Arts Part I', year: 'Third Year', semester: 'First Semester'},
                {code: 'TED 1', name: 'Research 1 Methods of Research', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 15B', name: 'Assessment in Learning II with focus on Trainee Methodology', year: 'Third Year', semester: 'First Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'First Semester'},
                {code: 'HE 110', name: 'Child and Adolescent Development', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HE 111', name: 'Marriage and Family Relationships', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HE 112', name: 'Clothing Construction', year: 'Third Year', semester: 'Second Semester'},
                {code: 'HE 113', name: 'Beauty Care and Wellness', year: 'Third Year', semester: 'Second Semester'},
                {code: 'AFA 2', name: 'Introduction to Agri Fishery Arts Part II', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 12', name: 'Technology for Teaching and Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 8', name: 'Ethics', year: 'Third Year', semester: 'Second Semester'},
                {code: 'TED 2', name: 'Research 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FS 1', name: 'Observations of Teaching Learning in actual school environment', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'FS 2', name: 'Participation and Teaching Assistantship', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 17', name: 'Refresher', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 18', name: 'Teaching Internship', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BSED-Science': {
            name: 'Bachelor of Secondary Education - Science',
            courses: [
                {code: 'CHEM 101', name: 'Inorganic Chemistry (Lecture & Laboratory)', year: 'First Year', semester: 'First Semester'},
                {code: 'PHYS 101', name: 'Mechanics (Solid)', year: 'First Year', semester: 'First Semester'},
                {code: 'ED 1', name: 'The Child and Adolescent Learners and Learning Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1/LTS 1/CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'CHEM 102', name: 'Organic Chemistry (Lecture & Laboratory)', year: 'First Year', semester: 'Second Semester'},
                {code: 'PHYS 102', name: 'Fluid Mechanics', year: 'First Year', semester: 'Second Semester'},
                {code: 'ED 3A', name: 'The Teacher and the Community, School Culture and Organizational Leadership', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 4', name: 'Living in the IT Era', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2/LTS 2/CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise-Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'CHEM 103', name: 'Analytical Chemistry', year: 'Second Year', semester: 'First Semester'},
                {code: 'BIO 101', name: 'Cell and Molecular Biology', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 2', name: 'The Teaching Profession', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 6', name: 'Foundation of Special and Inclusive Education', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 6B SS', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science, Technology and Society', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3A', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'CHEM 104', name: 'Biochemistry', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PHYS 103', name: 'Thermodynamics (Lecture and Laboratory)', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PHYS 104', name: 'Electricity and Magnetism', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BIO 102', name: 'Genetics (Lecture & Laboratory)', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 7', name: 'Facilitating Learner-Centered Teaching', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Second Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4A', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PHYS 105', name: 'Waves and Optics (Lecture & Laboratory)', year: 'Third Year', semester: 'First Semester'},
                {code: 'BIO 103', name: 'Microbiology and Parasitology (Lecture & Laboratory)', year: 'Third Year', semester: 'First Semester'},
                {code: 'SCI 101', name: 'Earth Science', year: 'Third Year', semester: 'First Semester'},
                {code: 'SCI 102', name: 'Environmental Science', year: 'Third Year', semester: 'First Semester'},
                {code: 'SCI 105', name: 'Teaching of Science/Teaching the Specialized Field', year: 'Third Year', semester: 'First Semester'},
                {code: 'SED 1 SCI', name: 'Research in Teaching the Specialized Field', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 10', name: 'Technology for Teaching and Learning 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 11', name: 'The Teacher and the School Curriculum', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 13', name: 'Assessment in Learning 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'PHYS 106', name: 'Modern Physics', year: 'Third Year', semester: 'Second Semester'},
                {code: 'BIO 104', name: 'Anatomy and Physiology', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SCI 103', name: 'Astronomy', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SCI 104', name: 'Meteorology', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 12', name: 'Technology for Teaching and Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SED SCI', name: 'Research in Teaching and Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 4A', name: 'Building and Enhancing New Literacies Across the Curriculum', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 15A', name: 'Assessment in Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FS 1', name: 'Observations of Teaching-Learning in Actual School Environment', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'FS 2', name: 'Participation and Teaching Assistantship', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 17', name: 'Refresher', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 18', name: 'Teaching Internship', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BSED-Math': {
            name: 'Bachelor of Secondary Education - Mathematics',
            courses: [
                {code: 'MATH 101', name: 'History of Mathematics', year: 'First Year', semester: 'First Semester'},
                {code: 'MATH 102', name: 'College and Advanced Algebra', year: 'First Year', semester: 'First Semester'},
                {code: 'ED 1', name: 'The Child and Adolescent Learners and Learning Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1/LTS 1/CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1A', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'MATH 103', name: 'Trigonometry', year: 'First Year', semester: 'Second Semester'},
                {code: 'MATH 104', name: 'Plane and Solid Geometry', year: 'First Year', semester: 'Second Semester'},
                {code: 'ED 3A', name: 'The Teacher and the Community School Culture and Organizational Leadership', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2/LTS 2/CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2A', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'MATH 105', name: 'Logic and Set Theory', year: 'Second Year', semester: 'First Semester'},
                {code: 'MATH 106A', name: 'Elementary Statistics and Probability with laboratory', year: 'Second Year', semester: 'First Semester'},
                {code: 'MATH 107A', name: 'Calculus 1 with Analytic Geometry', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 2', name: 'The Teaching Profession', year: 'Second Year', semester: 'First Semester'},
                {code: 'ED 6', name: 'Foundation of Special and Inclusive Education', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3A', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'MATH 108', name: 'Calculus 2', year: 'Second Year', semester: 'Second Semester'},
                {code: 'MATH 109', name: 'Number Theory', year: 'Second Year', semester: 'Second Semester'},
                {code: 'MATH 110A', name: 'Advanced Statistics with laboratory', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ED 7', name: 'Facilitating Learner Centered Teaching', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Second Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4A', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'MATH 111A', name: 'Calculus 3', year: 'Third Year', semester: 'First Semester'},
                {code: 'MATH 112', name: 'Mathematics of Investments', year: 'Third Year', semester: 'First Semester'},
                {code: 'MATH 113', name: 'Problem Solving Mathematical Investigation and Modeling', year: 'Third Year', semester: 'First Semester'},
                {code: 'MATH 116', name: 'Principles and Strategies in Teaching Mathematics', year: 'Third Year', semester: 'First Semester'},
                {code: 'SED 1 MATH', name: 'Research in Mathematics 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 10', name: 'Technology for Teaching and Learning 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 11', name: 'The Teacher and the School Curriculum', year: 'Third Year', semester: 'First Semester'},
                {code: 'ED 13', name: 'Assessment in Learning 1', year: 'Third Year', semester: 'First Semester'},
                {code: 'MATH 113', name: 'Linear Algebra', year: 'Third Year', semester: 'Second Semester'},
                {code: 'MATH 114', name: 'Modern Geometry', year: 'Third Year', semester: 'Second Semester'},
                {code: 'MATH 115A', name: 'Abstract Algebra', year: 'Third Year', semester: 'Second Semester'},
                {code: 'MATH 117', name: 'Assessment and Evaluation in Mathematics', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 12 MATH', name: 'Technology for Teaching and Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SED 2 MATH', name: 'Research in Mathematics 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 15A', name: 'Assessment in Learning 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ED 4A', name: 'Building and Enhancing New Literacies Across the Curriculum', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FS 1', name: 'Observations of Teaching Learning in Actual School Environment', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'FS 2', name: 'Participation and Teaching Assistantship', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 17', name: 'REFRESHER', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'ED 18', name: 'Teaching Internship', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        }
    },
    'BIT': {
        'BIT-Electrical': {
            name: 'BIT Major in Electrical Technology',
            courses: [
                {code: 'BELT 110', name: 'Occupational Safety and Health (OSH)', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 111', name: 'Electricity and Electronics Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 112', name: 'DC Circuits', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 113', name: 'Shop Processes Tools and Equipment', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 114', name: 'Philippine Electrical Code', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 115', name: 'Residential Wiring System', year: 'First Year', semester: 'First Semester'},
                {code: 'IND DRAW', name: 'Industrial Drawing', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BELT 121', name: 'AC Circuits', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELT 122', name: 'Industrial Wiring Systems', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELT 123', name: 'Electrical Instruments and Measurements', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELT 124', name: 'Electrical Machines', year: 'First Year', semester: 'Second Semester'},
                {code: 'COMP MATH', name: 'Comprehensive Mathematics', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELT 211', name: 'Instrumentation and Process Control', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELT 212', name: 'Sensor Technology', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELT 213', name: 'Electronic Laws and Standard', year: 'Second Year', semester: 'First Semester'},
                {code: 'IND PHYS', name: 'Physics for Industrial Technologists', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELT 221', name: 'Logic Circuits', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELT 222', name: 'Electrical Computers and Aide Design', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELT 223', name: 'Programmable Logic Controller', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ICT 1', name: 'Introduction to Information Technology', year: 'Second Year', semester: 'Second Semester'},
                {code: 'IND CHEM', name: 'Chemistry for Industrial Technologists', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ITM 15', name: 'Materials Technology Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ITM 16', name: 'Quality Control and Assurance', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELT 311', name: 'Electro- Pneumatic Systems', year: 'Third Year', semester: 'First Semester'},
                {code: 'CS 11', name: 'Computer Programming', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 2', name: 'Industrial Psychology', year: 'Third Year', semester: 'First Semester'},
                {code: 'PS 1', name: 'Project Study 1 with Intellectual Property Rights', year: 'Third Year', semester: 'First Semester'},
                {code: 'BELT 312', name: 'Instrumentation and Process Control', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 13', name: 'Technopreneurship', year: 'Third Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FOR LAN 1', name: 'Foreign Language 1', year: 'Third Year', semester: 'Second Semester'},
                {code: 'PS 2', name: 'Project Study 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 14', name: 'Industrial Organization and Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 9', name: 'Production Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SIP 1', name: 'Student Internship Program 1 600 hours', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'SIP 2', name: 'Student Internship Program 2 600 hours', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BIT-Automotive': {
            name: 'BIT Major in Automotive Technology',
            courses: [
                {code: 'BAT 110', name: 'Occupational Safety and Health', year: 'First Year', semester: 'First Semester'},
                {code: 'BAT 111', name: 'Fundamentals of Automotive Technology', year: 'First Year', semester: 'First Semester'},
                {code: 'BAT 112', name: 'Automotive Electrical System', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 1', name: 'Introduction to Information Technology', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BAT 121', name: 'Automotive Electronics', year: 'First Year', semester: 'Second Semester'},
                {code: 'BAT 122', name: 'Small Engine Repair and Motorcycle Servicing', year: 'First Year', semester: 'Second Semester'},
                {code: 'BAT 123', name: 'Car Care Servicing,Emission Control and Tune', year: 'First Year', semester: 'Second Semester'},
                {code: 'BAT 124', name: 'Automotive Emission Aided Design', year: 'First Year', semester: 'Second Semester'},
                {code: 'IND DRAW', name: 'Industrial Drawing', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 5 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BAT 211', name: 'Body Repair and Painting', year: 'Second Year', semester: 'First Semester'},
                {code: 'BAT 212', name: 'Power Train and Conversion System', year: 'Second Year', semester: 'First Semester'},
                {code: 'BAT 213', name: 'Automotive LPB System', year: 'Second Year', semester: 'First Semester'},
                {code: 'BAT 214', name: 'Automotive Air Conditioning', year: 'Second Year', semester: 'First Semester'},
                {code: 'ITM 15', name: 'Materials Technology Management', year: 'Second Year', semester: 'First Semester'},
                {code: 'IND CHEM', name: 'Chemistry for Industrial Technologists', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 3', name: 'The Contemporary World', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 6', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'BAT 107', name: 'Engine Overhauling and Performance Testing', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BAT 222', name: 'Hybrid and Electric Vehicle', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BAT 223', name: 'Driving Education', year: 'Second Year', semester: 'Second Semester'},
                {code: 'CS 1', name: 'Computer Programming', year: 'Second Year', semester: 'Second Semester'},
                {code: 'COMP MATH', name: 'Comprehensive Mathematics', year: 'Second Year', semester: 'Second Semester'},
                {code: 'FOR LAN 1', name: 'Foreign Language 1', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BAT 311', name: 'Body Management and Under Chassis Electronics Control System', year: 'Third Year', semester: 'First Semester'},
                {code: 'PS 1', name: 'Project Study 1 with Intellectual Property Rights', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 2', name: 'Industrial Psychology', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 9', name: 'Production Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 13', name: 'Technopreneurship', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 14', name: 'Industrial Organization and Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'First Semester'},
                {code: 'BAT 321', name: 'Electronics Engine Management Control System', year: 'Third Year', semester: 'Second Semester'},
                {code: 'PS 2', name: 'Project Study 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 16', name: 'Quality Control and Assurance', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IND PHYS', name: 'Physics for Industrial Technologists', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SIP 1', name: 'Student Internship Program 1', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'SIP 2', name: 'Student Internship Program 2', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BIT-Electronics': {
            name: 'BIT Major in Electronics Technology',
            courses: [
                {code: 'BELX 110', name: 'Occupational Safety and Health OSH', year: 'First Year', semester: 'First Semester'},
                {code: 'BELX 111', name: 'Electronic Devices 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BELX 112', name: 'Electronic Communications 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BELX 113', name: 'Electronic CAD', year: 'First Year', semester: 'First Semester'},
                {code: 'IND DRAW', name: 'Industrial Drawing', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 4 MATH', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BELX 121', name: 'Electronic Devices 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELX 122', name: 'Electronic Communication 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELX 123', name: 'Digital Electronics', year: 'First Year', semester: 'Second Semester'},
                {code: 'COMP MATH', name: 'Comprehensive Mathematics', year: 'First Year', semester: 'Second Semester'},
                {code: 'IND CHEM', name: 'Chemistry for Industrial Technologists', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BELX 211', name: 'Instrumentation and Process Control', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELX 212', name: 'Sensor Technology', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELX 213', name: 'Electronic Laws and Standard', year: 'Second Year', semester: 'First Semester'},
                {code: 'CS 1', name: 'Computer Programming', year: 'Second Year', semester: 'First Semester'},
                {code: 'IND PHYS', name: 'Physics for Industrial Technologists', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'BELX 221', name: 'Multimedia Systems', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELX 222', name: 'Industrial Electronics', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELX 223', name: 'Electro Pneumatic System', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ITM 15', name: 'Materials Technology Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'ITM 16', name: 'Industrial Organization and Management', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BELX 311', name: 'Programmable Controllers', year: 'Third Year', semester: 'First Semester'},
                {code: 'PS 1', name: 'Project Study 1 with Intellectual Property Rights', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 2', name: 'Industrial Psychology', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 14', name: 'Industrial Organization and Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'Third Year', semester: 'First Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Third Year', semester: 'First Semester'},
                {code: 'BELX 321', name: 'Industrial Robotics', year: 'Third Year', semester: 'Second Semester'},
                {code: 'PS 2', name: 'Project Study 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 9', name: 'Production Management', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 13', name: 'Technopreneurship', year: 'Third Year', semester: 'Second Semester'},
                {code: 'FOR LAN 1', name: 'Foreign Language 1', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Third Year', semester: 'Second Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'Second Semester'},
                {code: 'SIP 1', name: 'Student Internship Program 1 600 hours', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'SIP 2', name: 'Student Internship Program 2 600 hours', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        },
        'BIT-HVACR': {
            name: 'BIT Major in HVAC/R Technology',
            courses: [
                {code: 'BHVARC 111', name: 'Occupational Safety and Health', year: 'First Year', semester: 'First Semester'},
                {code: 'BHVARC 112', name: 'Refrigeration Principles', year: 'First Year', semester: 'First Semester'},
                {code: 'BHVARC 113', name: 'HVACR Electricity and Electronics', year: 'First Year', semester: 'First Semester'},
                {code: 'GE 5 ENG', name: 'Purposive Communication', year: 'First Year', semester: 'First Semester'},
                {code: 'GE ELEC 7', name: 'Gender and Society', year: 'First Year', semester: 'First Semester'},
                {code: 'ICT 1', name: 'Introduction to Information Technology', year: 'First Year', semester: 'First Semester'},
                {code: 'PE 1', name: 'PATHFIT 1 Movement Competency Training', year: 'First Year', semester: 'First Semester'},
                {code: 'NSTP 1', name: 'ROTC 1 / LTS 1 / CWTS 1', year: 'First Year', semester: 'First Semester'},
                {code: 'BHVARC 121', name: 'Domestic RAC Components and Circuits Diagnostics', year: 'First Year', semester: 'Second Semester'},
                {code: 'BHVARC 122', name: 'Refrigerant Classifications Controls and Application', year: 'First Year', semester: 'Second Semester'},
                {code: 'BHVARC 124', name: 'Split Type Aircon/PACU System Heat Rate Installation', year: 'First Year', semester: 'Second Semester'},
                {code: 'IND DRAW', name: 'Industrial Drawing', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 4', name: 'Mathematics in the Modern World', year: 'First Year', semester: 'Second Semester'},
                {code: 'GE 1 SS', name: 'Understanding the Self', year: 'First Year', semester: 'Second Semester'},
                {code: 'PE 2', name: 'PATHFIT 2 Exercise Based Fitness Activities', year: 'First Year', semester: 'Second Semester'},
                {code: 'NSTP 2', name: 'ROTC 2 / LTS 2 / CWTS 2', year: 'First Year', semester: 'Second Semester'},
                {code: 'BHVARC 211', name: 'Automotive Mobile Aircon Systems Trouble shooting', year: 'Second Year', semester: 'First Semester'},
                {code: 'BHVARC 212', name: 'Industrial Refrigeration Systems Application', year: 'Second Year', semester: 'First Semester'},
                {code: 'BHVARC 213', name: 'Commercial Refrigeration Equipment Operation and Cold Storage', year: 'Second Year', semester: 'First Semester'},
                {code: 'ITM 15', name: 'Materials Technology Management', year: 'Second Year', semester: 'First Semester'},
                {code: 'IND CHEM', name: 'Chemistry for Industrial Technologists', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 3 SS', name: 'The Contemporary World', year: 'Second Year', semester: 'First Semester'},
                {code: 'GE 6 SS', name: 'Art Appreciation', year: 'Second Year', semester: 'First Semester'},
                {code: 'PE 3', name: 'PATHFIT 3 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'First Semester'},
                {code: 'BHVARC 221', name: 'Industrial System Processing Troubleshooting Repair and Maintenance', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BHVARC 222', name: 'Heating Ventilating and Air Conditioning Repair and Maintenance', year: 'Second Year', semester: 'Second Semester'},
                {code: 'CS 1', name: 'Computer Programming', year: 'Second Year', semester: 'Second Semester'},
                {code: 'COMP MATH', name: 'Comprehensive Mathematics', year: 'Second Year', semester: 'Second Semester'},
                {code: 'FOR LAN 1', name: 'Foreign Language', year: 'Second Year', semester: 'Second Semester'},
                {code: 'GE 8 SS', name: 'Ethics', year: 'Second Year', semester: 'Second Semester'},
                {code: 'PE 4', name: 'PATHFIT 4 Sports/Dance/Martial Arts', year: 'Second Year', semester: 'Second Semester'},
                {code: 'BHVARC 311', name: 'Auxiliary Equipment Operation Service and Installation', year: 'Third Year', semester: 'First Semester'},
                {code: 'BHVARC 312', name: 'Transportation Refrigeration System Troubleshooting and Maintenance', year: 'Third Year', semester: 'First Semester'},
                {code: 'PS 1', name: 'Project Study 1 with Intellectual Property Rights', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 2', name: 'Industrial Psychology', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 9', name: 'Production Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 13', name: 'Technopreneurship', year: 'Third Year', semester: 'First Semester'},
                {code: 'ITM 14', name: 'Industrial Organization and Management', year: 'Third Year', semester: 'First Semester'},
                {code: 'RIZAL', name: 'Life and Works of Rizal', year: 'Third Year', semester: 'First Semester'},
                {code: 'BHVARC 321', name: 'HVAC/R Piping Ducting Construction Heat Load and Design', year: 'Third Year', semester: 'Second Semester'},
                {code: 'PS 2', name: 'Project Study 2', year: 'Third Year', semester: 'Second Semester'},
                {code: 'ITM 16', name: 'Quality Control and Assurance', year: 'Third Year', semester: 'Second Semester'},
                {code: 'IND PHYS', name: 'Physics for Industrial Technologists', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 2 SS', name: 'Readings in Philippine History', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE 7 SCI', name: 'Science Technology and Society', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE ELEC 1', name: 'Environmental Science', year: 'Third Year', semester: 'Second Semester'},
                {code: 'GE ELEC 10', name: 'Philippine Popular Culture', year: 'Third Year', semester: 'Second Semester'},
                {code: 'OJT 1', name: 'STUDENT INTERNSHIP PROGRAM 1 600 hours', year: 'Fourth Year', semester: 'First Semester'},
                {code: 'OJT 2', name: 'STUDENT INTERNSHIP PROGRAM 2 600 hours', year: 'Fourth Year', semester: 'Second Semester'}
            ]
        }
    }
};

// Department checkbox change handler
document.querySelectorAll('.department-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', handleDepartmentChange);
});

function handleDepartmentChange(e) {
    const checkedDepts = Array.from(document.querySelectorAll('.department-checkbox:checked'));
    
    // Only allow one department to be checked
    if (checkedDepts.length > 1) {
        // Uncheck all except the current one
        document.querySelectorAll('.department-checkbox').forEach(cb => {
            if (cb !== e.target) {
                cb.checked = false;
            }
        });
    }
    
    const selectedDept = e.target.checked ? e.target.value : null;
    
    if (selectedDept && courseData[selectedDept]) {
        // Show program selection section
        document.getElementById('programSection').style.display = 'block';
        populatePrograms(selectedDept);
        
        // Hide other sections
        document.getElementById('yearSemesterFilterSection').style.display = 'none';
        document.getElementById('subjectSection').style.display = 'none';
        document.getElementById('yearSemesterSection').style.display = 'none';
        
        // Reset global variables
        currentDepartment = null;
        currentProgram = null;
    } else {
        // Hide all sections if no department selected
        document.getElementById('programSection').style.display = 'none';
        document.getElementById('yearSemesterFilterSection').style.display = 'none';
        document.getElementById('subjectSection').style.display = 'none';
        document.getElementById('yearSemesterSection').style.display = 'none';
        currentDepartment = null;
        currentProgram = null;
    }
}

function populatePrograms(department) {
    const programSelect = document.getElementById('programSelect');
    programSelect.innerHTML = '<option value="">-- Select a program --</option>';
    
    const programs = courseData[department];
    
    for (const [programCode, programData] of Object.entries(programs)) {
        const option = document.createElement('option');
        option.value = programCode;
        option.textContent = `${programCode} - ${programData.name}`;
        option.dataset.department = department;
        programSelect.appendChild(option);
    }
}

// Program selection handler
document.getElementById('programSelect').addEventListener('change', function() {
    if (this.value) {
        currentDepartment = this.options[this.selectedIndex].dataset.department;
        currentProgram = this.value;
        
        // Show year/semester filter section
        document.getElementById('yearSemesterFilterSection').style.display = 'block';
        
        // Hide subject section until filters are selected
        document.getElementById('subjectSection').style.display = 'none';
        document.getElementById('yearSemesterSection').style.display = 'none';
        
        // Reset filters
        document.getElementById('yearLevelFilter').value = '';
        document.getElementById('semesterFilter').value = '';
    } else {
        document.getElementById('yearSemesterFilterSection').style.display = 'none';
        document.getElementById('subjectSection').style.display = 'none';
        document.getElementById('yearSemesterSection').style.display = 'none';
        currentDepartment = null;
        currentProgram = null;
    }
});

// Year Level Filter handler
document.getElementById('yearLevelFilter').addEventListener('change', function() {
    filterAndDisplaySubjects();
});

// Semester Filter handler
document.getElementById('semesterFilter').addEventListener('change', function() {
    filterAndDisplaySubjects();
});

// Function to filter and display subjects based on year level and semester
function filterAndDisplaySubjects() {
    const selectedYear = document.getElementById('yearLevelFilter').value;
    const selectedSemester = document.getElementById('semesterFilter').value;
    
    // Check if both filters are selected
    if (!selectedYear || !selectedSemester) {
        document.getElementById('subjectSection').style.display = 'none';
        return;
    }
    
    if (!currentDepartment || !currentProgram) {
        return;
    }
    
    // Get courses for the selected program
    const courses = courseData[currentDepartment][currentProgram].courses;
    
    // Filter courses by year level and semester
    const filteredCourses = courses.filter(course => {
        return course.year === selectedYear && course.semester === selectedSemester;
    });
    
    // Populate subject dropdown with filtered courses
    const subjectSelect = document.getElementById('subjectSelect');
    subjectSelect.innerHTML = '<option value="">-- Select a subject --</option>';
    
    if (filteredCourses.length === 0) {
        subjectSelect.innerHTML = '<option value="">-- No subjects available for this selection --</option>';
        document.getElementById('subjectCount').innerHTML = '<span class="text-warning">No subjects found for the selected year and semester</span>';
    } else {
        filteredCourses.forEach(course => {
            const option = document.createElement('option');
            option.value = JSON.stringify(course);
            option.textContent = `${course.code} - ${course.name}`;
            subjectSelect.appendChild(option);
        });
        
        document.getElementById('subjectCount').innerHTML = `<span class="text-success">${filteredCourses.length} subject(s) available</span>`;
    }
    
    // Show subject section
    document.getElementById('subjectSection').style.display = 'block';
    
    // Reset subject selection
    document.querySelector('input[name="course_code"]').value = '';
    document.querySelector('input[name="subject_name"]').value = '';
    document.getElementById('yearSemesterSection').style.display = 'none';
}

// Subject selection handler
document.getElementById('subjectSelect').addEventListener('change', function() {
    if (this.value) {
        const courseInfo = JSON.parse(this.value);
        
        // Populate course code and subject name
        document.querySelector('input[name="course_code"]').value = courseInfo.code;
        document.querySelector('input[name="subject_name"]').value = courseInfo.name;
        
        // Show and populate year level and semester (as hidden fields for form submission)
        document.getElementById('yearSemesterSection').style.display = 'block';
        populateYearAndSemester(courseInfo);
    } else {
        document.getElementById('yearSemesterSection').style.display = 'none';
        document.querySelector('input[name="course_code"]').value = '';
        document.querySelector('input[name="subject_name"]').value = '';
    }
});



document.addEventListener('DOMContentLoaded', function() {
    // Scroll to top to show success message
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Re-enable submit button
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Add Book to Collection';
        submitBtn.disabled = false;
    }
    
    // Restore form state from PHP POST data
    <?php if (!empty($selected_categories)): ?>
        // Get the selected department
        const selectedDept = '<?php echo $selected_categories[0]; ?>';
        const deptCheckbox = document.querySelector(`input[value="${selectedDept}"]`);
        
        if (deptCheckbox && courseData[selectedDept]) {
            // Set current department
            currentDepartment = selectedDept;
            
            // Populate programs
            populatePrograms(selectedDept);
            document.getElementById('programSection').style.display = 'block';
            
            // Wait for programs to populate
            setTimeout(function() {
                <?php if (!empty($submitted_program)): ?>
                    // Find the program that contains this course code
                    const programs = courseData[selectedDept];
                    let foundProgram = null;
                    
                    for (const [programCode, programData] of Object.entries(programs)) {
                        const courses = programData.courses;
                        const courseExists = courses.some(course => course.code === '<?php echo htmlspecialchars($submitted_program); ?>');
                        if (courseExists) {
                            foundProgram = programCode;
                            break;
                        }
                    }
                    
                    if (foundProgram) {
                        const programSelect = document.getElementById('programSelect');
                        programSelect.value = foundProgram;
                        currentProgram = foundProgram;
                        
                        // Show year/semester filter section
                        document.getElementById('yearSemesterFilterSection').style.display = 'block';
                        
                        // Set the filters
                        <?php if (!empty($submitted_year_filter)): ?>
                            document.getElementById('yearLevelFilter').value = '<?php echo htmlspecialchars($submitted_year_filter); ?>';
                        <?php endif; ?>
                        
                        <?php if (!empty($submitted_semester_filter)): ?>
                            document.getElementById('semesterFilter').value = '<?php echo htmlspecialchars($submitted_semester_filter); ?>';
                        <?php endif; ?>
                        
                        // Trigger filter to show subjects
                        setTimeout(function() {
                            filterAndDisplaySubjects();
                            
                            // Wait for subjects to populate, then select the subject
                            setTimeout(function() {
                                const subjectSelect = document.getElementById('subjectSelect');
                                if (subjectSelect && subjectSelect.options.length > 1) {
                                    // Find and select the correct subject
                                    for (let i = 0; i < subjectSelect.options.length; i++) {
                                        const option = subjectSelect.options[i];
                                        if (option.value) {
                                            try {
                                                const courseData = JSON.parse(option.value);
                                                if (courseData.code === '<?php echo htmlspecialchars($submitted_program); ?>') {
                                                    subjectSelect.value = option.value;
                                                    
                                                    // Trigger change to populate course code and subject name
                                                    const event = new Event('change', { bubbles: true });
                                                    subjectSelect.dispatchEvent(event);
                                                    break;
                                                }
                                            } catch (e) {
                                                console.error('Error parsing course data:', e);
                                            }
                                        }
                                    }
                                }
                            }, 400);
                        }, 300);
                    }
                <?php endif; ?>
            }, 200);
        }
    <?php endif; ?>
    
    // Restore ISBN fields based on quantity
    setTimeout(function() {
        generateISBNFields();
        
        // Restore ISBN values if they exist
        <?php if (isset($_POST['isbn'])): ?>
            setTimeout(function() {
                <?php if (is_array($_POST['isbn'])): ?>
                    const isbnInputs = document.querySelectorAll('.isbn-input');
                    const isbnValues = <?php echo json_encode($_POST['isbn']); ?>;
                    isbnInputs.forEach((input, index) => {
                        if (isbnValues[index]) {
                            input.value = isbnValues[index];
                        }
                    });
                <?php else: ?>
                    const isbnInput = document.querySelector('.isbn-input');
                    if (isbnInput) {
                        isbnInput.value = '<?php echo htmlspecialchars($_POST['isbn']); ?>';
                    }
                <?php endif; ?>
            }, 100);
        <?php endif; ?>
    }, 600);
});

function populateYearAndSemester(courseInfo) {
    // Populate Year Level (hidden input for form submission)
    const yearLevelContainer = document.getElementById('yearLevelContainer');
    yearLevelContainer.innerHTML = `
        <input type="hidden" name="year_level[]" value="${courseInfo.year}">
    `;
    yearLevelContainer.style.display = 'none';
    
    // Populate Semester (hidden input for form submission)
    const semesterContainer = document.getElementById('semesterContainer');
    semesterContainer.innerHTML = `
        <input type="hidden" name="semester[]" value="${courseInfo.semester}">
    `;
    semesterContainer.style.display = 'none';
}

// Dynamic ISBN field generation function
function generateISBNFields() {
    const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
    const sameBook = document.getElementById('sameBookToggle').checked;
    const container = document.getElementById('isbnContainer');
    
    if (!container) {
        console.error('ISBN container not found!');
        return;
    }
    
    container.innerHTML = '';
    
    if (sameBook) {
        container.innerHTML = `
            <div class="isbn-field mb-2">
                <div class="input-group">
                    <span class="input-group-text bg-success text-white">
                        <i class="fas fa-barcode me-1"></i>All ${quantity} copies
                    </span>
                    <input type="text" class="form-control isbn-input" name="isbn" 
                           placeholder="Enter Call No. for all copies" data-book-index="all">
                </div>
            </div>
        `;
        container.innerHTML += '<input type="hidden" name="same_book" value="true">';
        
        document.getElementById('isbnHelperText').innerHTML = 
            `<i class="fas fa-check-circle text-success me-1"></i>
             All ${quantity} copies will share the same Call No.`;
    } else {
        let fieldsHTML = '';
        for (let i = 1; i <= quantity; i++) {
            fieldsHTML += `
                <div class="isbn-field mb-2">
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white">
                            <i class="fas fa-book me-1"></i>Book ${i}
                        </span>
                        <input type="text" class="form-control isbn-input" name="isbn[]" 
                               placeholder="Enter Call No. for book ${i}" data-book-index="${i}">
                    </div>
                </div>
            `;
        }
        container.innerHTML = fieldsHTML;
        container.innerHTML += '<input type="hidden" name="same_book" value="false">';
        
        document.getElementById('isbnHelperText').innerHTML = 
            `<i class="fas fa-books text-warning me-1"></i>
             ${quantity} different books - each can have unique Call No.`;
    }
    
    // Add event listeners to new inputs
    container.querySelectorAll('.isbn-input').forEach(input => {
        input.addEventListener('input', formatISBN);
    });
}

// ISBN formatting function
function formatISBN(event) {
    let value = event.target.value.replace(/[^\d]/g, ''); 
    
    if (value.length === 10) {
        event.target.value = value.replace(/(\d{3})(\d{1})(\d{3})(\d{3})(\d{1})/, '$1-$2-$3-$4-$5');
    } else if (value.length === 13) {
        event.target.value = value.replace(/(\d{3})(\d{1})(\d{2})(\d{6})(\d{1})/, '$1-$2-$3-$4-$5');
    }
}

// Check if book will be sent to pending archives
function checkAutoArchiveStatus() {
    const publicationYear = document.getElementById('publicationYear').value;
    const currentYear = new Date().getFullYear();
    const autoArchiveNotice = document.getElementById('autoArchiveNotice');
    
    if (publicationYear && (currentYear - parseInt(publicationYear)) >= 5) {
        autoArchiveNotice.style.display = 'block';
        autoArchiveNotice.className = 'alert alert-warning';
        autoArchiveNotice.innerHTML = `
            <i class="fas fa-hourglass-half me-2"></i>
            <strong>Pending Archive:</strong> This book (published in ${publicationYear}) will be sent to Pending Archives (5+ years old). 
            You'll need to select an archive reason in the Archives page before it's officially archived.
        `;
    } else {
        autoArchiveNotice.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    
    // Generate ISBN fields immediately on page load
    generateISBNFields();
    checkAutoArchiveStatus();
    
    // Set up event listeners
    document.getElementById('quantityInput').addEventListener('input', generateISBNFields);
    document.getElementById('sameBookToggle').addEventListener('change', generateISBNFields);
    
    const currentYear = new Date().getFullYear();
    document.getElementById('publicationYear').setAttribute('placeholder', `e.g., ${currentYear}`);
});

// Event listener for publication year changes
document.getElementById('publicationYear').addEventListener('input', function() {
    checkAutoArchiveStatus();
});

// Publication year validation
document.getElementById('publicationYear').addEventListener('input', function() {
    const year = parseInt(this.value);
    const currentYear = new Date().getFullYear();
    
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

// Form validation
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

// Reset form handler
document.querySelector('button[type="reset"]').addEventListener('click', function() {
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('autoArchiveNotice').style.display = 'none';
    document.getElementById('programSection').style.display = 'none';
    document.getElementById('yearSemesterFilterSection').style.display = 'none';
    document.getElementById('subjectSection').style.display = 'none';
    document.getElementById('yearSemesterSection').style.display = 'none';
    
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    document.getElementById('sameBookToggle').checked = true;
    document.getElementById('publicationYear').classList.remove('is-invalid', 'is-valid');
    
    document.querySelector('input[name="course_code"]').value = '';
    document.querySelector('input[name="subject_name"]').value = '';
    document.getElementById('yearLevelFilter').value = '';
    document.getElementById('semesterFilter').value = '';
    
    currentDepartment = null;
    currentProgram = null;
    
    const existingMsg = document.querySelector('.year-validation');
    if (existingMsg) {
        existingMsg.remove();
    }
    setTimeout(generateISBNFields, 100);
});

// Character counter for description
document.querySelector('textarea[name="description"]').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    
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
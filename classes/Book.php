<?php
require_once 'ActivityLogger.php';

class Book {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Initialize activity logging
        $this->logger = new ActivityLogger($pdo);
    }
    
    /**
     * Create archived_books table if it doesn't exist
     */
    private function createArchivedBooksTable() {
        try {
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
                is_multi_context TINYINT(1) DEFAULT 0,
                same_book_series TINYINT(1) DEFAULT 0,
                original_created_at TIMESTAMP NULL,
                original_updated_at TIMESTAMP NULL,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archive_reason VARCHAR(255) DEFAULT 'Automatic archiving - 10+ years old',
                archived_by VARCHAR(100) DEFAULT 'System',
                INDEX idx_original_id (original_id),
                INDEX idx_category (category),
                INDEX idx_publication_year (publication_year),
                INDEX idx_archived_at (archived_at)
            )";
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Error creating archived_books table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a book should be automatically archived based on publication year
     */
    private function shouldAutoArchive($publicationYear) {
        if (!$publicationYear) {
            return false;
        }
        
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 5;
        
        return $publicationYear <= $cutoffYear;
    }

    /**
     * Archive a book automatically
     */
    private function autoArchiveBook($bookData, $bookId) {
        try {
            // Create archive table if it doesn't exist
            $this->createArchivedBooksTable();
            
            // Insert into archived_books
            $sql = "INSERT INTO archived_books (
                original_id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_context, same_book_series,
                original_created_at, original_updated_at, archived_at, archive_reason, archived_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $bookId,
                $bookData['title'],
                $bookData['author'],
                $bookData['isbn'] ?? '',
                $bookData['category'],
                $bookData['quantity'] ?? 1,
                $bookData['description'] ?? '',
                $bookData['subject_name'] ?? '',
                $bookData['semester'] ?? '',
                $bookData['section'] ?? '',
                $bookData['year_level'] ?? '',
                $bookData['course_code'] ?? '',
                $bookData['publication_year'] ?? null,
                $bookData['book_copy_number'] ?? null,
                $bookData['total_quantity'] ?? null,
                $bookData['is_multi_context'] ?? 0,
                $bookData['same_book_series'] ?? 0,
                'Automatic archiving - Publication year 10+ years old',
                'System'
            ]);
            
            if ($result) {
                // Log the auto-archive activity
                $this->logger->logBookActivity(
                    'auto_archive',
                    ['id' => $bookId, 'title' => $bookData['title'], 'category' => $bookData['category']],
                    "Auto-archived due to publication year: " . ($bookData['publication_year'] ?? 'unknown')
                );
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Auto archive failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get archive statistics
     */
    public function getArchiveStats() {
        try {
            $this->createArchivedBooksTable();
            
            $stats = [];
            
            // Total archived books
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM archived_books");
            $stats['total_archived'] = $stmt->fetch()['total'] ?? 0;
            
            // Books eligible for archiving
            $currentYear = date('Y');
            $cutoffYear = $currentYear - 10;
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as eligible FROM books WHERE publication_year IS NOT NULL AND publication_year <= ?");
            $stmt->execute([$cutoffYear]);
            $stats['eligible_for_archive'] = $stmt->fetch()['eligible'] ?? 0;
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting archive stats: " . $e->getMessage());
            return ['total_archived' => 0, 'eligible_for_archive' => 0];
        }
    }
    
    // Test method for activity logging
    public function testActivityLogging() {
        return $this->logger->log(
            'test',
            'Testing activity logging system from Book class at ' . date('Y-m-d H:i:s'),
            999,
            'Test Book Title',
            'BIT'
        );
    }
    
    // Method to get logger instance (useful for other classes)
    public function getLogger() {
        return $this->logger;
    }
    
    // New utility methods for enhanced functionality
    
    /**
     * Check if publication year field exists in database
     */
    public function checkPublicationYearColumn() {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM books LIKE 'publication_year'");
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking publication_year column: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add publication_year column if it doesn't exist
     */
    public function addPublicationYearColumn() {
        try {
            if (!$this->checkPublicationYearColumn()) {
                $sql = "ALTER TABLE books ADD COLUMN publication_year INT(4) NULL AFTER course_code";
                $this->pdo->exec($sql);
                
                $this->logger->logUserActivity('system', 'Added publication_year column to books table');
                return true;
            }
            return true; // Column already exists
        } catch (PDOException $e) {
            error_log("Error adding publication_year column: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if enhanced book fields exist in database
     */
    public function checkEnhancedColumns() {
        try {
            $requiredColumns = [
                'publication_year',
                'book_copy_number', 
                'total_quantity',
                'is_multi_context',
                'same_book_series'
            ];
            
            $existingColumns = [];
            foreach ($requiredColumns as $column) {
                $stmt = $this->pdo->query("SHOW COLUMNS FROM books LIKE '$column'");
                if ($stmt->fetch()) {
                    $existingColumns[] = $column;
                }
            }
            
            return [
                'required' => $requiredColumns,
                'existing' => $existingColumns,
                'missing' => array_diff($requiredColumns, $existingColumns),
                'all_exist' => count($existingColumns) === count($requiredColumns)
            ];
        } catch (PDOException $e) {
            error_log("Error checking enhanced columns: " . $e->getMessage());
            return ['required' => [], 'existing' => [], 'missing' => [], 'all_exist' => false];
        }
    }
    
    /**
     * Add all missing enhanced columns
     */
    public function addEnhancedColumns() {
        try {
            $columnCheck = $this->checkEnhancedColumns();
            
            if ($columnCheck['all_exist']) {
                return true; // All columns already exist
            }
            
            $alterStatements = [];
            
            foreach ($columnCheck['missing'] as $column) {
                switch ($column) {
                    case 'publication_year':
                        $alterStatements[] = "ADD COLUMN publication_year INT(4) NULL";
                        break;
                    case 'book_copy_number':
                        $alterStatements[] = "ADD COLUMN book_copy_number INT NULL";
                        break;
                    case 'total_quantity':
                        $alterStatements[] = "ADD COLUMN total_quantity INT NULL";
                        break;
                    case 'is_multi_context':
                        $alterStatements[] = "ADD COLUMN is_multi_context TINYINT(1) DEFAULT 0";
                        break;
                    case 'same_book_series':
                        $alterStatements[] = "ADD COLUMN same_book_series TINYINT(1) DEFAULT 0";
                        break;
                }
            }
            
            if (!empty($alterStatements)) {
                $sql = "ALTER TABLE books " . implode(", ", $alterStatements);
                $this->pdo->exec($sql);
                
                $this->logger->logUserActivity('system', 
                    'Added enhanced columns to books table: ' . implode(', ', $columnCheck['missing']));
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error adding enhanced columns: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get database schema information for books table
     */
    public function getTableSchema() {
        try {
            $stmt = $this->pdo->query("DESCRIBE books");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting table schema: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Safe method to add book with fallback for missing columns
     */
    public function safeAddBook($data) {
        try {
            // Check if enhanced columns exist
            $columnCheck = $this->checkEnhancedColumns();
            
            if ($columnCheck['all_exist']) {
                // Use the full addBook method
                return $this->addBook($data);
            } else {
                // Use basic addBook method for compatibility
                return $this->basicAddBook($data);
            }
        } catch (PDOException $e) {
            error_log("Error in safeAddBook: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Basic addBook method for databases without enhanced columns
     */
    private function basicAddBook($data) {
        try {
            if (empty($data['title']) || empty($data['author']) || empty($data['category'])) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO books (title, author, isbn, category, quantity, description, subject_name, semester, section, year_level, course_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $data['title'],
                $data['author'],
                $data['isbn'] ?? '',
                $data['category'],
                $data['quantity'] ?? 1,
                $data['description'] ?? '',
                $data['subject_name'] ?? '',
                $data['semester'] ?? '',
                $data['section'] ?? '',
                $data['year_level'] ?? '',
                $data['course_code'] ?? ''
            ]);
            
            if ($result) {
                $bookId = $this->pdo->lastInsertId();
                
                // Log the activity
                $bookData = [
                    'id' => $bookId,
                    'title' => $data['title'],
                    'category' => $data['category']
                ];
                
                $this->logger->logBookActivity(
                    'add',
                    $bookData,
                    "Quantity: {$data['quantity']}, Author: {$data['author']} (Basic mode)"
                );
                
                return $bookId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error in basicAddBook: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize database with required enhancements
     */
    public function initializeEnhancements() {
        try {
            $this->logger->logUserActivity('system', 'Starting database enhancement initialization');
            
            // Add enhanced columns if they don't exist
            $result = $this->addEnhancedColumns();
            
            if ($result) {
                $this->logger->logUserActivity('system', 'Database enhancement initialization completed successfully');
                return true;
            } else {
                $this->logger->logUserActivity('system', 'Database enhancement initialization failed');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error in initializeEnhancements: " . $e->getMessage());
            $this->logger->logUserActivity('system', 'Database enhancement initialization error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enhanced search method to handle comma-separated values
     */
    public function getAllBooks($category = '', $search = '', $yearLevel = '', $semester = '') {
        try {
            $query = "SELECT * FROM books WHERE 1=1";
            $params = [];
            
            // Category filtering with comma-separated support
            if ($category) {
                $query .= " AND (category = ? OR FIND_IN_SET(?, category) > 0)";
                $params[] = $category;
                $params[] = $category;
            }
            
            // Year level filtering with comma-separated support
            if ($yearLevel) {
                $query .= " AND (year_level = ? OR FIND_IN_SET(?, year_level) > 0)";
                $params[] = $yearLevel;
                $params[] = $yearLevel;
            }
            
            // Semester filtering with comma-separated support
            if ($semester) {
                $query .= " AND (semester = ? OR FIND_IN_SET(?, semester) > 0)";
                $params[] = $semester;
                $params[] = $semester;
            }
            
            if ($search) {
                $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $query .= " ORDER BY title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllBooks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enhanced search method for multi-context books
     */
    public function searchBooksAdvanced($filters = []) {
        try {
            $query = "SELECT * FROM books WHERE 1=1";
            $params = [];
            
            // Title/Author/Description search
            if (!empty($filters['search'])) {
                $query .= " AND (title LIKE ? OR author LIKE ? OR description LIKE ? OR subject_name LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Category filtering (handles both single and comma-separated)
            if (!empty($filters['category'])) {
                $query .= " AND (category = ? OR FIND_IN_SET(?, category) > 0)";
                $params[] = $filters['category'];
                $params[] = $filters['category'];
            }
            
            // Year level filtering
            if (!empty($filters['year_level'])) {
                $query .= " AND (year_level = ? OR FIND_IN_SET(?, year_level) > 0)";
                $params[] = $filters['year_level'];
                $params[] = $filters['year_level'];
            }
            
            // Semester filtering
            if (!empty($filters['semester'])) {
                $query .= " AND (semester = ? OR FIND_IN_SET(?, semester) > 0)";
                $params[] = $filters['semester'];
                $params[] = $filters['semester'];
            }
            
            // Section filtering
            if (!empty($filters['section'])) {
                $query .= " AND (section = ? OR FIND_IN_SET(?, section) > 0)";
                $params[] = $filters['section'];
                $params[] = $filters['section'];
            }
            
            // Publication year filtering
            if (!empty($filters['publication_year_min'])) {
                $query .= " AND publication_year >= ?";
                $params[] = $filters['publication_year_min'];
            }
            
            if (!empty($filters['publication_year_max'])) {
                $query .= " AND publication_year <= ?";
                $params[] = $filters['publication_year_max'];
            }
            
            // Availability filtering
            if (!empty($filters['available_only']) && $filters['available_only'] === 'true') {
                $query .= " AND quantity > 0";
            }
            
            // Multi-context filtering
            if (!empty($filters['multi_context_only']) && $filters['multi_context_only'] === 'true') {
                $query .= " AND is_multi_context = 1";
            }
            
            $query .= " ORDER BY title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in searchBooksAdvanced: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get books by specific academic context
     */
    public function getBooksByAcademicContext($category, $yearLevel = '', $semester = '', $section = '') {
        try {
            $query = "SELECT * FROM books WHERE (category = ? OR FIND_IN_SET(?, category) > 0)";
            $params = [$category, $category];
            
            if ($yearLevel) {
                $query .= " AND (year_level = ? OR FIND_IN_SET(?, year_level) > 0)";
                $params[] = $yearLevel;
                $params[] = $yearLevel;
            }
            
            if ($semester) {
                $query .= " AND (semester = ? OR FIND_IN_SET(?, semester) > 0)";
                $params[] = $semester;
                $params[] = $semester;
            }
            
            if ($section) {
                $query .= " AND (section = ? OR FIND_IN_SET(?, section) > 0)";
                $params[] = $section;
                $params[] = $section;
            }
            
            $query .= " ORDER BY title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getBooksByAcademicContext: " . $e->getMessage());
            return [];
        }
    }

    public function getBookById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getBookById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced addBook method with automatic archiving and multi-context support
     */
    public function addBook($data) {
        try {
            if (empty($data['title']) || empty($data['author']) || empty($data['category'])) {
                return false;
            }
            
            // Check if this book should be auto-archived
            $shouldArchive = $this->shouldAutoArchive($data['publication_year'] ?? null);
            
            // Check if this is a multi-context book
            $categories = is_array($data['category']) ? $data['category'] : explode(',', $data['category']);
            $yearLevels = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level'] ?? '');
            $semesters = is_array($data['semester']) ? $data['semester'] : explode(',', $data['semester'] ?? '');
            $sections = is_array($data['section']) ? $data['section'] : explode(',', $data['section'] ?? '');
            
            $isMultiContext = (count($categories) > 1 || count($yearLevels) > 1 || count($semesters) > 1 || count($sections) > 1) ? 1 : 0;
            
            // Convert arrays to comma-separated strings
            $categoryStr = is_array($data['category']) ? implode(',', $data['category']) : $data['category'];
            $yearLevelStr = is_array($data['year_level']) ? implode(',', $data['year_level']) : ($data['year_level'] ?? '');
            $semesterStr = is_array($data['semester']) ? implode(',', $data['semester']) : ($data['semester'] ?? '');
            $sectionStr = is_array($data['section']) ? implode(',', $data['section']) : ($data['section'] ?? '');
            
            // Prepare data with multi-context support
            $bookData = [
                'title' => $data['title'],
                'author' => $data['author'],
                'isbn' => $data['isbn'] ?? '',
                'category' => $categoryStr,
                'program' => $data['program'] ?? '',  // ADD PROGRAM FIELD
                'quantity' => $data['quantity'] ?? 1,
                'description' => $data['description'] ?? '',
                'subject_name' => $data['subject_name'] ?? '',
                'semester' => $semesterStr,
                'section' => $sectionStr,
                'year_level' => $yearLevelStr,
                'course_code' => $data['course_code'] ?? '',
                'publication_year' => $data['publication_year'] ?? null,
                'book_copy_number' => $data['book_copy_number'] ?? null,
                'total_quantity' => $data['total_quantity'] ?? null,
                'is_multi_context' => $isMultiContext,
                'same_book_series' => $data['same_book_series'] ?? 0
            ];
            
            if ($shouldArchive) {
                // Create archive table if needed
                $this->createArchivedBooksTable();
                
                // Add directly to archive instead of main table
                $result = $this->autoArchiveBook($bookData, 0); // 0 as placeholder for original_id
                
                if ($result) {
                    $this->logger->logBookActivity(
                        'add_archived',
                        ['title' => $data['title'], 'category' => $categoryStr],
                        "Book added directly to archive - Publication year: " . ($data['publication_year'] ?? 'unknown')
                    );
                    
                    return 'archived'; // Special return value to indicate archived
                }
                
                return false;
            }
            
            // Normal book addition (not old enough for archive)
            // ADD 'program' to the INSERT statement
            $stmt = $this->pdo->prepare("INSERT INTO books (title, author, isbn, category, program, quantity, description, subject_name, semester, section, year_level, course_code, publication_year, book_copy_number, total_quantity, is_multi_context, same_book_series) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $bookData['title'],
                $bookData['author'],
                $bookData['isbn'],
                $bookData['category'],
                $bookData['program'],  // ADD PROGRAM VALUE
                $bookData['quantity'],
                $bookData['description'],
                $bookData['subject_name'],
                $bookData['semester'],
                $bookData['section'],
                $bookData['year_level'],
                $bookData['course_code'],
                $bookData['publication_year'],
                $bookData['book_copy_number'],
                $bookData['total_quantity'],
                $bookData['is_multi_context'],
                $bookData['same_book_series']
            ]);
            
            if ($result) {
                $bookId = $this->pdo->lastInsertId();
                
                // Log the activity - include program info if available
                $additionalInfo = "Quantity: {$bookData['quantity']}, Author: {$bookData['author']}";
                if (!empty($bookData['publication_year'])) {
                    $additionalInfo .= ", Published: {$bookData['publication_year']}";
                }
                if (!empty($bookData['program'])) {
                    $additionalInfo .= ", Program: {$bookData['program']}";
                }
                if ($isMultiContext) {
                    $additionalInfo .= " (Multi-context)";
                }
                
                $this->logger->logBookActivity(
                    'add',
                    ['id' => $bookId, 'title' => $bookData['title'], 'category' => $bookData['category']],
                    $additionalInfo
                );
                
                return $bookId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error in addBook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the existing updateBook method to handle is_multi_context field
     */
    public function updateBook($id, $data) {
        try {
            if (empty($data['title']) || empty($data['author']) || empty($data['category'])) {
                return false;
            }
            
            // Get old book details for comparison
            $oldBook = $this->getBookById($id);
            
            // Check if this is a multi-context book
            $categories = is_array($data['category']) ? $data['category'] : explode(',', $data['category']);
            $yearLevels = is_array($data['year_level']) ? $data['year_level'] : explode(',', $data['year_level'] ?? '');
            $semesters = is_array($data['semester']) ? $data['semester'] : explode(',', $data['semester'] ?? '');
            $sections = is_array($data['section']) ? $data['section'] : explode(',', $data['section'] ?? '');
            
            $isMultiContext = (count($categories) > 1 || count($yearLevels) > 1 || count($semesters) > 1 || count($sections) > 1) ? 1 : 0;
            
            // Convert arrays to comma-separated strings
            $categoryStr = is_array($data['category']) ? implode(',', $data['category']) : $data['category'];
            $yearLevelStr = is_array($data['year_level']) ? implode(',', $data['year_level']) : ($data['year_level'] ?? '');
            $semesterStr = is_array($data['semester']) ? implode(',', $data['semester']) : ($data['semester'] ?? '');
            $sectionStr = is_array($data['section']) ? implode(',', $data['section']) : ($data['section'] ?? '');
            
            // Updated SQL to include is_multi_context
            $stmt = $this->pdo->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, description=?, subject_name=?, semester=?, section=?, year_level=?, course_code=?, publication_year=?, book_copy_number=?, total_quantity=?, is_multi_context=?, same_book_series=? WHERE id=?");
            $result = $stmt->execute([
                $data['title'],
                $data['author'],
                $data['isbn'] ?? '',
                $categoryStr,
                $data['quantity'] ?? 1,
                $data['description'] ?? '',
                $data['subject_name'] ?? '',
                $semesterStr,
                $sectionStr,
                $yearLevelStr,
                $data['course_code'] ?? '',
                $data['publication_year'] ?? null,
                $data['book_copy_number'] ?? null,
                $data['total_quantity'] ?? null,
                $isMultiContext,
                $data['same_book_series'] ?? 0,
                $id
            ]);
            
            if ($result) {
                // Log the activity with change details
                if ($oldBook) {
                    $changes = [];
                    
                    if ($oldBook['title'] !== $data['title']) {
                        $changes[] = "title changed from '{$oldBook['title']}' to '{$data['title']}'";
                    }
                    if ($oldBook['author'] !== $data['author']) {
                        $changes[] = "author changed from '{$oldBook['author']}' to '{$data['author']}'";
                    }
                    if ($oldBook['category'] !== $categoryStr) {
                        $changes[] = "category changed from '{$oldBook['category']}' to '{$categoryStr}'";
                    }
                    if ($oldBook['quantity'] != ($data['quantity'] ?? 1)) {
                        $changes[] = "quantity changed from {$oldBook['quantity']} to " . ($data['quantity'] ?? 1);
                    }
                    if (($oldBook['publication_year'] ?? '') != ($data['publication_year'] ?? '')) {
                        $oldYear = $oldBook['publication_year'] ?: 'not set';
                        $newYear = $data['publication_year'] ?: 'not set';
                        $changes[] = "publication year changed from {$oldYear} to {$newYear}";
                    }
                    
                    $additionalInfo = !empty($changes) ? implode(', ', $changes) : 'Minor updates';
                    
                    $bookData = [
                        'id' => $id,
                        'title' => $data['title'],
                        'category' => $categoryStr
                    ];
                    
                    $this->logger->logBookActivity('update', $bookData, $additionalInfo);
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error in updateBook: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteBook($id) {
        try {
            // Get book details before deletion for logging
            $book = $this->getBookById($id);
            
            $stmt = $this->pdo->prepare("DELETE FROM books WHERE id=?");
            $result = $stmt->execute([$id]);
            
            if ($result && $book) {
                // Log the activity
                $this->logger->logBookActivity(
                    'delete',
                    $book,
                    "Permanently removed from library"
                );
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error in deleteBook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enhanced book statistics with multi-context support
     */
    public function getBookStats() {
        try {
            $stats = [];
            
            // Total books
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM books");
            $stats['total_books'] = $stmt->fetch()['total'] ?? 0;
            
            // Total copies
            $stmt = $this->pdo->query("SELECT SUM(quantity) as total FROM books");
            $stats['total_copies'] = $stmt->fetch()['total'] ?? 0;
            
            // Multi-context books
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM books WHERE is_multi_context = 1");
            $stats['multi_context_books'] = $stmt->fetch()['total'] ?? 0;
            
            // Books by primary category (first category in comma-separated list)
            $stmt = $this->pdo->query("
                SELECT 
                    SUBSTRING_INDEX(category, ',', 1) as primary_category,
                    COUNT(*) as count 
                FROM books 
                GROUP BY primary_category
                ORDER BY count DESC
            ");
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // All category associations (including multi-context)
            $stmt = $this->pdo->query("
                SELECT 
                    'BIT' as category,
                    COUNT(*) as count 
                FROM books 
                WHERE FIND_IN_SET('BIT', category) > 0
                
                UNION ALL
                
                SELECT 
                    'EDUCATION' as category,
                    COUNT(*) as count 
                FROM books 
                WHERE FIND_IN_SET('EDUCATION', category) > 0
                
                UNION ALL
                
                SELECT 
                    'HBM' as category,
                    COUNT(*) as count 
                FROM books 
                WHERE FIND_IN_SET('HBM', category) > 0
                
                UNION ALL
                
                SELECT 
                    'COMPSTUD' as category,
                    COUNT(*) as count 
                FROM books 
                WHERE FIND_IN_SET('COMPSTUD', category) > 0
            ");
            $stats['by_category_expanded'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Books by publication year (recent years)
            $stmt = $this->pdo->query("SELECT publication_year, COUNT(*) as count FROM books WHERE publication_year IS NOT NULL AND publication_year >= YEAR(NOW()) - 10 GROUP BY publication_year ORDER BY publication_year DESC");
            $stats['by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Borrowed books
            $stmt = $this->pdo->query("SELECT COUNT(*) as borrowed_books FROM borrowing WHERE status = 'borrowed'");
            $borrowedStats = $stmt->fetch();
            $stats['borrowed_books'] = $borrowedStats['borrowed_books'] ?? 0;
            
            // Available copies (total copies - borrowed)
            $stats['available_copies'] = $stats['total_copies'] - $stats['borrowed_books'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getBookStats: " . $e->getMessage());
            return [
                'total_books' => 0,
                'total_copies' => 0,
                'borrowed_books' => 0,
                'available_copies' => 0,
                'multi_context_books' => 0,
                'by_category' => [],
                'by_category_expanded' => [],
                'by_year' => []
            ];
        }
    }

    /**
     * Get detailed report data with multi-context display
     */
    public function getDetailedReportData($department = 'all', $sortBy = 'title') {
        try {
            $query = "SELECT *, DATE_FORMAT(created_at, '%Y') as copyright_year FROM books";
            $params = [];
            
            if ($department && $department !== 'all') {
                $query .= " WHERE (category = ? OR FIND_IN_SET(?, category) > 0)";
                $params[] = $department;
                $params[] = $department;
            }
            
            // Add sorting
            switch ($sortBy) {
                case 'author':
                    $query .= " ORDER BY author ASC, title ASC";
                    break;
                case 'category':
                    $query .= " ORDER BY category ASC, title ASC";
                    break;
                case 'date':
                    $query .= " ORDER BY created_at DESC, title ASC";
                    break;
                case 'year':
                    $query .= " ORDER BY publication_year DESC, title ASC";
                    break;
                case 'contexts':
                    $query .= " ORDER BY is_multi_context DESC, title ASC";
                    break;
                default:
                    $query .= " ORDER BY title ASC";
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process results to show readable multi-context information
            foreach ($results as &$book) {
                $book['categories_display'] = str_replace(',', ', ', $book['category']);
                $book['year_levels_display'] = $book['year_level'] ? str_replace(',', ', ', $book['year_level']) : 'All';
                $book['semesters_display'] = $book['semester'] ? str_replace(',', ', ', $book['semester']) : 'All';
                $book['sections_display'] = $book['section'] ? str_replace(',', ', ', $book['section']) : 'All';
                $book['is_multi_context_display'] = $book['is_multi_context'] ? 'Yes' : 'No';
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getDetailedReportData: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enhanced CSV export with multi-context information
     */
    public function exportToCSV($department = 'all') {
        try {
            $books = $this->getDetailedReportData($department);
            
            $headers = [
                'ID', 'Title', 'Author', 'ISBN', 'Categories', 
                'Quantity', 'Publication Year', 'Subject Name', 'Course Code',
                'Year Levels', 'Semesters', 'Sections', 'Multi-Context', 
                'Description', 'Date Added', 'Last Updated'
            ];
            
            $csvData = [];
            $csvData[] = $headers;
            
            foreach ($books as $book) {
                $csvData[] = [
                    $book['id'],
                    $book['title'],
                    $book['author'],
                    $book['isbn'],
                    $book['categories_display'],
                    $book['quantity'],
                    $book['publication_year'] ?? '',
                    $book['subject_name'] ?? '',
                    $book['course_code'] ?? '',
                    $book['year_levels_display'],
                    $book['semesters_display'],
                    $book['sections_display'],
                    $book['is_multi_context_display'],
                    $book['description'],
                    $book['created_at'],
                    $book['updated_at']
                ];
            }
            
            // Log export activity
            $departmentText = ($department === 'all') ? 'all departments' : $department . ' department';
            $this->logger->logUserActivity(
                'export',
                "Exported books data in CSV format (" . count($books) . " books from " . $departmentText . ") with multi-context support"
            );
            
            return $csvData;
        } catch (PDOException $e) {
            error_log("Error in exportToCSV: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Utility method to parse comma-separated fields back to arrays
     */
    public function parseBookContexts($book) {
        return [
            'categories' => $book['category'] ? explode(',', $book['category']) : [],
            'year_levels' => $book['year_level'] ? explode(',', $book['year_level']) : [],
            'semesters' => $book['semester'] ? explode(',', $book['semester']) : [],
            'sections' => $book['section'] ? explode(',', $book['section']) : []
        ];
    }

    /**
     * Check if a book is available for a specific academic context
     */
    public function isBookAvailableForContext($bookId, $category, $yearLevel = '', $semester = '', $section = '') {
        try {
            $book = $this->getBookById($bookId);
            if (!$book || $book['quantity'] <= 0) {
                return false;
            }
            
            // Check category
            $categories = explode(',', $book['category']);
            if (!in_array($category, $categories)) {
                return false;
            }
            
            // Check year level if specified
            if ($yearLevel && $book['year_level']) {
                $yearLevels = explode(',', $book['year_level']);
                if (!in_array($yearLevel, $yearLevels)) {
                    return false;
                }
            }
            
            // Check semester if specified
            if ($semester && $book['semester']) {
                $semesters = explode(',', $book['semester']);
                if (!in_array($semester, $semesters)) {
                    return false;
                }
            }
            
            // Check section if specified
            if ($section && $book['section']) {
                $sections = explode(',', $book['section']);
                if (!in_array($section, $sections)) {
                    return false;
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error in isBookAvailableForContext: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get books that need database field updates (migrate old single-context books)
     */
    public function getBooksNeedingContextUpdate() {
        try {
            // Find books that don't have the is_multi_context field set properly
            $stmt = $this->pdo->query("
                SELECT * FROM books 
                WHERE is_multi_context IS NULL 
                OR is_multi_context = 0 AND (
                    category LIKE '%,%' 
                    OR year_level LIKE '%,%' 
                    OR semester LIKE '%,%' 
                    OR section LIKE '%,%'
                )
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getBooksNeedingContextUpdate: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update is_multi_context field for existing books
     */
    public function updateMultiContextFlags() {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE books 
                SET is_multi_context = CASE 
                    WHEN category LIKE '%,%' 
                        OR year_level LIKE '%,%' 
                        OR semester LIKE '%,%' 
                        OR section LIKE '%,%' 
                    THEN 1 
                    ELSE 0 
                END
            ");
            
            $result = $stmt->execute();
            $updatedCount = $stmt->rowCount();
            
            if ($result) {
                $this->logger->logUserActivity('system', "Updated is_multi_context flags for {$updatedCount} books");
            }
            
            return $updatedCount;
        } catch (PDOException $e) {
            error_log("Error in updateMultiContextFlags: " . $e->getMessage());
            return false;
        }
    }
    
    // Borrowing methods with activity logging
    public function borrowBook($bookId, $borrowerName, $borrowerEmail, $dueDate) {
        try {
            $this->pdo->beginTransaction();
            
            // Get book details
            $book = $this->getBookById($bookId);
            if (!$book || $book['quantity'] <= 0) {
                throw new Exception("Book not available for borrowing");
            }
            
            // Add borrowing record
            $sql = "INSERT INTO borrowing (book_id, borrower_name, borrower_email, due_date) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$bookId, $borrowerName, $borrowerEmail, $dueDate]);
            
            // Update book quantity
            $sql = "UPDATE books SET quantity = quantity - 1 WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$bookId]);
            
            $this->pdo->commit();
            
            // Log the borrowing activity
            $this->logger->logBorrowingActivity('borrow', $book['title'], $borrowerName, "Due: {$dueDate}");
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Book borrowing failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function returnBook($borrowingId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get borrowing details
            $sql = "SELECT b.*, bk.title FROM borrowing b 
                    JOIN books bk ON b.book_id = bk.id 
                    WHERE b.id = ? AND b.status = 'borrowed'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$borrowingId]);
            $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$borrowing) {
                throw new Exception("Borrowing record not found or already returned");
            }
            
            // Update borrowing record
            $sql = "UPDATE borrowing SET returned_date = NOW(), status = 'returned' WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$borrowingId]);
            
            // Update book quantity
            $sql = "UPDATE books SET quantity = quantity + 1 WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$borrowing['book_id']]);
            
            $this->pdo->commit();
            
            // Log the return activity
            $returnStatus = (strtotime($borrowing['due_date']) < time()) ? "(Late return)" : "";
            $this->logger->logBorrowingActivity('return', $borrowing['title'], $borrowing['borrower_name'], $returnStatus);
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Book return failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDepartmentStatistics() {
        try {
            $query = "
                SELECT 
                    category,
                    COUNT(*) as title_count,
                    SUM(quantity) as volume_count,
                    MIN(created_at) as oldest_book,
                    MAX(created_at) as newest_book,
                    AVG(quantity) as avg_copies,
                    MIN(publication_year) as oldest_publication,
                    MAX(publication_year) as newest_publication,
                    AVG(publication_year) as avg_publication_year
                FROM books 
                GROUP BY category
                ORDER BY category
            ";
            
            $stmt = $this->pdo->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate percentages
            $totalTitles = array_sum(array_column($results, 'title_count'));
            $totalVolumes = array_sum(array_column($results, 'volume_count'));
            
            foreach ($results as &$result) {
                $result['title_percentage'] = $totalTitles > 0 ? round(($result['title_count'] / $totalTitles) * 100, 1) : 0;
                $result['volume_percentage'] = $totalVolumes > 0 ? round(($result['volume_count'] / $totalVolumes) * 100, 1) : 0;
                $result['avg_copies'] = round($result['avg_copies'], 1);
                $result['avg_publication_year'] = $result['avg_publication_year'] ? round($result['avg_publication_year']) : null;
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getDepartmentStatistics: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAuthorStatistics() {
        try {
            $query = "
                SELECT 
                    author,
                    COUNT(*) as book_count,
                    SUM(quantity) as total_copies,
                    GROUP_CONCAT(DISTINCT category) as categories,
                    MIN(publication_year) as earliest_publication,
                    MAX(publication_year) as latest_publication
                FROM books 
                GROUP BY author
                HAVING book_count > 1
                ORDER BY book_count DESC, author ASC
                LIMIT 20
            ";
            
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAuthorStatistics: " . $e->getMessage());
            return [];
        }
    }
    
    public function getCollectionGrowth() {
        try {
            $query = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as books_added,
                    SUM(quantity) as copies_added
                FROM books 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ";
            
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getCollectionGrowth: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLowStockBooks($threshold = 3) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM books 
                WHERE quantity <= ? 
                ORDER BY quantity ASC, title ASC
            ");
            $stmt->execute([$threshold]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getLowStockBooks: " . $e->getMessage());
            return [];
        }
    }
    
    public function getRecentlyAddedBooks($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM books 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getRecentlyAddedBooks: " . $e->getMessage());
            return [];
        }
    }
    
    // New method to get books by publication year range
    public function getBooksByYearRange($startYear = null, $endYear = null) {
        try {
            $query = "SELECT * FROM books WHERE 1=1";
            $params = [];
            
            if ($startYear) {
                $query .= " AND (publication_year >= ? OR publication_year IS NULL)";
                $params[] = $startYear;
            }
            
            if ($endYear) {
                $query .= " AND (publication_year <= ? OR publication_year IS NULL)";
                $params[] = $endYear;
            }
            
            $query .= " ORDER BY publication_year DESC, title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getBooksByYearRange: " . $e->getMessage());
            return [];
        }
    }
    
    // New method to get publication year statistics
    public function getPublicationYearStats() {
        try {
            $query = "
                SELECT 
                    CASE 
                        WHEN publication_year IS NULL THEN 'Unknown'
                        WHEN publication_year >= YEAR(NOW()) - 5 THEN 'Recent (5 years)'
                        WHEN publication_year >= YEAR(NOW()) - 10 THEN 'Moderate (6-10 years)'
                        ELSE 'Older (10+ years)'
                    END as age_group,
                    COUNT(*) as book_count,
                    SUM(quantity) as total_copies
                FROM books 
                GROUP BY age_group
                ORDER BY 
                    CASE age_group
                        WHEN 'Recent (5 years)' THEN 1
                        WHEN 'Moderate (6-10 years)' THEN 2
                        WHEN 'Older (10+ years)' THEN 3
                        WHEN 'Unknown' THEN 4
                    END
            ";
            
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getPublicationYearStats: " . $e->getMessage());
            return [];
        }
    }
    
    public function searchBooks($searchTerm, $searchFields = ['title', 'author', 'description']) {
        try {
            $conditions = [];
            $params = [];
            
            foreach ($searchFields as $field) {
                $conditions[] = "$field LIKE ?";
                $params[] = "%$searchTerm%";
            }
            
            $query = "SELECT * FROM books WHERE " . implode(' OR ', $conditions) . " ORDER BY title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            // Log search activity
            $this->logger->logUserActivity('search', "Searched for: \"{$searchTerm}\"");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in searchBooks: " . $e->getMessage());
            return [];
        }
    }
    
    // Bulk operations with activity logging
    public function bulkImport($books) {
        try {
            $importedCount = 0;
            $archivedCount = 0;
            $errors = [];
            
            $this->pdo->beginTransaction();
            
            foreach ($books as $index => $bookData) {
                $result = $this->addBook($bookData);
                if ($result === 'archived') {
                    $archivedCount++;
                } elseif ($result) {
                    $importedCount++;
                } else {
                    $errors[] = "Row " . ($index + 1) . ": Failed to import book";
                }
            }
            
            $this->pdo->commit();
            
            // Log import activity
            $description = "Imported {$importedCount} books to active collection";
            if ($archivedCount > 0) {
                $description .= " and {$archivedCount} books to archive";
            }
            if (!empty($errors)) {
                $description .= " with " . count($errors) . " errors";
            }
            
            $this->logger->logUserActivity('import', $description);
            
            return [
                'success' => true,
                'imported' => $importedCount,
                'archived' => $archivedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error in bulkImport: " . $e->getMessage());
            return [
                'success' => false,
                'imported' => 0,
                'archived' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    public function bulkDelete($ids) {
        try {
            if (empty($ids)) {
                return false;
            }
            
            // Get book details before deletion
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $this->pdo->prepare("SELECT id, title, category FROM books WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete books
            $stmt = $this->pdo->prepare("DELETE FROM books WHERE id IN ($placeholders)");
            $result = $stmt->execute($ids);
            
            if ($result) {
                $titles = array_column($books, 'title');
                $this->logger->logUserActivity(
                    'delete',
                    'Bulk deleted ' . count($books) . ' books: ' . implode(', ', array_slice($titles, 0, 3)) . (count($titles) > 3 ? '...' : '')
                );
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error in bulkDelete: " . $e->getMessage());
            return false;
        }
    }
    
    // Activity logging helper methods
    public function logCustomActivity($action, $description, $bookId = null, $bookTitle = null, $category = null) {
        return $this->logger->log($action, $description, $bookId, $bookTitle, $category);
    }
    
    public function getActivityLog($limit = 10) {
        return $this->logger->getRecentActivities($limit);
    }
    
    public function getOverdueBooks() {
        try {
            $sql = "SELECT b.*, bk.title, bk.author FROM borrowing b 
                    JOIN books bk ON b.book_id = bk.id 
                    WHERE b.status = 'borrowed' AND b.due_date < CURDATE()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get overdue books: " . $e->getMessage());
            return [];
        }
    }
    
    public function markOverdueBooks() {
        try {
            $sql = "UPDATE borrowing SET status = 'overdue' 
                    WHERE status = 'borrowed' AND due_date < CURDATE()";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute();
            
            if ($result && $stmt->rowCount() > 0) {
                // Log overdue marking
                $this->logger->logUserActivity('system', "Marked {$stmt->rowCount()} books as overdue");
            }
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Failed to mark overdue books: " . $e->getMessage());
            return false;
        }
    }
    
    public function getBorrowingHistory($limit = 20) {
        try {
            $sql = "SELECT b.*, bk.title, bk.author, bk.category 
                    FROM borrowing b 
                    JOIN books bk ON b.book_id = bk.id 
                    ORDER BY b.borrowed_date DESC 
                    LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get borrowing history: " . $e->getMessage());
            return [];
        }
    }
    
    public function getCurrentBorrowings() {
        try {
            $sql = "SELECT b.*, bk.title, bk.author, bk.category 
                    FROM borrowing b 
                    JOIN books bk ON b.book_id = bk.id 
                    WHERE b.status IN ('borrowed', 'overdue')
                    ORDER BY b.due_date ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get current borrowings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all archived books
     */
    public function getArchivedBooks($category = '', $search = '') {
        try {
            $this->createArchivedBooksTable();
            
            $query = "SELECT * FROM archived_books WHERE 1=1";
            $params = [];
            
            if ($category) {
                $query .= " AND category = ?";
                $params[] = $category;
            }
            
            if ($search) {
                $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $query .= " ORDER BY archived_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getArchivedBooks: " . $e->
            getMessage());
            return [];
        }
    }

    /**
     * Get books eligible for archiving (10+ years old)
     */
    public function getBooksEligibleForArchiving() {
        try {
            $currentYear = date('Y');
            $cutoffYear = $currentYear - 10;
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM books 
                WHERE publication_year IS NOT NULL 
                AND publication_year <= ? 
                ORDER BY publication_year ASC, title ASC
            ");
            $stmt->execute([$cutoffYear]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getBooksEligibleForArchiving: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Manually archive a book
     */
    public function archiveBook($data, $reason, $archivedBy) {
        try {
            // Insert into archived_books table
            $sql = "INSERT INTO archived_books (
                original_id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_context, same_book_series,
                original_created_at, original_updated_at, archive_reason, archived_by, archiving_method
            ) VALUES (
                :original_id, :title, :author, :isbn, :category, :quantity, :description,
                :subject_name, :semester, :section, :year_level, :course_code, :publication_year,
                :book_copy_number, :total_quantity, :is_multi_context, :same_book_series,
                NOW(), NOW(), :archive_reason, :archived_by, 'manual'
            )";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Use 0 for original_id since we're archiving directly
            $stmt->bindValue(':original_id', 0, PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title']);
            $stmt->bindValue(':author', $data['author']);
            $stmt->bindValue(':isbn', $data['isbn'] ?? '');
            $stmt->bindValue(':category', $data['category']);
            $stmt->bindValue(':quantity', $data['quantity']);
            $stmt->bindValue(':description', $data['description'] ?? '');
            $stmt->bindValue(':subject_name', $data['subject_name'] ?? '');
            $stmt->bindValue(':semester', $data['semester'] ?? '');
            $stmt->bindValue(':section', $data['section'] ?? '');
            $stmt->bindValue(':year_level', $data['year_level'] ?? '');
            $stmt->bindValue(':course_code', $data['course_code'] ?? '');
            $stmt->bindValue(':publication_year', $data['publication_year']);
            $stmt->bindValue(':book_copy_number', $data['book_copy_number']);
            $stmt->bindValue(':total_quantity', $data['total_quantity']);
            $stmt->bindValue(':is_multi_context', $data['is_multi_context']);
            $stmt->bindValue(':same_book_series', $data['same_book_series']);
            $stmt->bindValue(':archive_reason', $reason);
            $stmt->bindValue(':archived_by', $archivedBy);
            
            if ($stmt->execute()) {
                // Log the archiving activity
                $this->logActivity(
                    'manual_archive',
                    "Manually archived book: \"{$data['title']}\" - Reason: {$reason}",
                    null,
                    $data['title'],
                    $data['category'],
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['user_name'] ?? 'System'
                );
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Archive Book Error: " . $e->getMessage());
            return false;
        }
    }

    // Add this method to your Book class
    public function logActivity($action, $description, $book_id = null, $book_title = null, $category = null, $user_id = null, $user_name = null) {
        try {
            $sql = "INSERT INTO activity_logs (action, description, book_id, book_title, category, user_id, user_name, ip_address, user_agent, created_at) 
                    VALUES (:action, :description, :book_id, :book_title, :category, :user_id, :user_name, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->bindValue(':action', $action);
            $stmt->bindValue(':description', $description);
            $stmt->bindValue(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->bindValue(':book_title', $book_title);
            $stmt->bindValue(':category', $category);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_name', $user_name);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Log Activity Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore a book from archive
     */
    public function restoreFromArchive($archivedBookId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get archived book details
            $stmt = $this->pdo->prepare("SELECT * FROM archived_books WHERE id = ?");
            $stmt->execute([$archivedBookId]);
            $archivedBook = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$archivedBook) {
                throw new Exception("Archived book not found");
            }
            
            // Insert back into active books
            $sql = "INSERT INTO books (
                title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_context, same_book_series
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
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
                $archivedBook['is_multi_context'],
                $archivedBook['same_book_series']
            ]);
            
            if (!$result) {
                throw new Exception("Failed to restore to active books");
            }
            
            $newBookId = $this->pdo->lastInsertId();
            
            // Remove from archive
            $stmt = $this->pdo->prepare("DELETE FROM archived_books WHERE id = ?");
            $deleteResult = $stmt->execute([$archivedBookId]);
            
            if (!$deleteResult) {
                throw new Exception("Failed to remove from archive");
            }
            
            $this->pdo->commit();
            
            // Log the restore activity
            $this->logger->logBookActivity(
                'restore',
                ['id' => $newBookId, 'title' => $archivedBook['title'], 'category' => $archivedBook['category']],
                "Restored from archive"
            );
            
            return $newBookId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Restore from archive failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk archive books that are eligible for archiving
     */
    public function bulkArchiveEligibleBooks($reason = 'Bulk archiving - 10+ years old', $archivedBy = 'System') {
        try {
            $eligibleBooks = $this->getBooksEligibleForArchiving();
            $archivedCount = 0;
            $errors = [];
            
            foreach ($eligibleBooks as $book) {
                if ($this->archiveBook($book['id'], $reason, $archivedBy)) {
                    $archivedCount++;
                } else {
                    $errors[] = "Failed to archive: " . $book['title'];
                }
            }
            
            // Log bulk archive activity
            $description = "Bulk archived {$archivedCount} books";
            if (!empty($errors)) {
                $description .= " with " . count($errors) . " errors";
            }
            
            $this->logger->logUserActivity('bulk_archive', $description);
            
            return [
                'success' => true,
                'archived' => $archivedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("Error in bulkArchiveEligibleBooks: " . $e->getMessage());
            return [
                'success' => false,
                'archived' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Debug database method - complete implementation
     */
    public function debugDatabase() {
        try {
            $debug = [];
            
            // Check if books table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'books'");
            $tableExists = $stmt->fetch();
            
            if (!$tableExists) {
                $debug['error'] = "Books table does not exist!";
                return $debug;
            }
            
            // Get total book count
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM books");
            $count = $stmt->fetch()['count'];
            $debug['total_books'] = $count;
            
            // Get table schema
            $debug['table_schema'] = $this->getTableSchema();
            
            // Check enhanced columns
            $debug['enhanced_columns'] = $this->checkEnhancedColumns();
            
            // Check activity logging
            if ($this->logger) {
                $debug['activity_logging_enabled'] = true;
                $recentActivities = $this->logger->getRecentActivities(5);
                $debug['recent_activities_count'] = count($recentActivities);
                $debug['recent_activities'] = $recentActivities;
            } else {
                $debug['activity_logging_enabled'] = false;
            }
            
            // Check archive table
            $this->createArchivedBooksTable();
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM archived_books");
            $archivedCount = $stmt->fetch()['count'];
            $debug['archived_books'] = $archivedCount;
            
            // Check borrowing table
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM borrowing");
                $borrowingCount = $stmt->fetch()['count'];
                $debug['borrowing_records'] = $borrowingCount;
            } catch (PDOException $e) {
                $debug['borrowing_table_error'] = $e->getMessage();
            }
            
            return $debug;
            
        } catch (PDOException $e) {
            error_log("Database debug error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get comprehensive system status
     */
    public function getSystemStatus() {
        try {
            $status = [];
            
            // Database connection status
            $status['database_connected'] = $this->pdo ? true : false;
            
            // Table existence checks
            $tables = ['books', 'archived_books', 'borrowing', 'activity_log'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                $status['tables'][$table] = $stmt->fetch() ? 'exists' : 'missing';
            }
            
            // Column checks for books table
            $status['enhanced_features'] = $this->checkEnhancedColumns();
            
            // Data counts
            $status['data_counts'] = [
                'active_books' => 0,
                'archived_books' => 0,
                'borrowing_records' => 0,
                'activity_logs' => 0
            ];
            
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM books");
                $status['data_counts']['active_books'] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $status['errors'][] = "Cannot count active books: " . $e->getMessage();
            }
            
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM archived_books");
                $status['data_counts']['archived_books'] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $status['data_counts']['archived_books'] = 'N/A';
            }
            
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM borrowing");
                $status['data_counts']['borrowing_records'] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $status['data_counts']['borrowing_records'] = 'N/A';
            }
            
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM activity_log");
                $status['data_counts']['activity_logs'] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $status['data_counts']['activity_logs'] = 'N/A';
            }
            
            // Activity logger status
            $status['activity_logger'] = $this->logger ? 'initialized' : 'not_initialized';
            
            // Archive statistics
            $status['archive_stats'] = $this->getArchiveStats();
            
            return $status;
            
        } catch (Exception $e) {
            error_log("Error in getSystemStatus: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Initialize all required database enhancements and tables
     */
    public function initializeCompleteSystem() {
        try {
            $results = [];
            
            // Initialize enhanced columns
            $results['enhanced_columns'] = $this->addEnhancedColumns();
            
            // Create archived books table
            $results['archive_table'] = $this->createArchivedBooksTable();
            
            // Update multi-context flags for existing books
            $updatedCount = $this->updateMultiContextFlags();
            $results['multi_context_update'] = $updatedCount !== false ? $updatedCount : 'failed';
            
            // Log system initialization
            $this->logger->logUserActivity('system', 'Complete system initialization performed');
            
            $results['success'] = true;
            return $results;
            
        } catch (Exception $e) {
            error_log("Error in initializeCompleteSystem: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get book availability summary
     */
    public function getAvailabilitySummary() {
        try {
            $summary = [];
            
            // Total books and copies
            $stmt = $this->pdo->query("SELECT COUNT(*) as titles, SUM(quantity) as copies FROM books");
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            $summary['total_titles'] = $totals['titles'];
            $summary['total_copies'] = $totals['copies'];
            
            // Available vs borrowed
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as borrowed FROM borrowing WHERE status IN ('borrowed', 'overdue')");
                $borrowed = $stmt->fetch()['borrowed'];
                $summary['borrowed_copies'] = $borrowed;
                $summary['available_copies'] = $summary['total_copies'] - $borrowed;
            } catch (PDOException $e) {
                $summary['borrowed_copies'] = 'N/A';
                $summary['available_copies'] = $summary['total_copies'];
            }
            
            // Low stock books
            $lowStockBooks = $this->getLowStockBooks(3);
            $summary['low_stock_count'] = count($lowStockBooks);
            
            // Out of stock books
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM books WHERE quantity = 0");
            $summary['out_of_stock_count'] = $stmt->fetch()['count'];
            
            return $summary;
            
        } catch (PDOException $e) {
            error_log("Error in getAvailabilitySummary: " . $e->getMessage());
            return [];
        }
    }
}
?>
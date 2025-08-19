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
                is_multi_record TINYINT(1) DEFAULT 0,
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
            return false; // Don't archive books without publication year
        }
        
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 10;
        
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
                book_copy_number, total_quantity, is_multi_record, same_book_series,
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
                $bookData['is_multi_record'] ?? 0,
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
    
    public function getAllBooks($category = '', $search = '') {
        try {
            $query = "SELECT * FROM books WHERE 1=1";
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
            
            $query .= " ORDER BY title ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllBooks: " . $e->getMessage());
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
     * Enhanced addBook method with automatic archiving
     */
    public function addBook($data) {
        try {
            if (empty($data['title']) || empty($data['author']) || empty($data['category'])) {
                return false;
            }
            
            // Check if this book should be auto-archived
            $shouldArchive = $this->shouldAutoArchive($data['publication_year'] ?? null);
            
            if ($shouldArchive) {
                // Create archive table if needed
                $this->createArchivedBooksTable();
                
                // Add directly to archive instead of main table
                $result = $this->autoArchiveBook($data, 0); // 0 as placeholder for original_id
                
                if ($result) {
                    $this->logger->logBookActivity(
                        'add_archived',
                        ['title' => $data['title'], 'category' => $data['category']],
                        "Book added directly to archive - Publication year: " . ($data['publication_year'] ?? 'unknown')
                    );
                    
                    return 'archived'; // Special return value to indicate archived
                }
                
                return false;
            }
            
            // Normal book addition (not old enough for archive)
            $stmt = $this->pdo->prepare("INSERT INTO books (title, author, isbn, category, quantity, description, subject_name, semester, section, year_level, course_code, publication_year, book_copy_number, total_quantity, is_multi_record, same_book_series) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                $data['course_code'] ?? '',
                $data['publication_year'] ?? null,
                $data['book_copy_number'] ?? null,
                $data['total_quantity'] ?? null,
                $data['is_multi_record'] ?? 0,
                $data['same_book_series'] ?? 0
            ]);
            
            if ($result) {
                $bookId = $this->pdo->lastInsertId();
                
                // Log the activity
                $additionalInfo = "Quantity: {$data['quantity']}, Author: {$data['author']}";
                if (!empty($data['publication_year'])) {
                    $additionalInfo .= ", Published: {$data['publication_year']}";
                }
                
                $this->logger->logBookActivity(
                    'add',
                    ['id' => $bookId, 'title' => $data['title'], 'category' => $data['category']],
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
    
    public function updateBook($id, $data) {
        try {
            if (empty($data['title']) || empty($data['author']) || empty($data['category'])) {
                return false;
            }
            
            // Get old book details for comparison
            $oldBook = $this->getBookById($id);
            
            // Updated SQL to include publication_year and additional fields
            $stmt = $this->pdo->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, description=?, subject_name=?, semester=?, section=?, year_level=?, course_code=?, publication_year=?, book_copy_number=?, total_quantity=?, is_multi_record=?, same_book_series=? WHERE id=?");
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
                $data['course_code'] ?? '',
                $data['publication_year'] ?? null,
                $data['book_copy_number'] ?? null,
                $data['total_quantity'] ?? null,
                $data['is_multi_record'] ?? 0,
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
                    if ($oldBook['category'] !== $data['category']) {
                        $changes[] = "category changed from '{$oldBook['category']}' to '{$data['category']}'";
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
                        'category' => $data['category']
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
    
    public function getBookStats() {
        try {
            $stats = [];
            
            // Total books
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM books");
            $stats['total_books'] = $stmt->fetch()['total'] ?? 0;
            
            // Total copies
            $stmt = $this->pdo->query("SELECT SUM(quantity) as total FROM books");
            $stats['total_copies'] = $stmt->fetch()['total'] ?? 0;
            
            // Books by category
            $stmt = $this->pdo->query("SELECT category, COUNT(*) as count FROM books GROUP BY category");
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                'by_category' => [],
                'by_year' => []
            ];
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
    
    // Enhanced report methods
    public function getDetailedReportData($department = 'all', $sortBy = 'title') {
        try {
            $query = "SELECT *, DATE_FORMAT(created_at, '%Y') as copyright_year FROM books";
            $params = [];
            
            if ($department && $department !== 'all') {
                $query .= " WHERE category = ?";
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
                default:
                    $query .= " ORDER BY title ASC";
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getDetailedReportData: " . $e->getMessage());
            return [];
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
    
    public function exportToCSV($department = 'all') {
        try {
            $books = $this->getDetailedReportData($department);
            
            $headers = [
                'ID', 'Title', 'Author', 'ISBN', 'Category', 
                'Quantity', 'Publication Year', 'Subject Name', 'Course Code',
                'Year Level', 'Semester', 'Section', 'Description', 
                'Date Added', 'Last Updated'
            ];
            
            $csvData = [];
            $csvData[] = $headers;
            
            foreach ($books as $book) {
                $csvData[] = [
                    $book['id'],
                    $book['title'],
                    $book['author'],
                    $book['isbn'],
                    $book['category'],
                    $book['quantity'],
                    $book['publication_year'] ?? '',
                    $book['subject_name'] ?? '',
                    $book['course_code'] ?? '',
                    $book['year_level'] ?? '',
                    $book['semester'] ?? '',
                    $book['section'] ?? '',
                    $book['description'],
                    $book['created_at'],
                    $book['updated_at']
                ];
            }
            
            // Log export activity
            $departmentText = ($department === 'all') ? 'all departments' : $department . ' department';
            $this->logger->logUserActivity(
                'export',
                "Exported books data in CSV format (" . count($books) . " books from " . $departmentText . ")"
            );
            
            return $csvData;
        } catch (PDOException $e) {
            error_log("Error in exportToCSV: " . $e->getMessage());
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
            error_log("Error in getArchivedBooks: " . $e->getMessage());
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
    public function archiveBook($bookId, $reason = 'Manual archiving', $archivedBy = 'Admin') {
        try {
            $this->pdo->beginTransaction();
            
            // Get book details
            $book = $this->getBookById($bookId);
            if (!$book) {
                throw new Exception("Book not found");
            }
            
            // Create archive table if needed
            $this->createArchivedBooksTable();
            
            // Insert into archived_books
            $sql = "INSERT INTO archived_books (
                original_id, title, author, isbn, category, quantity, description, 
                subject_name, semester, section, year_level, course_code, publication_year,
                book_copy_number, total_quantity, is_multi_record, same_book_series,
                original_created_at, original_updated_at, archived_at, archive_reason, archived_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $book['id'],
                $book['title'],
                $book['author'],
                $book['isbn'] ?? '',
                $book['category'],
                $book['quantity'] ?? 1,
                $book['description'] ?? '',
                $book['subject_name'] ?? '',
                $book['semester'] ?? '',
                $book['section'] ?? '',
                $book['year_level'] ?? '',
                $book['course_code'] ?? '',
                $book['publication_year'] ?? null,
                $book['book_copy_number'] ?? null,
                $book['total_quantity'] ?? null,
                $book['is_multi_record'] ?? 0,
                $book['same_book_series'] ?? 0,
                $book['created_at'] ?? null,
                $book['updated_at'] ?? null,
                $reason,
                $archivedBy
            ]);
            
            if (!$result) {
                throw new Exception("Failed to insert into archive");
            }
            
            // Delete from active books
            $stmt = $this->pdo->prepare("DELETE FROM books WHERE id = ?");
            $deleteResult = $stmt->execute([$bookId]);
            
            if (!$deleteResult) {
                throw new Exception("Failed to remove from active books");
            }
            
            $this->pdo->commit();
            
            // Log the archive activity
            $this->logger->logBookActivity(
                'archive',
                ['id' => $bookId, 'title' => $book['title'], 'category' => $book['category']],
                "Manually archived - Reason: {$reason}"
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Manual archive failed: " . $e->getMessage());
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
                book_copy_number, total_quantity, is_multi_record, same_book_series
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
                $archivedBook['is_multi_record'],
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
    
    // Debug method
    public function debugDatabase() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'books'");
            $tableExists = $stmt->fetch();
            
            if (!$tableExists) {
                error_log("ERROR: 'books' table does not exist!");
                return false;
            }
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM books");
            $count = $stmt->fetch()['count'];
            error_log("Total books in database: " . $count);
            
            // Check if activity logging is working
            if ($this->logger) {
                error_log("Activity logging is enabled");
                $recentActivities = $this->logger->getRecentActivities(5);
                error_log("Recent activities count: " . count($recentActivities));
            } else {
                error_log("Activity logging is NOT enabled");
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Database debug error: " . $e->getMessage());
            return false;
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
                'is_multi_record',
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
                    case 'is_multi_record':
                        $alterStatements[] = "ADD COLUMN is_multi_record TINYINT(1) DEFAULT 0";
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
}
?>
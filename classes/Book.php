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
    
    public function addBook($data) {
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
                
                // Log the activity using the new ActivityLogger
                $bookData = [
                    'id' => $bookId,
                    'title' => $data['title'],
                    'category' => $data['category']
                ];
                
                $this->logger->logBookActivity(
                    'add',
                    $bookData,
                    "Quantity: {$data['quantity']}, Author: {$data['author']}"
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
            
            $stmt = $this->pdo->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, description=?, subject_name=?, semester=?, section=?, year_level=?, course_code=? WHERE id=?");
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
                'by_category' => []
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
                    AVG(quantity) as avg_copies
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
                    GROUP_CONCAT(DISTINCT category) as categories
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
                'Quantity', 'Description', 'Date Added', 'Last Updated'
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
            $errors = [];
            
            $this->pdo->beginTransaction();
            
            foreach ($books as $index => $bookData) {
                if ($this->addBook($bookData)) {
                    $importedCount++;
                } else {
                    $errors[] = "Row " . ($index + 1) . ": Failed to import book";
                }
            }
            
            $this->pdo->commit();
            
            // Log import activity
            $description = "Imported {$importedCount} books";
            if (!empty($errors)) {
                $description .= " with " . count($errors) . " errors";
            }
            
            $this->logger->logUserActivity('import', $description);
            
            return [
                'success' => true,
                'imported' => $importedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error in bulkImport: " . $e->getMessage());
            return [
                'success' => false,
                'imported' => 0,
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
}
?>
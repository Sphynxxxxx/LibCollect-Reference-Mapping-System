<?php
class Book {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
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
            return $stmt->execute([
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
            
            $stmt = $this->pdo->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, description=?, subject_name=?, semester=?, section=?, year_level=?, course_code=? WHERE id=?");
            return $stmt->execute([
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
        } catch (PDOException $e) {
            error_log("Error in updateBook: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteBook($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM books WHERE id=?");
            return $stmt->execute([$id]);
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
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getBookStats: " . $e->getMessage());
            return [
                'total_books' => 0,
                'total_copies' => 0,
                'by_category' => []
            ];
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
            $stats = [];
            
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
            
            return $csvData;
        } catch (PDOException $e) {
            error_log("Error in exportToCSV: " . $e->getMessage());
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
            
            return true;
        } catch (PDOException $e) {
            error_log("Database debug error: " . $e->getMessage());
            return false;
        }
    }
}
?>
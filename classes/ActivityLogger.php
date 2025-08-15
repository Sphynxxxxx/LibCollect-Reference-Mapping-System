<?php
class ActivityLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an activity
     */
    public function log($action, $description, $bookId = null, $bookTitle = null, $category = null, $userId = null, $userName = null) {
        try {
            $sql = "INSERT INTO activity_logs (action, description, book_id, book_title, category, user_id, user_name, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $action,
                $description,
                $bookId,
                $bookTitle,
                $category,
                $userId,
                $userName,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log book-related activities
     */
    public function logBookActivity($action, $book, $additionalInfo = '') {
        $bookTitle = is_array($book) ? $book['title'] : $book->title ?? 'Unknown';
        $bookId = is_array($book) ? $book['id'] : $book->id ?? null;
        $category = is_array($book) ? $book['category'] : $book->category ?? null;
        
        $description = $this->generateBookDescription($action, $bookTitle, $additionalInfo);
        
        return $this->log(
            $action,
            $description,
            $bookId,
            $bookTitle,
            $category,
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? 'System'
        );
    }
    
    /**
     * Log user activities
     */
    public function logUserActivity($action, $description, $userId = null, $userName = null) {
        return $this->log(
            $action,
            $description,
            null,
            null,
            null,
            $userId ?? $_SESSION['user_id'] ?? null,
            $userName ?? $_SESSION['username'] ?? 'Unknown'
        );
    }
    
    /**
     * Log borrowing activities
     */
    public function logBorrowingActivity($action, $bookTitle, $borrowerName, $additionalInfo = '') {
        $description = $this->generateBorrowingDescription($action, $bookTitle, $borrowerName, $additionalInfo);
        
        return $this->log(
            $action,
            $description,
            null,
            $bookTitle,
            null,
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? 'System'
        );
    }
    
    /**
     * Get recent activities
     */
    public function getRecentActivities($limit = 10) {
        try {
            $sql = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch recent activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get activities by date range
     */
    public function getActivitiesByDateRange($startDate, $endDate, $limit = 100) {
        try {
            $sql = "SELECT * FROM activity_logs 
                    WHERE created_at BETWEEN ? AND ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch activities by date range: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get activities by action type
     */
    public function getActivitiesByAction($action, $limit = 50) {
        try {
            $sql = "SELECT * FROM activity_logs 
                    WHERE action = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$action, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch activities by action: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clear old logs (optional - for maintenance)
     */
    public function clearOldLogs($daysToKeep = 90) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            $sql = "DELETE FROM activity_logs WHERE created_at < ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cutoffDate]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Failed to clear old logs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate book-specific descriptions
     */
    private function generateBookDescription($action, $bookTitle, $additionalInfo) {
        switch (strtolower($action)) {
            case 'add':
            case 'create':
                return "Added new book: \"{$bookTitle}\"" . ($additionalInfo ? " - {$additionalInfo}" : "");
            case 'update':
            case 'edit':
                return "Updated book: \"{$bookTitle}\"" . ($additionalInfo ? " - {$additionalInfo}" : "");
            case 'delete':
            case 'remove':
                return "Deleted book: \"{$bookTitle}\"" . ($additionalInfo ? " - {$additionalInfo}" : "");
            default:
                return "{$action} performed on book: \"{$bookTitle}\"" . ($additionalInfo ? " - {$additionalInfo}" : "");
        }
    }
    
    /**
     * Generate borrowing-specific descriptions
     */
    private function generateBorrowingDescription($action, $bookTitle, $borrowerName, $additionalInfo) {
        switch (strtolower($action)) {
            case 'borrow':
                return "\"{$bookTitle}\" borrowed by {$borrowerName}" . ($additionalInfo ? " - {$additionalInfo}" : "");
            case 'return':
                return "\"{$bookTitle}\" returned by {$borrowerName}" . ($additionalInfo ? " - {$additionalInfo}" : "");
            case 'overdue':
                return "\"{$bookTitle}\" is overdue (borrowed by {$borrowerName})" . ($additionalInfo ? " - {$additionalInfo}" : "");
            default:
                return "{$action}: \"{$bookTitle}\" - {$borrowerName}" . ($additionalInfo ? " - {$additionalInfo}" : "");
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>
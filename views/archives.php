<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

function getCategoryDisplayInfo($categoryString, $departments) {
    if (empty($categoryString)) {
        return ['primary' => 'Unknown', 'color' => 'secondary', 'display' => 'Unknown', 'count' => 0];
    }
    
    $categories = array_map('trim', explode(',', $categoryString));
    $primaryCategory = $categories[0];
    $categoryCount = count($categories);
    
    // Get color from primary category or default to secondary
    $color = isset($departments[$primaryCategory]) ? $departments[$primaryCategory]['color'] : 'secondary';
    
    // Create display text
    if ($categoryCount > 1) {
        $display = $primaryCategory . ' +' . ($categoryCount - 1);
    } else {
        $display = $primaryCategory;
    }
    
    return [
        'primary' => $primaryCategory,
        'color' => $color,
        'display' => $display,
        'count' => $categoryCount,
        'all' => $categories
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'auto_archive':
                $result = $book->bulkArchiveEligibleBooks();
                if ($result['success']) {
                    $_SESSION['message'] = "Successfully archived {$result['archived']} books older than 10 years.";
                    $_SESSION['message_type'] = 'success';
                    if (!empty($result['errors'])) {
                        $_SESSION['message'] .= " ({$result['errors']} errors)";
                    }
                } else {
                    $_SESSION['message'] = 'Failed to auto-archive books!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'archive_pending':
                // Archive a pending book with selected reason
                if (isset($_POST['final_archive_reason']) && isset($_POST['pending_id'])) {
                    $pendingId = $_POST['pending_id'];
                    $archiveReason = $_POST['final_archive_reason'];
                    
                    // Get pending book data
                    $stmt = $pdo->prepare("SELECT * FROM pending_archives WHERE id = ?");
                    $stmt->execute([$pendingId]);
                    $pendingBook = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pendingBook) {
                        // Prepare data for archiving
                        $archiveData = [
                            'title' => $pendingBook['title'],
                            'author' => $pendingBook['author'],
                            'isbn' => $pendingBook['isbn'],
                            'category' => $pendingBook['category'],
                            'quantity' => $pendingBook['quantity'],
                            'description' => $pendingBook['description'],
                            'subject_name' => $pendingBook['subject_name'],
                            'semester' => $pendingBook['semester'],
                            'section' => $pendingBook['section'],
                            'year_level' => $pendingBook['year_level'],
                            'course_code' => $pendingBook['course_code'],
                            'publication_year' => $pendingBook['publication_year'],
                            'book_copy_number' => $pendingBook['book_copy_number'],
                            'total_quantity' => $pendingBook['total_quantity'],
                            'is_multi_context' => $pendingBook['is_multi_context'],
                            'same_book_series' => $pendingBook['same_book_series']
                        ];
                        
                        // Archive the book
                        $result = $book->archiveBook($archiveData, $archiveReason, $_SESSION['user_name'] ?? 'System');
                        
                        if ($result) {
                            // Remove from pending archives
                            $stmt = $pdo->prepare("DELETE FROM pending_archives WHERE id = ?");
                            $stmt->execute([$pendingId]);
                            
                            $_SESSION['message'] = 'Book successfully archived!';
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = 'Failed to archive book!';
                            $_SESSION['message_type'] = 'danger';
                        }
                    }
                }
                break;
                
            case 'restore':
                if ($book->restoreFromArchive($_POST['id'])) {
                    $_SESSION['message'] = 'Book restored successfully!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to restore book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'permanent_delete':
                // Get archived book details for logging before deletion
                $stmt = $pdo->prepare("SELECT * FROM archived_books WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $archivedBook = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($archivedBook) {
                    $stmt = $pdo->prepare("DELETE FROM archived_books WHERE id = ?");
                    if ($stmt->execute([$_POST['id']])) {
                        // Log the permanent deletion
                        $book->logCustomActivity(
                            'permanent_delete',
                            "Permanently deleted archived book: {$archivedBook['title']} by {$archivedBook['author']}",
                            $_POST['id'],
                            $archivedBook['title'],
                            $archivedBook['category']
                        );
                        $_SESSION['message'] = 'Book permanently deleted!';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to delete book!';
                        $_SESSION['message_type'] = 'danger';
                    }
                } else {
                    $_SESSION['message'] = 'Book not found!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'delete_pending':
                // Delete from pending archives
                $stmt = $pdo->prepare("DELETE FROM pending_archives WHERE id = ?");
                if ($stmt->execute([$_POST['id']])) {
                    $_SESSION['message'] = 'Pending book removed!';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to remove pending book!';
                    $_SESSION['message_type'] = 'danger';
                }
                break;
                
            case 'update_archive_reason':
                if (isset($_POST['final_archive_reason']) && isset($_POST['book_ids'])) {
                    $newReason = $_POST['final_archive_reason'];
                    $bookIds = explode(',', $_POST['book_ids']);
                    
                    $updateCount = 0;
                    foreach ($bookIds as $bookId) {
                        $bookId = trim($bookId);
                        if (!empty($bookId)) {
                            $stmt = $pdo->prepare("UPDATE archived_books SET archive_reason = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$newReason, $bookId])) {
                                $updateCount++;
                            }
                        }
                    }
                    
                    if ($updateCount > 0) {
                        $_SESSION['message'] = "Successfully updated archive reason for {$updateCount} book record(s)!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to update archive reason!';
                        $_SESSION['message_type'] = 'danger';
                    }
                }
                break;
        }
        header('Location: archives.php');
        exit;
    }
}

// Function to merge duplicate archived books
function mergeArchivedBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $bookItem) {
        $key = md5(strtolower($bookItem['title'] . '|' . $bookItem['author'] . '|' . $bookItem['isbn'] . '|' . ($bookItem['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            $mergedBooks[$key] = $bookItem;
            $mergedBooks[$key]['total_quantity'] = $bookItem['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$bookItem['id']];
            $mergedBooks[$key]['archive_dates'] = [date('Y-m-d', strtotime($bookItem['archived_at']))];
            $mergedBooks[$key]['archive_reasons'] = [$bookItem['archive_reason']];
        } else {
            $mergedBooks[$key]['total_quantity'] += $bookItem['quantity'];
            $mergedBooks[$key]['record_ids'][] = $bookItem['id'];
            $archiveDate = date('Y-m-d', strtotime($bookItem['archived_at']));
            if (!in_array($archiveDate, $mergedBooks[$key]['archive_dates'])) {
                $mergedBooks[$key]['archive_dates'][] = $archiveDate;
            }
            if (!in_array($bookItem['archive_reason'], $mergedBooks[$key]['archive_reasons'])) {
                $mergedBooks[$key]['archive_reasons'][] = $bookItem['archive_reason'];
            }
        }
        
        $context = [];
        if (!empty($bookItem['category'])) $context['category'] = $bookItem['category'];
        if (!empty($bookItem['year_level'])) $context['year_level'] = $bookItem['year_level'];
        if (!empty($bookItem['semester'])) $context['semester'] = $bookItem['semester'];
        if (!empty($bookItem['section'])) $context['section'] = $bookItem['section'];
        if (!empty($bookItem['subject_name'])) $context['subject_name'] = $bookItem['subject_name'];
        if (!empty($bookItem['course_code'])) $context['course_code'] = $bookItem['course_code'];
        
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

// Function to merge pending books
function mergePendingBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $bookItem) {
        $key = md5(strtolower($bookItem['title'] . '|' . $bookItem['author'] . '|' . $bookItem['isbn'] . '|' . ($bookItem['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            $mergedBooks[$key] = $bookItem;
            $mergedBooks[$key]['total_quantity'] = $bookItem['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$bookItem['id']];
            $mergedBooks[$key]['pending_dates'] = [date('Y-m-d', strtotime($bookItem['pending_since']))];
        } else {
            $mergedBooks[$key]['total_quantity'] += $bookItem['quantity'];
            $mergedBooks[$key]['record_ids'][] = $bookItem['id'];
            $pendingDate = date('Y-m-d', strtotime($bookItem['pending_since']));
            if (!in_array($pendingDate, $mergedBooks[$key]['pending_dates'])) {
                $mergedBooks[$key]['pending_dates'][] = $pendingDate;
            }
        }
        
        $context = [];
        if (!empty($bookItem['category'])) $context['category'] = $bookItem['category'];
        if (!empty($bookItem['year_level'])) $context['year_level'] = $bookItem['year_level'];
        if (!empty($bookItem['semester'])) $context['semester'] = $bookItem['semester'];
        if (!empty($bookItem['section'])) $context['section'] = $bookItem['section'];
        if (!empty($bookItem['subject_name'])) $context['subject_name'] = $bookItem['subject_name'];
        if (!empty($bookItem['course_code'])) $context['course_code'] = $bookItem['course_code'];
        
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

function getArchivedBooksGrouped($pdo, $category = '', $search = '', $groupBy = 'category') {
    try {
        $query = "SELECT * FROM archived_books WHERE 1=1";
        $params = [];
        
        if ($category) {
            $query .= " AND (category = ? OR FIND_IN_SET(?, category) > 0)";
            $params[] = $category;
            $params[] = $category;
        }
        
        if ($search) {
            $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR publication_year LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $query .= " ORDER BY title ASC, author ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mergedBooks = mergeArchivedBooks($books);
        
        $grouped = [];
        foreach ($mergedBooks as $bookItem) {
            switch ($groupBy) {
                case 'year':
                    $key = $bookItem['publication_year'] ?: 'Unknown Year';
                    break;
                case 'author':
                    $key = strtoupper(substr($bookItem['author'], 0, 1));
                    break;
                case 'archive_date':
                    $key = $bookItem['archive_dates'][0];
                    break;
                default:
                    $key = $bookItem['category'];
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $bookItem;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        error_log("Error getting archived books: " . $e->getMessage());
        return [];
    }
}

function getPendingArchivesGrouped($pdo, $groupBy = 'category') {
    try {
        $query = "SELECT * FROM pending_archives ORDER BY title ASC, author ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mergedBooks = mergePendingBooks($books);
        
        $grouped = [];
        foreach ($mergedBooks as $bookItem) {
            switch ($groupBy) {
                case 'year':
                    $key = $bookItem['publication_year'] ?: 'Unknown Year';
                    break;
                case 'author':
                    $key = strtoupper(substr($bookItem['author'], 0, 1));
                    break;
                default:
                    $key = $bookItem['category'];
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $bookItem;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        error_log("Error getting pending archives: " . $e->getMessage());
        return [];
    }
}

function getBooksEligibleForArchivingGrouped($pdo, $groupBy = 'category') {
    try {
        $currentYear = date('Y');
        $cutoffYear = $currentYear - 5;
        
        $sql = "SELECT * FROM books 
                WHERE publication_year IS NOT NULL AND publication_year <= ?
                ORDER BY title ASC, author ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffYear]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mergedBooks = mergeEligibleBooks($books);
        
        $grouped = [];
        foreach ($mergedBooks as $bookItem) {
            switch ($groupBy) {
                case 'year':
                    $key = $bookItem['publication_year'] ?: 'Unknown Year';
                    break;
                case 'author':
                    $key = strtoupper(substr($bookItem['author'], 0, 1));
                    break;
                default:
                    $key = $bookItem['category'];
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $bookItem;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        error_log("Error getting books eligible for archiving: " . $e->getMessage());
        return [];
    }
}

function mergeEligibleBooks($books) {
    $mergedBooks = [];
    
    foreach ($books as $bookItem) {
        $key = md5(strtolower($bookItem['title'] . '|' . $bookItem['author'] . '|' . $bookItem['isbn'] . '|' . ($bookItem['publication_year'] ?? '')));
        
        if (!isset($mergedBooks[$key])) {
            $mergedBooks[$key] = $bookItem;
            $mergedBooks[$key]['total_quantity'] = $bookItem['quantity'];
            $mergedBooks[$key]['academic_contexts'] = [];
            $mergedBooks[$key]['record_ids'] = [$bookItem['id']];
        } else {
            $mergedBooks[$key]['total_quantity'] += $bookItem['quantity'];
            $mergedBooks[$key]['record_ids'][] = $bookItem['id'];
        }
        
        $context = [];
        if (!empty($bookItem['category'])) $context['category'] = $bookItem['category'];
        if (!empty($bookItem['year_level'])) $context['year_level'] = $bookItem['year_level'];
        if (!empty($bookItem['semester'])) $context['semester'] = $bookItem['semester'];
        if (!empty($bookItem['section'])) $context['section'] = $bookItem['section'];
        if (!empty($bookItem['subject_name'])) $context['subject_name'] = $bookItem['subject_name'];
        if (!empty($bookItem['course_code'])) $context['course_code'] = $bookItem['course_code'];
        
        if (!empty($context)) {
            $mergedBooks[$key]['academic_contexts'][] = $context;
        }
    }
    
    return array_values($mergedBooks);
}

// Get parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : 'category';

// Get data for the page
$archivedBooks = getArchivedBooksGrouped($pdo, $category_filter, $search, $groupBy);
$pendingArchives = getPendingArchivesGrouped($pdo, $groupBy);
$eligibleBooks = getBooksEligibleForArchivingGrouped($pdo, $groupBy);
$archiveStats = $book->getArchiveStats();

// Get pending count
$pendingCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pending_archives");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $result['count'];
} catch (PDOException $e) {
    $pendingCount = 0;
}

// Department configuration
$departments = [
    'BIT' => ['name' => 'Bachelor in Industrial Technology', 'color' => 'primary'],
    'EDUCATION' => ['name' => 'Education Department', 'color' => 'success'],
    'HBM' => ['name' => 'Hotel & Business Management', 'color' => 'info'],
    'COMPSTUD' => ['name' => 'Computer Studies', 'color' => 'warning']
];

// Group display options
$groupOptions = [
    'category' => ['name' => 'Department', 'icon' => 'fas fa-layer-group'],
    'year' => ['name' => 'Publication Year', 'icon' => 'fas fa-calendar-alt'],
    'author' => ['name' => 'Author (A-Z)', 'icon' => 'fas fa-user'],
    'archive_date' => ['name' => 'Archive Date', 'icon' => 'fas fa-clock']
];

$page_title = "LibCollect: Reference Mapping System - Archives";
include '../includes/header.php';
?>

<style>
/* Enhanced Book Card Styles */
.book-card {
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
    height: 100%;
    border-radius: 12px;
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15) !important;
}

.book-cover {
    position: relative;
    background: #ffd700;
    height: 180px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: white;
    overflow: hidden;
}

.book-cover::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #ffd700;
}

.book-spine {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.9;
    z-index: 2;
    position: relative;
}

.book-info {
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    z-index: 3;
}

.book-id {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
    backdrop-filter: blur(10px);
}

.department-badge {
    font-size: 0.7rem;
    padding: 4px 8px;
    backdrop-filter: blur(10px);
}

.total-copies-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(40, 167, 69, 0.9);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    z-index: 3;
    backdrop-filter: blur(10px);
    font-weight: bold;
}

.publication-year-badge {
    position: absolute;
    top: 70px;
    right: 8px;
    background: rgba(13, 110, 253, 0.9);
    color: white;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    z-index: 3;
    backdrop-filter: blur(10px);
}

.merged-indicator {
    position: absolute;
    top: 40px;
    right: 8px;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    z-index: 3;
}

.archive-badge-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(108, 117, 125, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 4;
    backdrop-filter: blur(10px);
}

.pending-badge-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 193, 7, 0.9);
    color: #000;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 4;
    backdrop-filter: blur(10px);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.eligible-badge-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 193, 7, 0.9);
    color: #000;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 4;
    backdrop-filter: blur(10px);
}

.pending-cover {
    background: #ff9800 !important;
}

.eligible-cover {
    background: #f39c12 !important;
}

.book-details {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    height: calc(100% - 180px);
}

.book-title {
    font-weight: 600;
    line-height: 1.3;
    height: auto;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: 1rem;
    margin-bottom: 0.75rem;
}

.book-author, .book-isbn {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.book-year {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.academic-contexts {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    max-height: 120px;
    overflow-y: auto;
    flex-grow: 1;
}

.academic-contexts::-webkit-scrollbar {
    width: 4px;
}

.academic-contexts::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.academic-contexts::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 2px;
}

.context-group {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.context-group:last-child {
    margin-bottom: 0;
}

.context-group .badge {
    font-size: 0.65rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

.context-header {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
}

.archive-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
}

.pending-info {
    background: #fff3cd;
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
    border: 1px solid #ffc107;
}

.book-actions {
    margin-top: auto;
}

.book-actions .btn-group {
    width: 100%;
}

.book-actions .dropdown-menu {
    width: 100%;
}

/* Different gradients for different departments */
.book-card[data-department="BIT"] .book-cover {
    background: #ffc107;
}

.book-card[data-department="EDUCATION"] .book-cover {
    background: #0d6efd;
}

.book-card[data-department="HBM"] .book-cover {
    background: #dc3545;
}

.book-card[data-department="COMPSTUD"] .book-cover {
    background: #212529;
}

/* Archive-specific overrides */
.archive-card .book-cover {
    filter: grayscale(20%);
}

.archive-card.clickable-card {
    cursor: pointer;
}

.archive-card.clickable-card .book-details {
    cursor: pointer;
}

.pending-card {
    cursor: pointer;
}

.pending-card .book-cover {
    filter: saturate(1.3);
}

.eligible-card .book-cover {
    filter: saturate(1.2);
}

.stats-card {
    background: #ffd700;
    color: white;
    border: none;
}

.tab-content {
    min-height: 400px;
}

.book-age {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
}

.age-ancient { background-color: #dc3545; color: white; }
.age-very-old { background-color: #fd7e14; color: white; }
.age-old { background-color: #ffc107; color: black; }

.group-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 4px solid #007bff;
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.group-collapse {
    cursor: pointer;
    transition: all 0.3s ease;
}

.group-collapse:hover {
    background-color: rgba(0,123,255,0.1);
}

.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

.compact-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    background: white;
}

.compact-card:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0,123,255,0.15);
}

.view-toggle {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.25rem;
}
</style>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-archive text-secondary me-2"></i>
                Archives Management
            </h1>
            <p class="mb-0">Manage archived books and automatic archiving of books older than 5 years</p>
        </div>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="auto_archive">
            <button type="submit" class="btn btn-warning" onclick="return confirm('This will automatically archive all books published 5+ years ago. Continue?')">
                <i class="fas fa-history me-1"></i>Auto Archive Old Books
            </button>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-warning bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <i class="fas fa-hourglass-half fa-2x mb-2 text-warning"></i>
                <h3><?php echo $pendingCount; ?></h3>
                <p class="mb-0">Pending Archives</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-archive fa-2x mb-2" style="color: black;"></i>
                <h3 style="color: black;"><?php echo $archiveStats['total_archived']; ?></h3>
                <p class="mb-0" style="color: black;">Archived Books</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-2 text-info"></i>
                <h3><?php echo date('Y') - 5; ?></h3>
                <p class="mb-0">Cutoff Year</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fas fa-undo fa-2x mb-2 text-success"></i>
                <h3>∞</h3>
                <p class="mb-0">Restorable</p>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'pending' ? 'active' : ''; ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
            <i class="fas fa-hourglass-half me-1" style="color: black;"></i>
            <span style="color: black;">Pending Archives (<?php echo $pendingCount; ?>)</span>
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'archived' ? 'active' : ''; ?>" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived" type="button" role="tab">
            <i class="fas fa-archive me-1" style="color: black;"></i>
            <span style="color: black;">
            Archived Books (<?php echo array_sum(array_map('count', $archivedBooks)); ?>)
            </span>
        </button>
    </li>
    <!--<li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'eligible' ? 'active' : ''; ?>" id="eligible-tab" data-bs-toggle="tab" data-bs-target="#eligible" type="button" role="tab">
        <i class="fas fa-clock me-1" style="color: black;"></i>
        <span style="color: black;">
        Eligible for Archive (<?php echo array_sum(array_map('count', $eligibleBooks)); ?>)
        </span>
        </button>
    </li>-->
    <!--<li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'stats' ? 'active' : ''; ?>" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
        <i class="fas fa-chart-bar me-1" style="color: black;"></i>
        <span style="color: black;">Statistics</span>
        </button>
    </li>-->
</ul>

<div class="tab-content" id="archiveTabContent">
    <!-- Pending Archives Tab -->
    <div class="tab-pane fade <?php echo $tab === 'pending' ? 'show active' : ''; ?>" id="pending" role="tabpanel">
        <?php if (empty($pendingArchives)): ?>
            <div class="text-center py-5">
                <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                <h4 class="text-muted">No pending archives</h4>
                <p class="text-muted">Books older than 5 years will appear here before being archived.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $pendingCount; ?> books</strong> are waiting to be archived. Click on any book to select an archive reason.
            </div>
            
            <?php foreach ($pendingArchives as $groupKey => $books): ?>
                <div class="mb-4">
                    <div class="group-header card border-warning">
                        <div class="card-body py-3 group-collapse" data-bs-toggle="collapse" data-bs-target="#pending-group-<?php echo md5($groupKey); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="<?php echo $groupOptions[$groupBy]['icon']; ?> me-2"></i>
                                    <?php 
                                    if ($groupBy === 'category' && isset($departments[$groupKey])) {
                                        echo $departments[$groupKey]['name'];
                                    } else {
                                        echo htmlspecialchars($groupKey);
                                    }
                                    ?>
                                    <span class="badge bg-warning text-dark ms-2"><?php echo count($books); ?> books</span>
                                </h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="pending-group-<?php echo md5($groupKey); ?>">
                        <div class="book-grid">
                            <?php foreach ($books as $book): ?>
                                <div class="book-card pending-card" 
                                     data-department="<?php echo htmlspecialchars(getCategoryDisplayInfo($book['category'], $departments)['primary']); ?>"
                                     data-pending-id="<?php echo $book['id']; ?>"
                                     data-book-ids="<?php echo implode(',', $book['record_ids']); ?>"
                                     data-book-title="<?php echo htmlspecialchars($book['title']); ?>"
                                     data-book-author="<?php echo htmlspecialchars($book['author']); ?>"
                                     data-book-isbn="<?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?>"
                                     data-book-year="<?php echo htmlspecialchars($book['publication_year'] ?? 'N/A'); ?>"
                                     data-book-category="<?php echo htmlspecialchars($book['category']); ?>"
                                     data-book-quantity="<?php echo $book['total_quantity']; ?>">
                                    <!-- Book Cover -->
                                    <div class="book-cover pending-cover">
                                        <div class="book-spine">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        
                                        <div class="book-info">
                                            <div class="book-id">#<?php echo $book['id']; ?></div>
                                            <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                            <span class="badge bg-<?php echo $categoryInfo['color']; ?> department-badge">
                                                <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Merged book indicator -->
                                        <?php if (count($book['record_ids']) > 1): ?>
                                            <div class="merged-indicator">
                                                <i class="fas fa-layer-group me-1"></i><?php echo count($book['record_ids']); ?> Records
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Publication year badge -->
                                        <?php if (!empty($book['publication_year'])): ?>
                                            <div class="publication-year-badge">
                                                <i class="fas fa-calendar me-1"></i><?php echo $book['publication_year']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Total copies badge -->
                                        <div class="total-copies-badge">
                                            <i class="fas fa-copy me-1"></i><?php echo $book['total_quantity']; ?>
                                        </div>
                                        
                                        <!-- Pending badge -->
                                        <div class="pending-badge-overlay">
                                            PENDING
                                        </div>
                                    </div>
                                    
                                    <!-- Book Details -->
                                    <div class="book-details">
                                        <h6 class="book-title" title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </h6>
                                        
                                        <p class="book-author">
                                            <i class="fas fa-user me-1 text-muted"></i>
                                            <span class="text-dark"><?php echo htmlspecialchars($book['author']); ?></span>
                                        </p>
                                        
                                        <?php if (!empty($book['isbn'])): ?>
                                            <p class="book-isbn">
                                                <i class="fas fa-barcode me-1 text-muted"></i>
                                                <span class="text-muted"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($book['publication_year'])): ?>
                                            <?php 
                                            $age = date('Y') - $book['publication_year'];
                                            $ageClass = $age >= 50 ? 'age-ancient' : ($age >= 30 ? 'age-very-old' : 'age-old');
                                            ?>
                                            <p class="book-year">
                                                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                                <span class="text-muted">Published: <?php echo $book['publication_year']; ?></span>
                                                <span class="book-age <?php echo $ageClass; ?> ms-2"><?php echo $age; ?>y</span>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Pending Information -->
                                        <div class="pending-info">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                                <span class="badge bg-<?php echo $categoryInfo['color']; ?> me-1">
                                                    <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                                </span>
                                                <span class="badge bg-secondary">Qty: <?php echo $book['quantity']; ?></span>
                                            </div>
                                            <small class="text-warning">
                                                <i class="fas fa-clock me-1"></i>Pending since: <?php echo date('M j, Y', strtotime($book['pending_since'])); ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="book-actions" onclick="event.stopPropagation();">
                                            <div class="d-grid gap-1">
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        onclick="event.stopPropagation(); openArchiveReasonModal(<?php echo $book['id']; ?>)">
                                                    <i class="fas fa-archive me-1"></i>Select Reason & Archive
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="event.stopPropagation(); deletePendingBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-times me-1"></i>Remove from Pending
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Archived Books Tab -->
    <div class="tab-pane fade <?php echo $tab === 'archived' ? 'show active' : ''; ?>" id="archived" role="tabpanel">
        <!-- Search, Filter and Group Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <input type="hidden" name="tab" value="archived">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Search archived books</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by title, author, ISBN, or year..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="category">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept => $info): ?>
                                <option value="<?php echo $dept; ?>" <?php echo ($category_filter == $dept) ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Group by</label>
                        <select class="form-control" name="group_by">
                            <?php foreach ($groupOptions as $key => $option): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($groupBy == $key) ? 'selected' : ''; ?>>
                                    <?php echo $option['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Apply
                        </button>
                    </div>
                    <div class="col-md-1 mb-2">
                        <a href="archives.php?tab=archived" class="btn btn-outline-secondary w-100" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="button" class="btn btn-outline-info w-100" onclick="toggleAllGroups()" title="Expand/Collapse All">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grouped Archived Books Display -->
        <?php if (empty($archivedBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No archived books found</h4>
                <p class="text-muted">Books older than 5 years will appear here when archived.</p>
            </div>
        <?php else: ?>
            <?php foreach ($archivedBooks as $groupKey => $books): ?>
                <div class="mb-4">
                    <div class="group-header card">
                        <div class="card-body py-3 group-collapse" data-bs-toggle="collapse" data-bs-target="#archived-group-<?php echo md5($groupKey); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="<?php echo $groupOptions[$groupBy]['icon']; ?> me-2"></i>
                                    <?php 
                                    if ($groupBy === 'archive_date') {
                                        echo date('F j, Y', strtotime($groupKey));
                                    } elseif ($groupBy === 'category' && isset($departments[$groupKey])) {
                                        echo $departments[$groupKey]['name'];
                                    } else {
                                        echo htmlspecialchars($groupKey);
                                    }
                                    ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($books); ?> books</span>
                                </h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="archived-group-<?php echo md5($groupKey); ?>">
                        <div class="book-grid">
                            <?php foreach ($books as $book): ?>
                                <div class="book-card archive-card clickable-card" 
                                     data-department="<?php echo htmlspecialchars(getCategoryDisplayInfo($book['category'], $departments)['primary']); ?>"
                                     data-book-ids="<?php echo implode(',', $book['record_ids']); ?>"
                                     data-book-title="<?php echo htmlspecialchars($book['title']); ?>"
                                     data-book-author="<?php echo htmlspecialchars($book['author']); ?>"
                                     data-book-isbn="<?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?>"
                                     data-book-year="<?php echo htmlspecialchars($book['publication_year'] ?? 'N/A'); ?>"
                                     data-book-category="<?php echo htmlspecialchars($book['category']); ?>"
                                     data-book-quantity="<?php echo $book['total_quantity']; ?>"
                                     data-archive-reason="<?php echo htmlspecialchars(implode(', ', array_unique($book['archive_reasons']))); ?>">
                                    <!-- Enhanced Book Cover -->
                                    <div class="book-cover">
                                        <div class="book-spine">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        
                                        <div class="book-info">
                                            <div class="book-id">#<?php echo $book['id']; ?></div>
                                            <?php if (!$category_filter): ?>
                                                <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                                <span class="badge bg-<?php echo $categoryInfo['color']; ?> department-badge" 
                                                    title="<?php echo htmlspecialchars($book['category']); ?>">
                                                    <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Merged book indicator -->
                                        <?php if (count($book['record_ids']) > 1): ?>
                                            <div class="merged-indicator">
                                                <i class="fas fa-layer-group me-1"></i><?php echo count($book['record_ids']); ?> Records
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Publication year badge -->
                                        <?php if (!empty($book['publication_year'])): ?>
                                            <div class="publication-year-badge">
                                                <i class="fas fa-calendar me-1"></i><?php echo $book['publication_year']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Total copies badge -->
                                        <div class="total-copies-badge">
                                            <i class="fas fa-copy me-1"></i><?php echo $book['total_quantity']; ?>
                                        </div>
                                        
                                        <!-- Archive badge -->
                                        <div class="archive-badge-overlay">
                                            ARCHIVED
                                        </div>
                                    </div>
                                    
                                    <!-- Book Details (rest of the archived book display) -->
                                    <div class="book-details">
                                        <h6 class="book-title" title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </h6>
                                        
                                        <p class="book-author">
                                            <i class="fas fa-user me-1 text-muted"></i>
                                            <span class="text-dark"><?php echo htmlspecialchars($book['author']); ?></span>
                                        </p>
                                        
                                        <!-- Archive Information -->
                                        <div class="archive-info">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <?php $categoryInfo = getCategoryDisplayInfo($book['category'], $departments); ?>
                                                <span class="badge bg-<?php echo $categoryInfo['color']; ?> me-1">
                                                    <?php echo htmlspecialchars($categoryInfo['display']); ?>
                                                </span>
                                                <span class="badge bg-secondary">Qty: <?php echo $book['quantity']; ?></span>
                                            </div>
                                            <small class="text-muted">
                                                Reason: <?php echo implode(', ', array_unique($book['archive_reasons'])); ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="book-actions" onclick="event.stopPropagation();">
                                            <?php if (count($book['record_ids']) > 1): ?>
                                                <div class="btn-group w-100" role="group">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="event.stopPropagation(); confirmRestoreAll([<?php echo implode(',', $book['record_ids']); ?>], '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-undo me-1"></i>Restore All
                                                    </button>
                                                    <button type="button" class="btn btn-success btn-sm dropdown-toggle dropdown-toggle-split" 
                                                            data-bs-toggle="dropdown" aria-expanded="false"
                                                            onclick="event.stopPropagation();">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu" onclick="event.stopPropagation();">
                                                        <li><h6 class="dropdown-header">Manage Records</h6></li>
                                                        <?php foreach ($book['record_ids'] as $index => $recordId): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="event.preventDefault(); event.stopPropagation(); restoreBook(<?php echo $recordId; ?>)">
                                                                    <i class="fas fa-undo me-2"></i>Restore Record #<?php echo $recordId; ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" 
                                                               onclick="event.preventDefault(); event.stopPropagation(); confirmDeleteAll([<?php echo implode(',', $book['record_ids']); ?>], '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                                                <i class="fas fa-trash me-2"></i>Delete All Permanently
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-grid gap-1">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="event.stopPropagation(); restoreBook(<?php echo $book['id']; ?>)">
                                                        <i class="fas fa-undo me-1"></i>Restore
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="event.stopPropagation(); confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>', permanentDeleteBook)">
                                                        <i class="fas fa-trash me-1"></i>Delete Permanently
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Eligible for Archive Tab (keep existing code) -->
    <!--<div class="tab-pane fade <?php echo $tab === 'eligible' ? 'show active' : ''; ?>" id="eligible" role="tabpanel">
        <div class="text-center py-5">
            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Eligible books are automatically moved to Pending Archives</h4>
            <p class="text-muted">Check the "Pending Archives" tab to review and archive books.</p>
        </div>
    </div>-->

    <!-- Statistics Tab (keep existing code) -->
    <!--<div class="tab-pane fade <?php echo $tab === 'stats' ? 'show active' : ''; ?>" id="stats" role="tabpanel">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Archives by Department</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archiveStats['by_category'])): ?>
                            <p class="text-muted">No archived books yet.</p>
                        <?php else: ?>
                            <?php foreach ($archiveStats['by_category'] as $stat): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-<?php echo $departments[$stat['category']]['color']; ?> me-2">
                                        <?php echo $stat['category']; ?>
                                    </span>
                                    <span><?php echo $stat['count']; ?> books</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Archives by Year</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($archiveStats['by_year'])): ?>
                            <p class="text-muted">No archiving activity yet.</p>
                        <?php else: ?>
                            <?php foreach ($archiveStats['by_year'] as $stat): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo $stat['year']; ?></span>
                                    <span class="badge bg-secondary"><?php echo $stat['count']; ?> books</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>-->
</div>

<!-- Archive Reason Modal for Pending Books -->
<div class="modal fade" id="archiveReasonModal" tabindex="-1" aria-labelledby="archiveReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="archiveReasonModalLabel">
                    <i class="fas fa-archive me-2"></i>Select Archive Reason
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="pendingBookDetailsSection">
                    <!-- Pending book details will be populated here -->
                </div>
                
                <hr>
                
                <h6 class="text-primary mb-3">
                    <i class="fas fa-clipboard-list me-2"></i>Why is this book being archived?
                </h6>
                
                <form id="archivePendingForm" method="POST">
                    <input type="hidden" name="action" value="archive_pending">
                    <input type="hidden" name="pending_id" id="modalPendingId">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Archive Reason *</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Donated" id="pending_reason_donated">
                                        <label class="form-check-label" for="pending_reason_donated">
                                            <i class="fas fa-hands-helping text-success me-1"></i>Donated
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Outdated" id="pending_reason_outdated">
                                        <label class="form-check-label" for="pending_reason_outdated">
                                            <i class="fas fa-calendar-times text-warning me-1"></i>Outdated
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Obsolete" id="pending_reason_obsolete">
                                        <label class="form-check-label" for="pending_reason_obsolete">
                                            <i class="fas fa-ban text-secondary me-1"></i>Obsolete
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Damaged" id="pending_reason_damaged">
                                        <label class="form-check-label" for="pending_reason_damaged">
                                            <i class="fas fa-exclamation-triangle text-danger me-1"></i>Damaged
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Lost" id="pending_reason_lost">
                                        <label class="form-check-label" for="pending_reason_lost">
                                            <i class="fas fa-search text-muted me-1"></i>Lost
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Low usage" id="pending_reason_low_usage">
                                        <label class="form-check-label" for="pending_reason_low_usage">
                                            <i class="fas fa-chart-line text-info me-1"></i>Low usage
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Availability of more recent edition" id="pending_reason_recent_edition">
                                        <label class="form-check-label" for="pending_reason_recent_edition">
                                            <i class="fas fa-sync-alt text-primary me-1"></i>Availability of more recent edition
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="Title ceased publication" id="pending_reason_ceased">
                                        <label class="form-check-label" for="pending_reason_ceased">
                                            <i class="fas fa-stop-circle text-dark me-1"></i>Title ceased publication
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Others option -->
                            <div class="form-check mb-2">
                                <input class="form-check-input pending-archive-reason" type="radio" name="archive_reason" value="custom" id="pending_reason_others">
                                <label class="form-check-label" for="pending_reason_others">
                                    <i class="fas fa-edit text-secondary me-1"></i>Others:
                                </label>
                            </div>
                            
                            <!-- Custom reason text area -->
                            <div class="mt-3" id="pendingCustomReasonSection" style="display: none;">
                                <label for="pendingCustomArchiveReason" class="form-label small text-muted">
                                    <i class="fas fa-pencil-alt me-1"></i>Please specify the reason:
                                </label>
                                <textarea class="form-control form-control-sm" 
                                        id="pendingCustomArchiveReason" 
                                        name="custom_archive_reason" 
                                        rows="2" 
                                        placeholder="Enter your custom archive reason here..."
                                        maxlength="200"></textarea>
                                <small class="text-muted">Maximum 200 characters</small>
                                <div class="small text-muted mt-1" id="pendingCustomReasonCounter">0/200 characters</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>This book will be moved to the archived collection with the selected reason.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-warning" onclick="submitPendingArchive()">
                    <i class="fas fa-archive me-1"></i>Archive Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Archive Reason Modal (for already archived books) -->
<div class="modal fade" id="updateArchiveReasonModal" tabindex="-1" aria-labelledby="updateArchiveReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="updateArchiveReasonModalLabel">
                    <i class="fas fa-edit me-2"></i>Update Archive Reason
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="archivedBookDetailsSection">
                    <!-- Archived book details will be populated here -->
                </div>
                
                <hr>
                
                <h6 class="text-primary mb-3">
                    <i class="fas fa-edit me-2"></i>Update Archive Reason
                </h6>
                
                <form id="updateArchiveReasonForm" method="POST">
                    <input type="hidden" name="action" value="update_archive_reason">
                    <input type="hidden" name="book_ids" id="modalBookIds">
                    
                    <div class="mb-3">
                        <label class="form-label">Select New Archive Reason *</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Donated" id="update_reason_donated">
                                        <label class="form-check-label" for="update_reason_donated">
                                            <i class="fas fa-hands-helping text-success me-1"></i>Donated
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Outdated" id="update_reason_outdated">
                                        <label class="form-check-label" for="update_reason_outdated">
                                            <i class="fas fa-calendar-times text-warning me-1"></i>Outdated
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Obsolete" id="update_reason_obsolete">
                                        <label class="form-check-label" for="update_reason_obsolete">
                                            <i class="fas fa-ban text-secondary me-1"></i>Obsolete
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Damaged" id="update_reason_damaged">
                                        <label class="form-check-label" for="update_reason_damaged">
                                            <i class="fas fa-exclamation-triangle text-danger me-1"></i>Damaged
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Lost" id="update_reason_lost">
                                        <label class="form-check-label" for="update_reason_lost">
                                            <i class="fas fa-search text-muted me-1"></i>Lost
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Low usage" id="update_reason_low_usage">
                                        <label class="form-check-label" for="update_reason_low_usage">
                                            <i class="fas fa-chart-line text-info me-1"></i>Low usage
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Availability of more recent edition" id="update_reason_recent_edition">
                                        <label class="form-check-label" for="update_reason_recent_edition">
                                            <i class="fas fa-sync-alt text-primary me-1"></i>Availability of more recent edition
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="Title ceased publication" id="update_reason_ceased">
                                        <label class="form-check-label" for="update_reason_ceased">
                                            <i class="fas fa-stop-circle text-dark me-1"></i>Title ceased publication
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Others option -->
                            <div class="form-check mb-2">
                                <input class="form-check-input update-archive-reason" type="radio" name="new_archive_reason" value="custom" id="update_reason_others">
                                <label class="form-check-label" for="update_reason_others">
                                    <i class="fas fa-edit text-secondary me-1"></i>Others:
                                </label>
                            </div>
                            
                            <!-- Custom reason text area -->
                            <div class="mt-3" id="updateCustomReasonSection" style="display: none;">
                                <label for="updateCustomArchiveReason" class="form-label small text-muted">
                                    <i class="fas fa-pencil-alt me-1"></i>Please specify the reason:
                                </label>
                                <textarea class="form-control form-control-sm" 
                                        id="updateCustomArchiveReason" 
                                        name="custom_new_archive_reason" 
                                        rows="2" 
                                        placeholder="Enter your custom archive reason here..."
                                        maxlength="200"></textarea>
                                <small class="text-muted">Maximum 200 characters</small>
                                <div class="small text-muted mt-1" id="updateCustomReasonCounter">0/200 characters</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Updating the archive reason will apply to all copies of this book.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="submitArchiveReasonUpdate()">
                    <i class="fas fa-save me-1"></i>Update Reason
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Tab navigation with URL parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'pending';
    
    // Activate the correct tab
    const tabElement = document.getElementById(activeTab + '-tab');
    if (tabElement) {
        const tab = new bootstrap.Tab(tabElement);
        tab.show();
    }
    
    // Update URL when tab changes
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', newUrl);
        });
    });
    
    // Auto-refresh notification for pending books
    const pendingCount = <?php echo $pendingCount; ?>;
    if (pendingCount > 0) {
        showPendingNotification(pendingCount);
    }
    
    // Initialize collapse icons
    updateCollapseIcons();
});

// Show notification for pending books
function showPendingNotification(count) {
    if (count > 0) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 350px;';
        notification.innerHTML = `
            <i class="fas fa-hourglass-half me-2"></i>
            <strong>${count} books</strong> are waiting to be archived! Click on them to select an archive reason.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }
}

// Group management functions
function toggleAllGroups() {
    const collapseElements = document.querySelectorAll('[id^="archived-group-"], [id^="pending-group-"], [id^="eligible-group-"]');
    const allExpanded = Array.from(collapseElements).every(el => el.classList.contains('show'));
    
    collapseElements.forEach(el => {
        if (allExpanded) {
            bootstrap.Collapse.getOrCreateInstance(el).hide();
        } else {
            bootstrap.Collapse.getOrCreateInstance(el).show();
        }
    });
    
    setTimeout(updateCollapseIcons, 300);
}

// Update collapse icons
function updateCollapseIcons() {
    document.querySelectorAll('.group-collapse').forEach(header => {
        const target = header.getAttribute('data-bs-target');
        const collapseElement = document.querySelector(target);
        const icon = header.querySelector('.fas');
        
        if (collapseElement && icon) {
            if (collapseElement.classList.contains('show')) {
                icon.className = 'fas fa-chevron-up';
            } else {
                icon.className = 'fas fa-chevron-down';
            }
        }
    });
}

// Listen for collapse events
document.addEventListener('shown.bs.collapse', updateCollapseIcons);
document.addEventListener('hidden.bs.collapse', updateCollapseIcons);

// Open archive reason modal for pending book
function openArchiveReasonModal(pendingId) {
    // Get pending book card
    const pendingCard = document.querySelector(`[data-pending-id="${pendingId}"]`);
    
    if (pendingCard) {
        const bookTitle = pendingCard.dataset.bookTitle || 'Unknown';
        const bookAuthor = pendingCard.dataset.bookAuthor || 'Unknown';
        const bookISBN = pendingCard.dataset.bookIsbn || 'N/A';
        const bookYear = pendingCard.dataset.bookYear || 'N/A';
        const bookCategory = pendingCard.dataset.bookCategory || 'N/A';
        const bookQuantity = pendingCard.dataset.bookQuantity || '1';
        
        // Populate modal with book details
        const bookDetailsSection = document.getElementById('pendingBookDetailsSection');
        bookDetailsSection.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-primary mb-3">${bookTitle}</h5>
                    <p><strong>Author:</strong> ${bookAuthor}</p>
                    <p><strong>Call No.:</strong> ${bookISBN}</p>
                    <p><strong>Publication:</strong> ${bookYear}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Department:</strong> <span class="badge bg-primary">${bookCategory}</span></p>
                    <p><strong>Total Copies:</strong> <span class="badge bg-success">${bookQuantity}</span></p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-clock me-2"></i><strong>Pending Archive</strong><br>
                        <small>This book is 5+ years old and needs to be archived.</small>
                    </div>
                </div>
            </div>
        `;
        
        // Store pending ID in hidden field
        document.getElementById('modalPendingId').value = pendingId;
        
        // Reset form
        document.querySelectorAll('.pending-archive-reason').forEach(radio => radio.checked = false);
        document.getElementById('pendingCustomReasonSection').style.display = 'none';
        document.getElementById('pendingCustomArchiveReason').value = '';
        
        // Reset all label styles
        document.querySelectorAll('.pending-archive-reason + label').forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('archiveReasonModal'));
        modal.show();
    }
}

// Handle pending archive reason selection
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('pending-archive-reason')) {
        const customSection = document.getElementById('pendingCustomReasonSection');
        const customTextArea = document.getElementById('pendingCustomArchiveReason');
        
        if (e.target.value === 'custom') {
            customSection.style.display = 'block';
            customTextArea.setAttribute('required', 'required');
            customSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            customSection.style.display = 'none';
            customTextArea.removeAttribute('required');
            customTextArea.value = '';
        }
        
        // Update visual feedback
        const selectedLabel = e.target.nextElementSibling;
        const allLabels = document.querySelectorAll('.pending-archive-reason + label');
        
        allLabels.forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
        
        if (selectedLabel) {
            selectedLabel.classList.add('text-primary', 'fw-bold');
        }
    }
});

// Handle update archive reason selection
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('update-archive-reason')) {
        const customSection = document.getElementById('updateCustomReasonSection');
        const customTextArea = document.getElementById('updateCustomArchiveReason');
        
        if (e.target.value === 'custom') {
            customSection.style.display = 'block';
            customTextArea.setAttribute('required', 'required');
            customSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            customSection.style.display = 'none';
            customTextArea.removeAttribute('required');
            customTextArea.value = '';
        }
        
        // Update visual feedback
        const selectedLabel = e.target.nextElementSibling;
        const allLabels = document.querySelectorAll('.update-archive-reason + label');
        
        allLabels.forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
        
        if (selectedLabel) {
            selectedLabel.classList.add('text-primary', 'fw-bold');
        }
    }
});

// Character counter for pending custom archive reason
document.getElementById('pendingCustomArchiveReason')?.addEventListener('input', function() {
    const maxLength = 200;
    const currentLength = this.value.length;
    const counter = document.getElementById('pendingCustomReasonCounter');
    
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

// Character counter for update custom archive reason
document.getElementById('updateCustomArchiveReason')?.addEventListener('input', function() {
    const maxLength = 200;
    const currentLength = this.value.length;
    const counter = document.getElementById('updateCustomReasonCounter');
    
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

// Submit pending archive with reason
function submitPendingArchive() {
    const selectedReason = document.querySelector('input[name="archive_reason"]:checked');
    
    if (!selectedReason) {
        alert('Please select an archive reason.');
        return;
    }
    
    let reasonValue = selectedReason.value;
    
    // If custom reason selected, get the custom text
    if (reasonValue === 'custom') {
        const customReason = document.getElementById('pendingCustomArchiveReason').value.trim();
        if (!customReason) {
            alert('Please provide a custom archive reason.');
            document.getElementById('pendingCustomArchiveReason').focus();
            return;
        }
        reasonValue = customReason;
    }
    
    // Update the form value and submit
    const form = document.getElementById('archivePendingForm');
    
    // Create a new hidden input with the final reason value
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'final_archive_reason';
    reasonInput.value = reasonValue;
    form.appendChild(reasonInput);
    
    // Submit the form
    form.submit();
}

// Open update archive reason modal for archived book
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.archive-card.clickable-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('button') || 
                e.target.closest('.dropdown-menu') || 
                e.target.closest('.btn') ||
                e.target.closest('.book-actions')) {
                return;
            }
            
            const bookTitle = this.dataset.bookTitle || 'Unknown';
            const bookAuthor = this.dataset.bookAuthor || 'Unknown';
            const bookISBN = this.dataset.bookIsbn || 'N/A';
            const bookYear = this.dataset.bookYear || 'N/A';
            const bookCategory = this.dataset.bookCategory || 'N/A';
            const bookQuantity = this.dataset.bookQuantity || '1';
            const archiveReason = this.dataset.archiveReason || 'Not specified';
            const bookIds = this.dataset.bookIds || '';
            
            const bookDetailsSection = document.getElementById('archivedBookDetailsSection');
            bookDetailsSection.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">${bookTitle}</h5>
                        <p><strong>Author:</strong> ${bookAuthor}</p>
                        <p><strong>ISBN/Call No.:</strong> ${bookISBN}</p>
                        <p><strong>Publication:</strong> ${bookYear}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Department:</strong> <span class="badge bg-primary">${bookCategory}</span></p>
                        <p><strong>Total Copies:</strong> <span class="badge bg-success">${bookQuantity}</span></p>
                        <p><strong>Current Archive Reason:</strong></p>
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-archive me-2"></i>${archiveReason}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('modalBookIds').value = bookIds;
            
            document.querySelectorAll('.update-archive-reason').forEach(radio => radio.checked = false);
            document.getElementById('updateCustomReasonSection').style.display = 'none';
            document.getElementById('updateCustomArchiveReason').value = '';
            
            document.querySelectorAll('.update-archive-reason + label').forEach(label => {
                label.classList.remove('text-primary', 'fw-bold');
            });
            
            const modal = new bootstrap.Modal(document.getElementById('updateArchiveReasonModal'));
            modal.show();
        });
    });
});

// Submit archive reason update
function submitArchiveReasonUpdate() {
    const selectedReason = document.querySelector('input[name="new_archive_reason"]:checked');
    
    if (!selectedReason) {
        alert('Please select an archive reason.');
        return;
    }
    
    let reasonValue = selectedReason.value;
    
    if (reasonValue === 'custom') {
        const customReason = document.getElementById('updateCustomArchiveReason').value.trim();
        if (!customReason) {
            alert('Please provide a custom archive reason.');
            document.getElementById('updateCustomArchiveReason').focus();
            return;
        }
        reasonValue = customReason;
    }
    
    const form = document.getElementById('updateArchiveReasonForm');
    
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'final_archive_reason';
    reasonInput.value = reasonValue;
    form.appendChild(reasonInput);
    
    form.submit();
}

// Delete pending book
function deletePendingBook(id, title) {
    if (confirm(`Are you sure you want to remove "${title}" from pending archives?\n\nYou can always add it again later.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_pending">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Existing functions for archived books
function confirmDelete(id, title, callback) {
    if (confirm(`Are you sure you want to permanently delete "${title}"?\n\nThis action cannot be undone.`)) {
        callback(id);
    }
}

function confirmDeleteAll(ids, title) {
    if (confirm(`Are you sure you want to permanently delete all ${ids.length} records of "${title}"?\n\nThis action cannot be undone.`)) {
        deleteMultipleBooks(ids);
    }
}

function confirmRestoreAll(ids, title) {
    if (confirm(`Are you sure you want to restore all ${ids.length} records of "${title}" to the active collection?`)) {
        restoreMultipleBooks(ids);
    }
}

function restoreBook(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function permanentDeleteBook(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="permanent_delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function restoreMultipleBooks(ids) {
    ids.forEach((id, index) => {
        setTimeout(() => {
            restoreBook(id);
        }, index * 100);
    });
}

function deleteMultipleBooks(ids) {
    ids.forEach((id, index) => {
        setTimeout(() => {
            permanentDeleteBook(id);
        }, index * 100);
    });
}

// Click handler for pending cards to open modal
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pending-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons
            if (e.target.closest('button') || e.target.closest('.book-actions')) {
                return;
            }
            
            const pendingId = this.dataset.pendingId;
            if (pendingId) {
                openArchiveReasonModal(pendingId);
            }
        });
    });
});

// Add CSS animation for fade-in effect
const style = document.createElement('style');
style.textContent = `
    .fade-in {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                document.getElementById('pending-tab').click();
                break;
            case '2':
                e.preventDefault();
                document.getElementById('archived-tab').click();
                break;
            case '3':
                e.preventDefault();
                document.getElementById('eligible-tab').click();
                break;
            case '4':
                e.preventDefault();
                document.getElementById('stats-tab').click();
                break;
            case 'e':
                e.preventDefault();
                toggleAllGroups();
                break;
        }
    }
});

// Auto-save user preferences
function saveUserPreferences() {
    const preferences = {
        groupBy: document.querySelector('select[name="group_by"]')?.value || 'category',
        expandedGroups: Array.from(document.querySelectorAll('.collapse.show')).map(el => el.id)
    };
    
    localStorage.setItem('archivePreferences', JSON.stringify(preferences));
}

function loadUserPreferences() {
    const preferences = JSON.parse(localStorage.getItem('archivePreferences') || '{}');
    
    if (preferences.groupBy) {
        const groupBySelect = document.querySelector('select[name="group_by"]');
        if (groupBySelect) {
            groupBySelect.value = preferences.groupBy;
        }
    }
    
    if (preferences.expandedGroups) {
        setTimeout(() => {
            preferences.expandedGroups.forEach(groupId => {
                const element = document.getElementById(groupId);
                if (element && !element.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(element).show();
                }
            });
        }, 100);
    }
}

// Save preferences on page unload
window.addEventListener('beforeunload', saveUserPreferences);

// Load preferences on page load
document.addEventListener('DOMContentLoaded', loadUserPreferences);

// Add stagger animation to book cards
document.addEventListener('DOMContentLoaded', function() {
    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
        card.classList.add('fade-in');
    });
});

// Search functionality enhancement
function enhanceSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length > 2 || this.value.length === 0) {
                    this.form.submit();
                }
            }, 500);
        });
    }
}

document.addEventListener('DOMContentLoaded', enhanceSearch);

// Add loading states to buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 3000);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->connect();

// Get filter parameters
$department = $_GET['dept'] ?? 'all';
$program = $_GET['program'] ?? '';
$courseCode = $_GET['course_code'] ?? '';
$yearLevel = $_GET['year_level'] ?? '';
$semester = $_GET['semester'] ?? '';

// Department mapping
$departments = ['BIT', 'EDUCATION', 'HBM', 'COMPSTUD'];

// Build query based on filters
$query = "SELECT DISTINCT b.course_code, b.subject_name, sd.description
          FROM books b
          LEFT JOIN subject_descriptions sd 
              ON b.course_code = sd.course_code AND b.subject_name = sd.subject_name
          WHERE 1=1";

$params = [];

if ($department !== 'all' && $department !== 'summary') {
    $query .= " AND b.category LIKE ?";
    $params[] = "%{$department}%";
}

if (!empty($program)) {
    $query .= " AND b.program = ?";
    $params[] = $program;
}

if (!empty($courseCode)) {
    $query .= " AND b.course_code = ?";
    $params[] = $courseCode;
}

if (!empty($yearLevel)) {
    $query .= " AND b.year_level LIKE ?";
    $params[] = "%{$yearLevel}%";
}

if (!empty($semester)) {
    $query .= " AND b.semester LIKE ?";
    $params[] = "%{$semester}%";
}

$query .= " ORDER BY b.course_code, b.subject_name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['subjects' => $subjects]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
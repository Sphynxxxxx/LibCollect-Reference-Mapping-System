<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->connect();

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['descriptions']) || !is_array($data['descriptions'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid input']));
}

$saved = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    foreach ($data['descriptions'] as $desc) {
        if (empty($desc['course_code']) || empty($desc['subject_name']) || empty($desc['description'])) {
            continue;
        }
        
        // Insert or update description
        $stmt = $pdo->prepare("
            INSERT INTO subject_descriptions (course_code, subject_name, description, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                description = VALUES(description),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $desc['course_code'],
            $desc['subject_name'],
            $desc['description']
        ]);
        
        $saved++;
    }
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'saved' => $saved,
        'message' => "Successfully saved {$saved} description(s)"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
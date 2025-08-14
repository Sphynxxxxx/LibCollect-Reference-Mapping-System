<?php
// Export functionality for reports
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

$exportType = $_GET['type'] ?? 'csv';
$department = $_GET['dept'] ?? 'all';
$reportType = $_GET['report'] ?? 'detailed';

// Department names
$departmentNames = [
    'BIT' => 'Bachelor of Industrial Technology',
    'EDUCATION' => 'Education Department', 
    'HBM' => 'Hotel and Business Management',
    'COMPSTUD' => 'Computer Studies',
    'all' => 'All Departments'
];

$departmentName = $departmentNames[$department] ?? 'Unknown';
$filename = "ISAT_U_Library_Report_" . $department . "_" . date('Y-m-d');

if ($exportType === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Report header
    fputcsv($output, ['ISAT U Library Management System']);
    fputcsv($output, ['Report Type: ' . ucfirst($reportType) . ' Report']);
    fputcsv($output, ['Department: ' . $departmentName]);
    fputcsv($output, ['Generated: ' . date('F d, Y g:i A')]);
    fputcsv($output, []); // Empty row
    
    if ($reportType === 'detailed') {
        // Detailed book listing
        fputcsv($output, ['Call No.', 'Title', 'Author', 'ISBN', 'Category', 'Quantity', 'Description', 'Date Added']);
        
        $books = $book->getDetailedReportData($department);
        $callNo = 1;
        
        foreach ($books as $bookItem) {
            fputcsv($output, [
                str_pad($callNo, 3, '0', STR_PAD_LEFT),
                $bookItem['title'],
                $bookItem['author'],
                $bookItem['isbn'],
                $bookItem['category'],
                $bookItem['quantity'],
                $bookItem['description'],
                date('Y-m-d', strtotime($bookItem['created_at']))
            ]);
            $callNo++;
        }
        
        // Summary
        fputcsv($output, []); // Empty row
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Titles:', count($books)]);
        fputcsv($output, ['Total Volumes:', array_sum(array_column($books, 'quantity'))]);
        
    } elseif ($reportType === 'statistics') {
        // Department statistics
        fputcsv($output, ['Department', 'Titles', 'Volumes', 'Title %', 'Volume %', 'Avg Copies']);
        
        $stats = $book->getDepartmentStatistics();
        foreach ($stats as $stat) {
            fputcsv($output, [
                $departmentNames[$stat['category']] ?? $stat['category'],
                $stat['title_count'],
                $stat['volume_count'],
                $stat['title_percentage'] . '%',
                $stat['volume_percentage'] . '%',
                $stat['avg_copies']
            ]);
        }
        
        // Overall totals
        $totalTitles = array_sum(array_column($stats, 'title_count'));
        $totalVolumes = array_sum(array_column($stats, 'volume_count'));
        
        fputcsv($output, []); // Empty row
        fputcsv($output, ['TOTAL', $totalTitles, $totalVolumes, '100%', '100%', '']);
    }
    
    fclose($output);
    exit;

} elseif ($exportType === 'excel') {
    // Simple Excel format (HTML table with Excel MIME type)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<h2>ISAT U Library Management System</h2>';
    echo '<h3>' . ucfirst($reportType) . ' Report - ' . $departmentName . '</h3>';
    echo '<p>Generated: ' . date('F d, Y g:i A') . '</p>';
    echo '<br>';
    
    if ($reportType === 'detailed') {
        echo '<table border="1">';
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<th>Call No.</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Quantity</th><th>Description</th>';
        echo '</tr>';
        
        $books = $book->getDetailedReportData($department);
        $callNo = 1;
        
        foreach ($books as $bookItem) {
            echo '<tr>';
            echo '<td>' . str_pad($callNo, 3, '0', STR_PAD_LEFT) . '</td>';
            echo '<td>' . htmlspecialchars($bookItem['title']) . '</td>';
            echo '<td>' . htmlspecialchars($bookItem['author']) . '</td>';
            echo '<td>' . htmlspecialchars($bookItem['isbn']) . '</td>';
            echo '<td>' . htmlspecialchars($bookItem['category']) . '</td>';
            echo '<td>' . $bookItem['quantity'] . '</td>';
            echo '<td>' . htmlspecialchars($bookItem['description']) . '</td>';
            echo '</tr>';
            $callNo++;
        }
        
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<td colspan="5">TOTAL</td>';
        echo '<td>' . array_sum(array_column($books, 'quantity')) . '</td>';
        echo '<td>' . count($books) . ' titles</td>';
        echo '</tr>';
        echo '</table>';
        
    } elseif ($reportType === 'statistics') {
        echo '<table border="1">';
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<th>Department</th><th>Titles</th><th>Volumes</th><th>Title %</th><th>Volume %</th><th>Avg Copies</th>';
        echo '</tr>';
        
        $stats = $book->getDepartmentStatistics();
        foreach ($stats as $stat) {
            echo '<tr>';
            echo '<td>' . ($departmentNames[$stat['category']] ?? $stat['category']) . '</td>';
            echo '<td>' . $stat['title_count'] . '</td>';
            echo '<td>' . $stat['volume_count'] . '</td>';
            echo '<td>' . $stat['title_percentage'] . '%</td>';
            echo '<td>' . $stat['volume_percentage'] . '%</td>';
            echo '<td>' . $stat['avg_copies'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '</body></html>';
    exit;

} else {
    // Invalid export type
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid export type';
    exit;
}
?>
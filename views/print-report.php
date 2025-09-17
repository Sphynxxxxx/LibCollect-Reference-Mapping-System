<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

$department = $_GET['dept'] ?? 'all';
$isCustom = isset($_GET['custom']);

// Get data
$stats = $book->getBookStats();
$allBooks = $book->getAllBooks();

// Department information with course descriptions - Updated to match database categories
$departments = [
    'BIT' => [
        'name' => 'Bachelor of Industrial Technology',
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in ELECTRONICS TECHNOLOGY',
        'description' => 'This course introduces the students to the basic occupational safety and health. The students will learn safe work practices and programs in preparation to specialty and general theories associated and physical encountered in the work. The course will also cover the equipment processing laws on OSH and facilities key concepts, principles and practices that are foundational knowledge, requirements, develop individual abilities and capacities on basic operations to become effective OSH programs and advancement in specialized OSH work in a working towards and corresponding specified measures in the workplace.',
        'books' => []
    ],
    'EDUCATION' => [
        'name' => 'Education Department', 
        'full_name' => 'EDUCATION DEPARTMENT',
        'description' => 'This course enables an introduction to theories and applications of primary educational devices involving electronic systems. The students will build comprehension in the content and performance presenting machine assembled which related OHMS Law and other fundamental basic electronic circuit analysis.',
        'books' => []
    ],
    'HBM' => [
        'name' => 'Hotel and Business Management',
        'full_name' => 'HOTEL AND BUSINESS MANAGEMENT',
        'description' => 'This program provides comprehensive training in hospitality and business management principles, covering hotel operations, customer service, financial management, and tourism industry practices.',
        'books' => []
    ],
    'COMPSTUD' => [
        'name' => 'Computer Studies',
        'full_name' => 'COMPUTER STUDIES',
        'description' => 'This course covers fundamental computing concepts, programming languages, software development, and information technology applications for modern business and academic environments.',
        'books' => []
    ]
];

// Group books by department - Handle multi-context books
foreach ($allBooks as $bookItem) {
    // Handle multi-context books (books that belong to multiple departments)
    $categories = explode(',', $bookItem['category']);
    foreach ($categories as $category) {
        $category = trim($category);
        if (isset($departments[$category])) {
            $departments[$category]['books'][] = $bookItem;
        }
    }
}

// Determine what to print
$reportTitle = '';
$reportData = [];

if ($department === 'all') {
    $reportTitle = 'LIST OF REFERENCES';
    $reportData = $departments;
} elseif ($department === 'summary') {
    $reportTitle = 'LIBRARY STATISTICS SUMMARY';
    $reportData = ['summary' => true];
} elseif (isset($departments[$department])) {
    $reportTitle = 'LIST OF REFERENCES';
    $reportData = [$department => $departments[$department]];
}

$currentYear = date('Y');
$academicYear = $currentYear . '-' . ($currentYear + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $reportTitle; ?> - ISAT U Miagao Campus Library</title>
    <style>
        @page {
            margin: 0.75in 0.5in;
            size: A4;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.2;
            color: black;
            margin: 0;
            padding: 0;
        }

        .no-print { 
            display: none !important; 
        }

        @media screen {
            .no-print {
                display: block !important;
                position: fixed;
                top: 10px;
                right: 10px;
                z-index: 1000;
                background: white;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }
            body {
                margin: 20px;
                background: white;
            }
        }

        /* Header Section */
        .document-header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }

        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .logo-left, .logo-right {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10pt;
            font-weight: bold;
        }

        .logo-left {
            background: url('../assets/images/ISATU Logo.png') no-repeat center;
            background-size: contain;
        }

        .logo-right {
            background: url('../assets/images/Bagong_Pilipinas_logo.png') no-repeat center;
            background-size: contain;
        }

        /* Print-specific styles for logos */
        @media print {
            .logo-left {
                background: url('../assets/images/ISATU Logo.png') no-repeat center !important;
                background-size: contain !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .logo-right {
                background: url('../assets/images/Bagong_Pilipinas_logo.png') no-repeat center;
                background-size: contain !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        .university-info {
            flex: 1;
            margin: 0 20px;
        }

        .university-info h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 3px 0;
            line-height: 1.1;
        }

        .university-info h2 {
            font-size: 11pt;
            font-weight: bold;
            margin: 0 0 8px 0;
        }

        .contact-info {
            font-size: 9pt;
            line-height: 1.2;
            margin: 5px 0;
        }

        .program-title {
            margin: 15px 0;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.3;
        }

        .academic-year {
            font-size: 10pt;
            font-weight: bold;
            margin: 8px 0;
        }

        .semester-info {
            font-size: 10pt;
            margin: 15px 0;
            font-style: italic;
        }

        /* Course Section */
        .course-section {
            margin: 20px 0;
            border: 1px solid black;
            page-break-inside: avoid;
        }

        .course-header {
            background-color: #f5f5f5;
            padding: 8px;
            border-bottom: 1px solid black;
            font-weight: bold;
            font-size: 10pt;
        }

        .course-description {
            padding: 10px;
            font-size: 9pt;
            line-height: 1.4;
            text-align: justify;
            margin-bottom: 10px;
        }

        /* Table Styles */
        .references-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-top: 10px;
        }

        .references-table th,
        .references-table td {
            border: 1px solid black;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
        }

        .references-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }

        .references-table .call-no {
            width: 8%;
            text-align: center;
        }

        .references-table .title {
            width: 45%;
        }

        .references-table .author {
            width: 25%;
        }

        .references-table .copyright {
            width: 12%;
            text-align: center;
        }

        .references-table .copies {
            width: 10%;
            text-align: center;
        }

        .table-footer {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .table-footer td {
            text-align: right;
        }

        .table-footer .total-cell {
            text-align: center;
        }

        /* Page break handling */
        .page-break {
            page-break-before: always;
        }

        /* Footer logos */
        .footer-logos {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }

        .footer-logos img {
            height: 25px;
            margin: 0 10px;
            opacity: 0.7;
        }

        /* Print button styles */
        .print-controls {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .print-controls button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            margin-right: 5px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .print-controls button:hover {
            background: #0056b3;
        }

        .print-controls button.secondary {
            background: #6c757d;
        }

        .print-controls button.secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="print-controls">
            <button onclick="window.print()">
                🖨️ Print Report
            </button>
            <button onclick="window.close()" class="secondary">
                ✖️ Close
            </button>
        </div>
    </div>

    <!-- Document Header -->
    <div class="document-header">
        <div class="header-logos">
            <div class="logo-left"></div>
            <div class="university-info">
                <h1>Iloilo Science and Technology University</h1>
                <h2>Miagao Campus</h2>
                <div class="contact-info">
                    Brgy. Miagao, Miagao, Iloilo • 5023 Philippines<br>
                    Telephone: (033) 315-9362 | Telefax: (033) 315-93<br>
                    Email: www.isatu.edu.ph<br>
                    miagao.pio@isatu.edu.ph
                </div>
            </div>
            <div class="logo-right"></div>
        </div>

        <div class="program-title">
            <strong><?php echo $reportTitle; ?></strong><br>
            <?php if ($department !== 'all' && $department !== 'summary' && isset($departments[$department])): ?>
                <?php echo $departments[$department]['full_name']; ?><br>
            <?php endif; ?>
        </div>

        <div class="academic-year">
            <strong>ACADEMIC YEAR <?php echo $academicYear; ?></strong>
        </div>

        <?php if ($department !== 'summary'): ?>
        <div class="semester-info">
            <strong>First Year - First Semester</strong>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($department === 'summary'): ?>
        <!-- Summary Report -->
        <div class="course-section">
            <div class="course-header">LIBRARY COLLECTION SUMMARY</div>
            <div class="course-description">
                This summary provides an overview of the complete library collection across all academic departments at ISAT U Miagao Campus. The collection supports various academic programs with comprehensive resources for student learning and faculty research.
            </div>
            
            <table class="references-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>No. of Titles</th>
                        <th>No. of Volumes</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Calculate totals for summary
                    $totalTitles = 0;
                    $totalVolumes = 0;
                    $deptStats = [];
                    
                    // Count unique books per department (handle multi-context books)
                    foreach ($allBooks as $bookItem) {
                        $categories = explode(',', $bookItem['category']);
                        foreach ($categories as $category) {
                            $category = trim($category);
                            if (isset($departments[$category])) {
                                if (!isset($deptStats[$category])) {
                                    $deptStats[$category] = ['titles' => 0, 'volumes' => 0];
                                }
                                $deptStats[$category]['titles']++;
                                $deptStats[$category]['volumes'] += $bookItem['quantity'];
                            }
                        }
                        $totalTitles++;
                        $totalVolumes += $bookItem['quantity'];
                    }
                    
                    foreach ($departments as $code => $dept): 
                        $titleCount = isset($deptStats[$code]) ? $deptStats[$code]['titles'] : 0;
                        $volumeCount = isset($deptStats[$code]) ? $deptStats[$code]['volumes'] : 0;
                        $percentage = $totalTitles > 0 ? round(($titleCount / $totalTitles) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $dept['name']; ?></td>
                        <td class="copies"><?php echo $titleCount; ?></td>
                        <td class="copies"><?php echo $volumeCount; ?></td>
                        <td class="copies"><?php echo $percentage; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-footer">
                        <td><strong>TOTAL</strong></td>
                        <td class="total-cell"><strong><?php echo $totalTitles; ?></strong></td>
                        <td class="total-cell"><strong><?php echo $totalVolumes; ?></strong></td>
                        <td class="total-cell"><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

    <?php else: ?>
        <!-- Detailed Department Reports -->
        <?php foreach ($reportData as $code => $dept): ?>
            <div class="course-section <?php echo ($code !== array_key_first($reportData)) ? 'page-break' : ''; ?>">
                <div class="course-header">
                    <?php echo strtoupper($code); ?> <?php echo $dept['name']; ?>
                </div>
                
                <div class="course-description">
                    <?php echo $dept['description']; ?>
                </div>

                <table class="references-table">
                    <thead>
                        <tr>
                            <th class="call-no">Call No.</th>
                            <th class="title">Title</th>
                            <th class="author">Author</th>
                            <th class="copyright">Copyright</th>
                            <th class="copies">No. of Copies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $bookNumber = 1;
                        // Remove duplicates for single department view
                        $uniqueBooks = [];
                        foreach ($dept['books'] as $bookItem) {
                            $bookKey = $bookItem['title'] . '|' . $bookItem['author'];
                            if (!isset($uniqueBooks[$bookKey])) {
                                $uniqueBooks[$bookKey] = $bookItem;
                            } else {
                                // Add quantities for duplicate books
                                $uniqueBooks[$bookKey]['quantity'] += $bookItem['quantity'];
                            }
                        }
                        
                        foreach ($uniqueBooks as $bookItem): 
                        ?>
                        <tr>
                            <td class="call-no"><?php echo str_pad($bookNumber, 3, '0', STR_PAD_LEFT); ?></td>
                            <td class="title"><?php echo htmlspecialchars($bookItem['title']); ?></td>
                            <td class="author"><?php echo htmlspecialchars($bookItem['author']); ?></td>
                            <td class="copyright"><?php echo $bookItem['publication_year'] ?? date('Y', strtotime($bookItem['created_at'])); ?></td>
                            <td class="copies"><?php echo $bookItem['quantity']; ?></td>
                        </tr>
                        <?php 
                        $bookNumber++;
                        endforeach; 
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-footer">
                            <td colspan="4"><strong>Total no. of Titles:</strong></td>
                            <td class="total-cell"><strong><?php echo count($uniqueBooks); ?></strong></td>
                        </tr>
                        <tr class="table-footer">
                            <td colspan="4"><strong>Total no. of Volumes:</strong></td>
                            <td class="total-cell"><strong><?php echo array_sum(array_column($uniqueBooks, 'quantity')); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer with Partner Logos -->
    <div class="footer-logos">
        <div style="font-size: 8pt; color: #666; margin-bottom: 10px;">
            In partnership with leading educational institutions and organizations
        </div>
        <!-- Placeholder for actual logos -->
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="width: 30px; height: 20px; background: #4285f4; border-radius: 3px;"></div>
            <div style="width: 30px; height: 20px; background: #db4437; border-radius: 3px;"></div>
            <div style="width: 30px; height: 20px; background: #f4b400; border-radius: 3px;"></div>
            <div style="width: 30px; height: 20px; background: #0f9d58; border-radius: 3px;"></div>
            <div style="width: 30px; height: 20px; background: #ab47bc; border-radius: 3px;"></div>
            <div style="width: 30px; height: 20px; background: #00acc1; border-radius: 3px;"></div>
        </div>
    </div>

    <script>
        // Auto-print option (uncomment if needed)
        // window.onload = function() { window.print(); }
        
        // Print function
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Get filter parameters
$department = $_GET['dept'] ?? 'all';
$program = $_GET['program'] ?? '';
$courseCode = $_GET['course_code'] ?? '';
$yearLevel = $_GET['year_level'] ?? '';
$semester = $_GET['semester'] ?? '';
$specializedTrack = $_GET['specialized_track'] ?? '';
$isCustom = isset($_GET['custom']);

// Get data
$stats = $book->getBookStats();
$allBooks = $book->getAllBooks();

// Department information with course descriptions
$departments = [
    'BIT' => [
        'name' => 'Bachelor of Industrial Technology',
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY',
        'books' => []
    ],
    'EDUCATION' => [
        'name' => 'Education Department', 
        'full_name' => 'EDUCATION DEPARTMENT',
        'books' => []
    ],
    'HBM' => [
        'name' => 'Hotel and Business Management',
        'full_name' => 'HOTEL AND BUSINESS MANAGEMENT',
        'books' => []
    ],
    'COMPSTUD' => [
        'name' => 'Computer Studies',
        'full_name' => 'COMPUTER STUDIES',
        'books' => []
    ]
];

// Map program codes to full names
$programNames = [
    'BIT-Electrical' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in ELECTRICAL TECHNOLOGY',
    'BIT-Electronics' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in ELECTRONICS TECHNOLOGY',
    'BIT-Automotive' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in AUTOMOTIVE TECHNOLOGY',
    'BIT-HVACR' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in HVAC/R TECHNOLOGY',
    'BSIS' => 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS',
    'BSIT' => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
    'BSHMCA' => 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT - CULINARY ARTS',
    'BSEntrep' => 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP',
    'BSTM' => 'BACHELOR OF SCIENCE IN TOURISM MANAGEMENT',
    'BTLEd-IA' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION - INDUSTRIAL ARTS',
    'BTLEd-HE' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION - HOME ECONOMICS',
    'BSED-Science' => 'BACHELOR OF SECONDARY EDUCATION - SCIENCE',
    'BSED-Math' => 'BACHELOR OF SECONDARY EDUCATION - MATHEMATICS'
];

// Map specialized track codes to full names
$trackNames = [
    'BUSINESS_ANALYTICS' => 'Business Analytics Track',
    'SERVICE_MANAGEMENT_BPO' => 'Service Management for Business Process Outsourcing',
    'ANIMATION' => 'Animation Track',
    'DATA_SCIENCE' => 'Data Science Track'
];

// Apply filters to books
$filteredBooks = $allBooks;

// Filter by program
if (!empty($program)) {
    $filteredBooks = array_filter($filteredBooks, function($book) use ($program) {
        return !empty($book['program']) && trim($book['program']) === $program;
    });
}

// Filter by course code
if (!empty($courseCode)) {
    $filteredBooks = array_filter($filteredBooks, function($book) use ($courseCode) {
        return !empty($book['course_code']) && trim($book['course_code']) === $courseCode;
    });
}

// Filter by year level
if (!empty($yearLevel)) {
    $filteredBooks = array_filter($filteredBooks, function($book) use ($yearLevel) {
        if (empty($book['year_level'])) return false;
        $yearLevels = array_map('trim', explode(',', $book['year_level']));
        return in_array($yearLevel, $yearLevels);
    });
}

// Filter by semester (only for non-specialized track books)
if (!empty($semester)) {
    $filteredBooks = array_filter($filteredBooks, function($book) use ($semester) {
        // Skip books with specialized tracks (they don't have semesters)
        if (!empty($book['specialized_track'])) return false;
        
        if (empty($book['semester'])) return false;
        $semesters = array_map('trim', explode(',', $book['semester']));
        return in_array($semester, $semesters);
    });
}

// Filter by specialized track
if (!empty($specializedTrack)) {
    $filteredBooks = array_filter($filteredBooks, function($book) use ($specializedTrack) {
        return !empty($book['specialized_track']) && trim($book['specialized_track']) === $specializedTrack;
    });
}

// Function to get custom description from database
function getCustomDescription($pdo, $courseCode, $subjectName) {
    try {
        $stmt = $pdo->prepare("SELECT description FROM subject_descriptions WHERE course_code = ? AND subject_name = ? LIMIT 1");
        $stmt->execute([$courseCode, $subjectName]);
        $result = $stmt->fetchColumn();
        return $result ? $result : '';
    } catch (Exception $e) {
        return '';
    }
}

// Group books by department, program, year level, semester AND specialized track AND by subject
$departmentData = [];
foreach ($filteredBooks as $bookItem) {
    $categories = explode(',', $bookItem['category']);
    foreach ($categories as $category) {
        $category = trim($category);
        if (isset($departments[$category])) {
            $bookProgram = trim($bookItem['program'] ?? '');
            $bookYearLevels = !empty($bookItem['year_level']) ? array_map('trim', explode(',', $bookItem['year_level'])) : [''];
            $bookSemesters = !empty($bookItem['semester']) ? array_map('trim', explode(',', $bookItem['semester'])) : [''];
            $bookSpecializedTrack = trim($bookItem['specialized_track'] ?? '');
            
            // Create entries for each year level and semester combination
            foreach ($bookYearLevels as $yl) {
                // If it's a specialized track, don't iterate semesters
                if ($yl === 'Specialized Track' && !empty($bookSpecializedTrack)) {
                    // Use specialized track as the grouping key part
                    $groupKey = $category . '|' . $bookProgram . '|' . $yl . '|' . $bookSpecializedTrack;
                    
                    if (!isset($departmentData[$groupKey])) {
                        $departmentData[$groupKey] = [
                            'department' => $category,
                            'program' => $bookProgram,
                            'year_level' => $yl,
                            'semester' => '', // No semester for specialized tracks
                            'specialized_track' => $bookSpecializedTrack,
                            'subjects' => []
                        ];
                    }
                    
                    // Create subject key
                    $subjectKey = $bookItem['course_code'] . '|' . $bookItem['subject_name'];
                    
                    if (!isset($departmentData[$groupKey]['subjects'][$subjectKey])) {
                        $customDesc = getCustomDescription($pdo, $bookItem['course_code'], $bookItem['subject_name']);
                        
                        $departmentData[$groupKey]['subjects'][$subjectKey] = [
                            'course_code' => $bookItem['course_code'],
                            'subject_name' => $bookItem['subject_name'],
                            'description' => $bookItem['description'] ?? '',
                            'custom_description' => $customDesc,
                            'books' => []
                        ];
                    }
                    
                    $departmentData[$groupKey]['subjects'][$subjectKey]['books'][] = $bookItem;
                } else {
                    // Regular year level with semesters
                    foreach ($bookSemesters as $sem) {
                        $groupKey = $category . '|' . $bookProgram . '|' . $yl . '|' . $sem;
                        
                        if (!isset($departmentData[$groupKey])) {
                            $departmentData[$groupKey] = [
                                'department' => $category,
                                'program' => $bookProgram,
                                'year_level' => $yl,
                                'semester' => $sem,
                                'specialized_track' => '',
                                'subjects' => []
                            ];
                        }
                        
                        $subjectKey = $bookItem['course_code'] . '|' . $bookItem['subject_name'];
                        
                        if (!isset($departmentData[$groupKey]['subjects'][$subjectKey])) {
                            $customDesc = getCustomDescription($pdo, $bookItem['course_code'], $bookItem['subject_name']);
                            
                            $departmentData[$groupKey]['subjects'][$subjectKey] = [
                                'course_code' => $bookItem['course_code'],
                                'subject_name' => $bookItem['subject_name'],
                                'description' => $bookItem['description'] ?? '',
                                'custom_description' => $customDesc,
                                'books' => []
                            ];
                        }
                        
                        $departmentData[$groupKey]['subjects'][$subjectKey]['books'][] = $bookItem;
                    }
                }
            }
        }
    }
}

// Determine what to print and get dynamic program title
$reportTitle = 'LIST OF REFERENCES';
$reportData = [];
$dynamicProgramTitle = '';
$showProgramTitle = false; 

// Get dynamic program title - ONLY if specific filters are applied
if (!empty($filteredBooks) && $department !== 'summary') {
    if (!empty($program) && isset($programNames[$program])) {
        $dynamicProgramTitle = $programNames[$program];
        $showProgramTitle = true;
    }
    elseif ($department !== 'all' && isset($departments[$department]) && empty($program)) {
        $dynamicProgramTitle = $departments[$department]['full_name'];
        $showProgramTitle = true;
    }
    else {
        $showProgramTitle = false;
        $dynamicProgramTitle = '';
    }
}

if ($department === 'all') {
    $reportData = $departmentData;
    $showProgramTitle = false;
    $dynamicProgramTitle = '';
} elseif ($department === 'summary') {
    $reportTitle = 'LIBRARY STATISTICS SUMMARY';
    $reportData = ['summary' => true];
    $showProgramTitle = false;
    $dynamicProgramTitle = '';
} elseif (isset($departments[$department])) {
    $reportData = array_filter($departmentData, function($data) use ($department) {
        return $data['department'] === $department;
    });
    
    if (empty($program)) {
        $dynamicProgramTitle = $departments[$department]['full_name'];
        $showProgramTitle = true;
    }
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
            padding-bottom: 0px;
            position: relative;
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

        .dynamic-program-title {
            margin: 10px 0;
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.3;
        }

        .section-program-title {
            margin: 0;
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.3;
            text-align: center;
            padding: 8px;
        }

        .section-year-semester {
            margin: 0 0 10px 0;
            font-size: 9pt;
            font-weight: bold;
            line-height: 1.2;
            text-align: center;
            padding: 5px;
            border-top: none;
        }

        .academic-year {
            font-size: 10pt;
            font-weight: bold;
            margin: 8px 0;
        }

        .semester-info {
            font-size: 10pt;
            margin: 8px 0;
            text-align: center;
            font-weight: bold;
        }

        /* Course Section */
        .course-section {
            margin: 10px 0;
            border: 1px solid black;
            page-break-inside: avoid;
            margin-bottom: 20px; 
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

        .no-data-message {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        /* Page break handling */
        .page-break {
            page-break-before: always;
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

        /* Footer logos - Updated for first page only */
        .footer-logos {
            text-align: center;
            padding: 15px 0;
            background: white;
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        @media print {
            @page {
                margin-bottom: 120px;
            }

            @page :first {
                @bottom-center {
                    content: element(footer);
                }
            }

            /* Hide all footer logos by default */
            .footer-logos {
                display: none !important;
            }
            
            /* Show only the first footer (marked with data-footer-position="1") */
            .footer-logos[data-footer-position="1"] {
                display: block !important;
                position: running(footer);
                text-align: center;
                padding: 15px 0;
                background: white;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .footer-logos[data-footer-position="1"] img {
                max-width: 800px;
                max-height: 100px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media screen {
            .footer-logos {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: white;
            }
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
            <strong><?php echo $reportTitle; ?></strong>
        </div>

        <?php if ($showProgramTitle && !empty($dynamicProgramTitle) && $department !== 'summary' && $department !== 'all'): ?>
        <div class="dynamic-program-title">
            <?php echo $dynamicProgramTitle; ?>
        </div>
        <?php endif; ?>

        <div class="academic-year">
            <strong>ACADEMIC YEAR <?php echo $academicYear; ?></strong>
        </div>

        <?php 
        // ONLY show semester info if there's a specific filter AND we're not showing program title in sections
        if ($department !== 'summary' && $department !== 'all' && (!empty($yearLevel) || !empty($semester) || !empty($specializedTrack))): 
        ?>
        <div class="semester-info">
            <?php 
            $semesterText = '';
            
            // Check if Specialized Track year level is selected
            if ($yearLevel === 'Specialized Track' && !empty($specializedTrack)) {
                $trackDisplayName = isset($trackNames[$specializedTrack]) ? $trackNames[$specializedTrack] : str_replace('_', ' ', $specializedTrack);
                $semesterText = 'FREE ELECTIVE: ' . strtoupper($trackDisplayName);
            } else {
                // Regular year level and semester display
                if (!empty($yearLevel) && $yearLevel !== 'Specialized Track') {
                    $semesterText .= $yearLevel;
                }
                if (!empty($semester)) {
                    if (!empty($yearLevel) && $yearLevel !== 'Specialized Track') {
                        $semesterText .= ' - ';
                    }
                    $semesterText .= $semester;
                }
            }
            echo $semesterText;
            ?>
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
                    $totalTitles = 0;
                    $totalVolumes = 0;
                    $deptStats = [];
                    
                    foreach ($filteredBooks as $bookItem) {
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
        <!-- Detailed Department Reports by Program, Year Level, Semester/Specialized Track, and Subject -->
        <?php 
        $sectionCounter = 0;
        $currentDepartment = '';
        $currentProgram = '';

        foreach ($reportData as $groupKey => $groupData): 
            if (empty($groupData['subjects'])) continue;
            
            // Show department title when department changes 
            if ($department === 'all' && $currentDepartment !== $groupData['department']) {
                $currentDepartment = $groupData['department'];
                $currentProgram = ''; 
                if ($sectionCounter > 0) {
                    echo '<div class="page-break"></div>';
                }
                echo '<div class="dynamic-program-title" style="margin: 20px 0;">';
                echo $departments[$currentDepartment]['full_name'];
                echo '</div>';
            }
            
            // This prevents duplication when user filters by specific program
            $programKey = $groupData['department'] . '|' . $groupData['program'];
            if ($currentProgram !== $programKey && !empty($groupData['program']) && isset($programNames[$groupData['program']])) {
                $currentProgram = $programKey;
                
                // ONLY show if we're NOT already showing it in the header
                if (empty($program)) {
                    echo '<div class="section-program-title">';
                    echo $programNames[$groupData['program']];
                    echo '</div>';
                }
            }
            
            // Show year level and semester OR specialized track under the program
            $yearSemesterText = '';
            
            // Check if we haven't already displayed this in the top header
            $showYearSemester = true;
            if (!empty($yearLevel) || !empty($semester) || !empty($specializedTrack)) {
                $showYearSemester = false;
            }
            
            // Check if Specialized Track year level
            if (!empty($groupData['year_level']) && $groupData['year_level'] === 'Specialized Track' && !empty($groupData['specialized_track'])) {
                $trackDisplayName = isset($trackNames[$groupData['specialized_track']]) ? $trackNames[$groupData['specialized_track']] : str_replace('_', ' ', $groupData['specialized_track']);
                $yearSemesterText = 'FREE ELECTIVE: ' . strtoupper($trackDisplayName);
            } else {
                // Regular year level and semester
                if (!empty($groupData['year_level']) && $groupData['year_level'] !== 'Specialized Track') {
                    $yearSemesterText .= $groupData['year_level'];
                }
                if (!empty($groupData['semester'])) {
                    if (!empty($yearSemesterText)) {
                        $yearSemesterText .= ' - ';
                    }
                    $yearSemesterText .= $groupData['semester'];
                }
            }
            
            // ONLY display year/semester section if not filtered OR if showing "all" departments with multiple programs
            if (!empty($yearSemesterText) && ($showYearSemester || $department === 'all')): ?>
                <div class="section-year-semester">
                    <?php echo $yearSemesterText; ?>
                </div>
            <?php endif;
            
            foreach ($groupData['subjects'] as $subjectKey => $subjectData):
                $sectionCounter++;
        ?>
            <div class="course-section">
                <div class="course-header">
                    <?php 
                    // Display course code and subject name
                    echo strtoupper($subjectData['course_code']) . ' ' . $subjectData['subject_name'];
                    ?>
                </div>
                
                <div class="course-description">
                    <?php 
                    if (!empty($subjectData['custom_description'])) {
                        echo $subjectData['custom_description'];
                    } elseif (!empty($subjectData['description'])) {
                        echo $subjectData['description'];
                    } elseif (isset($departments[$groupData['department']]['description']) && !empty($departments[$groupData['department']]['description'])) {
                        echo $departments[$groupData['department']]['description'];
                    } else {
                        echo ''; 
                    }
                    ?>
                </div>

                <?php if (empty($subjectData['books'])): ?>
                    <div class="no-data-message">
                        No books found for this subject.
                    </div>
                <?php else: ?>
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
                            $uniqueBooks = [];

                            foreach ($subjectData['books'] as $bookItem) {
                                $bookKey = $bookItem['title'] . '|' . $bookItem['author'];
                                if (!isset($uniqueBooks[$bookKey])) {
                                    $uniqueBooks[$bookKey] = $bookItem;
                                } else {
                                    $uniqueBooks[$bookKey]['quantity'] += $bookItem['quantity'];
                                }
                            }

                            // Sort books by copyright year (newest first)
                            usort($uniqueBooks, function($a, $b) {
                                $yearA = $a['publication_year'] ?? date('Y', strtotime($a['created_at'] ?? 'now'));
                                $yearB = $b['publication_year'] ?? date('Y', strtotime($b['created_at'] ?? 'now'));
                                
                                // Sort descending (newest first)
                                return $yearB - $yearA;
                            });

                            foreach ($uniqueBooks as $bookItem): 
                            ?>
                            <tr>
                                <td class="call-no"><?php echo !empty($bookItem['isbn']) ? htmlspecialchars($bookItem['isbn']) :str_pad($bookNumber, 3, '0', STR_PAD_LEFT); ?></td>
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
                <?php endif; ?>
            </div>
        <?php 
            endforeach;
        endforeach; 
        ?>
    <?php endif; ?>

    <!-- Footer with Partner Logos - Now only appears on first page when printing -->
    <div class="footer-logos" data-footer-position="1" style="page-break-after: avoid; page-break-inside: avoid;">
        <div style="font-size: 8pt; color: #666; margin-bottom: 10px;">
            In partnership with leading educational institutions and organizations
        </div>
        <div style="display: flex; justify-content: center; align-items: center;">
            <img src="../assets/images/footer.png" alt="Partner Organizations" style="max-width: auto; height: auto; opacity: 0.8;">
        </div>
    </div>

<script>
    function printReport() {
        window.print();
    }

    // Ensure footer only appears on first page by moving it after header
    document.addEventListener('DOMContentLoaded', function() {
        const footer = document.querySelector('.footer-logos');
        const header = document.querySelector('.document-header');
        
        if (footer && header && window.matchMedia('print').matches === false) {
            // On screen view, footer stays at bottom (normal behavior)
        }
        
        // Before printing, move footer right after header
        window.addEventListener('beforeprint', function() {
            const footer = document.querySelector('.footer-logos');
            const header = document.querySelector('.document-header');
            
            if (footer && header) {
                // Insert footer right after header
                header.parentNode.insertBefore(footer, header.nextSibling);
            }
        });
        
        // After printing, move footer back to bottom (optional, for UI consistency)
        window.addEventListener('afterprint', function() {
            const footer = document.querySelector('.footer-logos');
            const body = document.querySelector('body');
            
            if (footer && body) {
                body.appendChild(footer);
            }
        });
    });
</script>
</body>
</html>
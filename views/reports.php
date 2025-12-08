<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
require_once '../classes/Book.php';

$database = new Database();
$pdo = $database->connect();
$book = new Book($pdo);

// Initialize default stats to prevent errors
$stats = [
    'total_books' => 0,
    'unique_authors' => 0,
    'unique_categories' => 0,
    'unique_programs' => 0
];

$allBooks = [];

// Get report data with proper error handling
try {
    $stats = $book->getBookStats();
    $allBooks = $book->getAllBooks();
} catch (Exception $e) {
    // Fallback to direct database queries if Book class methods fail
    error_log("Error in reports.php: " . $e->getMessage());
    
    try {
        $statsQuery = "SELECT 
            COUNT(*) as total_books,
            COUNT(DISTINCT author) as unique_authors,
            COUNT(DISTINCT category) as unique_categories,
            COUNT(DISTINCT program) as unique_programs
            FROM books";
        $stmt = $pdo->query($statsQuery);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $allBooksQuery = "SELECT * FROM books ORDER BY created_at DESC";
        $stmt = $pdo->query($allBooksQuery);
        $allBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        error_log("Fallback query also failed: " . $e2->getMessage());
        // Use default empty values
    }
}

// Get all unique courses from the database (combining subject_name and course_code)
$allCourses = [];
try {
    $coursesQuery = "SELECT DISTINCT CONCAT(course_code, ' - ', subject_name) as course_display 
                     FROM books 
                     WHERE course_code IS NOT NULL AND course_code != '' 
                     AND subject_name IS NOT NULL AND subject_name != ''
                     ORDER BY course_code, subject_name";
    $stmt = $pdo->query($coursesQuery);
    $allCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error getting courses: " . $e->getMessage());
    $allCourses = [];
}

// Process council data - updated to match your database categories with display names
$councils = [
    'BIT' => ['name' => 'Bachelor of Industrial Technology', 'display' => 'BINDTECH', 'books' => []],
    'EDUCATION' => ['name' => 'Education Council', 'display' => 'EDUCATION', 'books' => []],
    'HBM' => ['name' => 'Hotel and Business Management', 'display' => 'HBM', 'books' => []],
    'COMPSTUD' => ['name' => 'Computer Studies', 'display' => 'COMPSTUD', 'books' => []]
];

// Group books by council - handle multi-context categories
if (is_array($allBooks)) {
    foreach ($allBooks as $bookItem) {
        if (isset($bookItem['category'])) {
            $categories = explode(',', $bookItem['category']);
            foreach ($categories as $category) {
                $category = trim($category);
                if (isset($councils[$category])) {
                    $councils[$category]['books'][] = $bookItem;
                }
            }
        }
    }
}

// Prepare chart data with safe defaults
$chartData = [
    'councils' => [],
    'monthly_additions' => [],
    'author_distribution' => []
];

// Council distribution for pie chart
foreach ($councils as $code => $council) {
    $chartData['councils'][] = [
        'label' => $council['display'], // Use display name instead of name
        'value' => count($council['books']),
        'color' => match($code) {
            'BIT' => '#ffc107',
            'EDUCATION' => '#0d6efd',
            'HBM' => '#dc3545',
            'COMPSTUD' => '#212529',
            default => '#6c757d'
        }
    ];
}

// Monthly additions for line chart - using actual database data
$monthlyData = [];
try {
    $monthlyQuery = "SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        DATE_FORMAT(created_at, '%Y-%m') as sort_key,
        COUNT(*) as books 
        FROM books 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY sort_key ASC";

    $stmt = $pdo->query($monthlyQuery);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting monthly data: " . $e->getMessage());
    $monthlyData = [];
}

// Fill missing months with 0
$filledMonthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('M Y', strtotime("-$i months"));
    $sortKey = date('Y-m', strtotime("-$i months"));
    
    $found = false;
    foreach ($monthlyData as $data) {
        if ($data['sort_key'] === $sortKey) {
            $filledMonthlyData[] = [
                'month' => $month,
                'books' => (int)$data['books']
            ];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $filledMonthlyData[] = [
            'month' => $month,
            'books' => 0
        ];
    }
}
$chartData['monthly_additions'] = $filledMonthlyData;

// Author distribution (top 10 authors) - handle multi-context books properly
$authorData = [];
try {
    $authorQuery = "SELECT 
        author, 
        COUNT(*) as book_count,
        SUM(quantity) as total_copies
        FROM books 
        GROUP BY author 
        ORDER BY book_count DESC 
        LIMIT 10";

    $stmt = $pdo->query($authorQuery);
    $authorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting author data: " . $e->getMessage());
    $authorData = [];
}
$chartData['author_distribution'] = $authorData;

// Get additional statistics with error handling
$additionalStats = [
    'archived_books' => 0,
    'recent_additions' => 0,
    'multi_context' => 0
];

try {
    // Get archived books count
    $archivedQuery = "SELECT COUNT(*) as archived_books FROM archived_books";
    $stmt = $pdo->query($archivedQuery);
    $additionalStats['archived_books'] = $stmt->fetchColumn() ?: 0;
    
    // Get recent additions (last 30 days)
    $recentQuery = "SELECT COUNT(*) as recent_additions FROM books WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $pdo->query($recentQuery);
    $additionalStats['recent_additions'] = $stmt->fetchColumn() ?: 0;
    
    // Get multi-context books count
    $multiContextQuery = "SELECT COUNT(*) as multi_context FROM books WHERE is_multi_context = 1";
    $stmt = $pdo->query($multiContextQuery);
    $additionalStats['multi_context'] = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log("Error getting additional stats: " . $e->getMessage());
    // Use default values already set
}

// Handle print requests
$printMode = isset($_GET['print']) ? $_GET['print'] : false;
$council = isset($_GET['dept']) ? $_GET['dept'] : 'all';
$program = isset($_GET['program']) ? $_GET['program'] : '';
$courseCode = isset($_GET['course_code']) ? $_GET['course_code'] : '';
$yearLevel = isset($_GET['year_level']) ? $_GET['year_level'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Map program codes to full names for report headers
$programNames = [
    // BIT Programs (display as BINDTECH in reports)
    'BIT-Electrical' => [
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in ELECTRICAL TECHNOLOGY',
        'council' => 'BIT'
    ],
    'BIT-Electronics' => [
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in ELECTRONICS TECHNOLOGY',
        'council' => 'BIT'
    ],
    'BIT-Automotive' => [
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in AUTOMOTIVE TECHNOLOGY',
        'council' => 'BIT'
    ],
    'BIT-HVACR' => [
        'full_name' => 'BACHELOR OF INDUSTRIAL TECHNOLOGY Major in HVAC/R TECHNOLOGY',
        'council' => 'BIT'
    ],
    // Computer Studies Programs
    'BSIS' => [
        'full_name' => 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS',
        'council' => 'COMPSTUD'
    ],
    'BSIT' => [
        'full_name' => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
        'council' => 'COMPSTUD'
    ],
    // HBM Programs
    'BSHMCA' => [
        'full_name' => 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT - CULINARY ARTS',
        'council' => 'HBM'
    ],
    'BSEntrep' => [
        'full_name' => 'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP',
        'council' => 'HBM'
    ],
    'BSTM' => [
        'full_name' => 'BACHELOR OF SCIENCE IN TOURISM MANAGEMENT',
        'council' => 'HBM'
    ],
    // Education Programs
    'BTLEd-IA' => [
        'full_name' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION - INDUSTRIAL ARTS',
        'council' => 'EDUCATION'
    ],
    'BTLEd-HE' => [
        'full_name' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION - HOME ECONOMICS',
        'council' => 'EDUCATION'
    ],
    'BSED-Science' => [
        'full_name' => 'BACHELOR OF SECONDARY EDUCATION - SCIENCE',
        'council' => 'EDUCATION'
    ],
    'BSED-Math' => [
        'full_name' => 'BACHELOR OF SECONDARY EDUCATION - MATHEMATICS',
        'council' => 'EDUCATION'
    ]
];

if ($printMode) {
    // Prepare print data with filters
    $printData = [
        'council' => $council,
        'program' => $program,
        'program_full_name' => isset($programNames[$program]) ? $programNames[$program]['full_name'] : '',
        'course_code' => $courseCode,
        'year_level' => $yearLevel,
        'semester' => $semester,
        'stats' => $stats,
        'allBooks' => $allBooks
    ];
    
    // Print mode - different layout
    include 'print-report.php';
    exit;
}

// Now set page title and include header
$page_title = "LibCollect: Reference Mapping System - Reports";
include '../includes/header.php';
?>

<div class="page-header">
    <h1 class="h2 mb-2">Library Reports & Analytics</h1>
    <p class="mb-0">Generate reports and visualize library data for ISAT U councils</p>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-1"></i>Distribution</h6>
            </div>
            <div class="card-body p-2">
                <div class="chart-container-small">
                    <canvas id="councilChart" width="200" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card">
            <div class="card-header bg-success text-white py-2">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-1"></i>Categories</h6>
            </div>
            <div class="card-body p-2">
                <div class="chart-container-small">
                    <canvas id="categoryChart" width="200" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white py-2">
                <h6 class="mb-0"><i class="fas fa-chart-line me-1"></i>Monthly Trends</h6>
            </div>
            <div class="card-body p-2">
                <div class="chart-container-small">
                    <canvas id="monthlyChart" width="300" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card">
            <div class="card-header bg-warning text-dark py-2">
                <h6 class="mb-0"><i class="fas fa-users me-1"></i>Authors</h6>
            </div>
            <div class="card-body p-2">
                <div class="chart-container-small">
                    <canvas id="authorChart" width="150" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Library Statistics Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-primary"><?php echo isset($stats['total_books']) ? $stats['total_books'] : 0; ?></h3>
                            <p class="text-muted">Total Books</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-success"><?php echo count($councils); ?></h3>
                            <p class="text-muted">Councils</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-warning"><?php echo count($authorData); ?></h3>
                            <p class="text-muted">Active Authors</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-secondary"><?php echo $additionalStats['archived_books']; ?></h3>
                            <p class="text-muted">Archived Books</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Options -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-print me-2"></i>Council Reports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Generate printable reports by council with optional filters</p>
                <div class="d-grid gap-2">
                    <?php foreach ($councils as $code => $council): ?>
                        <div class="council-report-item mb-3">
                            <!-- Changed from button to styled div -->
                            <div class="p-3 border rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="fas fa-file-alt me-2"></i>
                                        <?php echo $council['display']; ?> Report
                                    </span>
                                    <span class="badge bg-primary"><?php echo count($council['books']); ?> books</span>
                                </div>
                            </div>
                            
                            <!-- Filter Options -->
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted d-block mb-2"><i class="fas fa-filter"></i> Filter by:</small>
                                
                                <!-- Program Filter -->
                                <select class="form-select form-select-sm mb-2 program-filter" data-council="<?php echo $code; ?>">
                                    <option value="">All Programs</option>
                                    <?php
                                    $councilPrograms = [];
                                    foreach ($council['books'] as $bookItem) {
                                        if (!empty($bookItem['program'])) {
                                            $program = trim($bookItem['program']);
                                            if (!in_array($program, $councilPrograms)) {
                                                $councilPrograms[] = $program;
                                            }
                                        }
                                    }
                                    sort($councilPrograms);
                                    foreach ($councilPrograms as $program):
                                        // Display with BINDTECH prefix if it's a BIT program
                                        $displayProgram = str_replace('BIT-', 'BINDTECH-', $program);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($program); ?>"><?php echo htmlspecialchars($displayProgram); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Course Code Filter -->
                                <select class="form-select form-select-sm mb-2 course-code-filter" data-council="<?php echo $code; ?>">
                                    <option value="">All Course Codes</option>
                                    <?php
                                    $councilCourseCodes = [];
                                    foreach ($council['books'] as $bookItem) {
                                        if (!empty($bookItem['course_code'])) {
                                            $courseCode = trim($bookItem['course_code']);
                                            if (!in_array($courseCode, $councilCourseCodes)) {
                                                $councilCourseCodes[] = $courseCode;
                                            }
                                        }
                                    }
                                    sort($councilCourseCodes);
                                    foreach ($councilCourseCodes as $courseCode):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($courseCode); ?>"><?php echo htmlspecialchars($courseCode); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Year Level Filter -->
                                <select class="form-select form-select-sm mb-2 year-level-filter" data-council="<?php echo $code; ?>">
                                    <option value="">All Year Levels</option>
                                    <?php
                                    $councilYearLevels = [];
                                    foreach ($council['books'] as $bookItem) {
                                        if (!empty($bookItem['year_level'])) {
                                            $yearLevels = explode(',', $bookItem['year_level']);
                                            foreach ($yearLevels as $yl) {
                                                $yl = trim($yl);
                                                if (!in_array($yl, $councilYearLevels)) {
                                                    $councilYearLevels[] = $yl;
                                                }
                                            }
                                        }
                                    }
                                    $yearOrder = ['First Year', 'Second Year', 'Third Year', 'Fourth Year', 'Graduate'];
                                    $sortedYearLevels = array_intersect($yearOrder, $councilYearLevels);
                                    foreach ($sortedYearLevels as $yearLevel):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($yearLevel); ?>"><?php echo htmlspecialchars($yearLevel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Semester Filter -->
                                <select class="form-select form-select-sm mb-2 semester-filter" data-council="<?php echo $code; ?>">
                                    <option value="">All Semesters</option>
                                    <?php
                                    $councilSemesters = [];
                                    foreach ($council['books'] as $bookItem) {
                                        if (!empty($bookItem['semester'])) {
                                            $semesters = explode(',', $bookItem['semester']);
                                            foreach ($semesters as $sem) {
                                                $sem = trim($sem);
                                                if (!in_array($sem, $councilSemesters)) {
                                                    $councilSemesters[] = $sem;
                                                }
                                            }
                                        }
                                    }
                                    $semesterOrder = ['First Semester', 'Second Semester', 'Summer', 'Midyear'];
                                    $sortedSemesters = array_intersect($semesterOrder, $councilSemesters);
                                    foreach ($sortedSemesters as $semester):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($semester); ?>"><?php echo htmlspecialchars($semester); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="btn-group w-100 mt-2" role="group">
                                <a href="?print=true&dept=<?php echo $code; ?>" 
                                   class="btn btn-sm btn-outline-secondary print-btn" 
                                   data-council="<?php echo $code; ?>"
                                   target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>
                                <a href="export-report.php?type=csv&dept=<?php echo $code; ?>&report=detailed" 
                                   class="btn btn-sm btn-outline-secondary csv-btn"
                                   data-council="<?php echo $code; ?>">
                                    <i class="fas fa-file-csv"></i> CSV
                                </a>
                                <a href="export-report.php?type=excel&dept=<?php echo $code; ?>&report=detailed" 
                                   class="btn btn-sm btn-outline-secondary excel-btn"
                                   data-council="<?php echo $code; ?>">
                                    <i class="fas fa-file-excel"></i> Excel
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Overall Reports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Generate comprehensive library reports</p>
                <div class="d-grid gap-2">
                    <a href="?print=true&dept=all" 
                       class="btn btn-outline-success print-all-btn" 
                       target="_blank">
                        <i class="fas fa-file-pdf me-2"></i>Complete Library Report
                    </a>
                    <a href="?print=true&dept=summary" 
                       class="btn btn-outline-info print-summary-btn" 
                       target="_blank">
                        <i class="fas fa-chart-pie me-2"></i>Summary Statistics Report
                    </a>
                    <button class="btn btn-outline-warning" onclick="generateCustomReport()">
                        <i class="fas fa-cog me-2"></i>Custom Report Generator
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                        <ul class="dropdown-menu w-100">
                            <li><a class="dropdown-item" href="export-report.php?type=csv&dept=all&report=detailed">
                                <i class="fas fa-file-csv me-2"></i>Export All Books (CSV)
                            </a></li>
                            <li><a class="dropdown-item" href="export-report.php?type=excel&dept=all&report=detailed">
                                <i class="fas fa-file-excel me-2"></i>Export All Books (Excel)
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export-report.php?type=csv&dept=all&report=statistics">
                                <i class="fas fa-chart-bar me-2"></i>Export Statistics (CSV)
                            </a></li>
                            <li><a class="dropdown-item" href="export-report.php?type=csv&dept=all&report=archived">
                                <i class="fas fa-archive me-2"></i>Export Archived Books (CSV)
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Preview -->
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Council Overview</h5>
    </div>
    <div class="card-body">
        <!-- Council Statistics -->
        <div class="row mb-4">
            <?php foreach ($councils as $code => $council): ?>
                <div class="col-md-3 mb-3">
                    <div class="card border-start border-4 border-primary">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo $council['display']; ?></h6>
                            <p class="card-text small text-muted"><?php echo $council['name']; ?></p>
                            <div class="d-flex justify-content-between">
                                <span class="text-primary fw-bold"><?php echo count($council['books']); ?></span>
                                <small class="text-muted">books</small>
                            </div>
                            <div class="progress mt-2" style="height: 5px;">
                                <?php 
                                $totalBooks = isset($stats['total_books']) && $stats['total_books'] > 0 ? $stats['total_books'] : 1;
                                $percentage = (count($council['books']) / $totalBooks) * 100;
                                ?>
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-12">
                <h6 class="mb-3">Recent Activity Summary</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Books Added (Last 30 Days)</td>
                                <td><?php echo $additionalStats['recent_additions']; ?></td>
                                <td><?php echo $stats['total_books'] > 0 ? round(($additionalStats['recent_additions'] / $stats['total_books']) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr>
                                <td>Multi-Context Books</td>
                                <td><?php echo $additionalStats['multi_context']; ?></td>
                                <td><?php echo $stats['total_books'] > 0 ? round(($additionalStats['multi_context'] / $stats['total_books']) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr>
                                <td>Archived Books</td>
                                <td><?php echo $additionalStats['archived_books']; ?></td>
                                <td><?php echo ($stats['total_books'] + $additionalStats['archived_books']) > 0 ? round(($additionalStats['archived_books'] / ($stats['total_books'] + $additionalStats['archived_books'])) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr>
                                <td>Unique Courses/Subjects</td>
                                <td><?php echo count($allCourses); ?></td>
                                <td>-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Report Modal -->
<div class="modal fade" id="customReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Custom Report Generator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customReportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-control" name="report_type">
                                <option value="detailed">Detailed Book List</option>
                                <option value="summary">Summary Statistics</option>
                                <option value="category">By Category</option>
                                <option value="author">By Author</option>
                                <option value="archived">Archived Books</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Council Filter</label>
                            <select class="form-control" name="council" id="councilSelect">
                                <option value="all">All Councils</option>
                                <?php foreach ($councils as $code => $council): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $council['display']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course/Subject Filter</label>
                            <select class="form-control" name="course" id="courseSelect">
                                <option value="all">All Courses</option>
                                <?php foreach ($allCourses as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Filter by Course Code and Subject Name</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Range</label>
                            <select class="form-control" name="date_range">
                                <option value="all">All Time</option>
                                <option value="current_year">Current Academic Year</option>
                                <option value="last_year">Previous Academic Year</option>
                                <option value="last_6_months">Last 6 Months</option>
                                <option value="last_30_days">Last 30 Days</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-control" name="sort_by">
                                <option value="title">Title (A-Z)</option>
                                <option value="author">Author (A-Z)</option>
                                <option value="category">Category</option>
                                <option value="subject_course">Course/Subject</option>
                                <option value="created_at">Date Added</option>
                                <option value="publication_year">Publication Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_stats" checked>
                            <label class="form-check-label">Include Statistics Summary</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_descriptions">
                            <label class="form-check-label">Include Book Descriptions</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_charts">
                            <label class="form-check-label">Include Charts in Report</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_context" checked>
                            <label class="form-check-label">Include Subject/Course Context</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateCustomPrint()">
                    <i class="fas fa-print me-1"></i>Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Subject Description Modal -->
<div class="modal fade" id="subjectDescriptionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <div class="w-100">
                    <h5 class="modal-title mb-2" id="modalSubjectTitle">Subject Description</h5>
                    <small id="modalProgress" class="text-white-50">Subject 1 of X</small>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar bg-white" id="descriptionProgressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentSubjectCourseCode">
                <input type="hidden" id="currentSubjectName">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Review and edit the subject description</strong><br>
                    This description will appear in the printed report. You can modify it now or skip to use the default description.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Subject Description</label>
                    <textarea class="form-control" id="subjectDescription" rows="8" 
                              placeholder="Enter the description for this subject that will appear in the printed report..."></textarea>
                    <small class="text-muted">
                        Leave blank to use the default council description. Changes will be saved for future reports.
                    </small>
                </div>
                
                <div class="alert alert-warning mb-0">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Tip:</strong> If you save a description, it will be automatically used for this subject in future reports.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" onclick="skipAllDescriptions()">
                    <i class="fas fa-forward me-1"></i>Skip All & Print
                </button>
                <button type="button" class="btn btn-secondary" onclick="skipCurrentDescription()">
                    <i class="fas fa-arrow-right me-1"></i>Skip This One
                </button>
                <button type="button" class="btn btn-primary" onclick="saveCurrentDescription()">
                    <i class="fas fa-save me-1"></i>Save & Continue
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.stat-item {
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.stat-item h3 {
    margin-bottom: 0.5rem;
    font-weight: bold;
    font-size: 1.5rem;
}

.chart-container-small {
    position: relative;
    height: 400px;
    width: 100%;
}

.progress {
    background-color: #e9ecef;
}

.card-header {
    font-size: 0.9rem;
}

.card-body.p-2 {
    padding: 0.5rem !important;
}

.table-responsive {
    max-height: 300px;
    overflow-y: auto;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart data from PHP
const chartData = <?php echo json_encode($chartData); ?>;

// Council Distribution Pie Chart
const councilCtx = document.getElementById('councilChart').getContext('2d');
new Chart(councilCtx, {
    type: 'pie',
    data: {
        labels: chartData.councils.map(d => d.label),
        datasets: [{
            data: chartData.councils.map(d => d.value),
            backgroundColor: chartData.councils.map(d => d.color),
            borderWidth: 1,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        size: 10
                    },
                    padding: 5
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Category Bar Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: chartData.councils.map(d => d.label),
        datasets: [{
            label: 'Books',
            data: chartData.councils.map(d => d.value),
            backgroundColor: chartData.councils.map(d => d.color + '80'),
            borderColor: chartData.councils.map(d => d.color),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 10
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Monthly Additions Line Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: chartData.monthly_additions.map(d => d.month.split(' ')[0]), // Show only month
        datasets: [{
            label: 'Books',
            data: chartData.monthly_additions.map(d => d.books),
            borderColor: '#007bff',
            backgroundColor: '#007bff20',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 9
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 9
                    },
                    maxRotation: 45
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Top Authors Horizontal Bar Chart
const authorCtx = document.getElementById('authorChart').getContext('2d');
new Chart(authorCtx, {
    type: 'bar',
    data: {
        labels: chartData.author_distribution.slice(0, 5).map(d => {
            const name = d.author.split(' ');
            return name.length > 1 ? name[name.length - 1] : d.author; // Show last name
        }),
        datasets: [{
            label: 'Books',
            data: chartData.author_distribution.slice(0, 5).map(d => d.book_count),
            backgroundColor: '#ffc107',
            borderColor: '#e0a800',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 9
                    }
                }
            },
            y: {
                ticks: {
                    font: {
                        size: 9
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    title: function(context) {
                        if (chartData.author_distribution[context[0].dataIndex]) {
                            return chartData.author_distribution[context[0].dataIndex].author;
                        }
                        return '';
                    },
                    afterLabel: function(context) {
                        if (chartData.author_distribution[context.dataIndex]) {
                            const copies = chartData.author_distribution[context.dataIndex].total_copies;
                            return `Total Copies: ${copies}`;
                        }
                        return '';
                    }
                }
            }
        }
    }
});

function generateCustomReport() {
    new bootstrap.Modal(document.getElementById('customReportModal')).show();
}

function generateCustomPrint() {
    const form = document.getElementById('customReportForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    window.open(`?print=true&custom=true&${params.toString()}`, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('customReportModal')).hide();
}

// Add chart refresh functionality
function refreshCharts() {
    location.reload();
}

// Auto-refresh charts every 5 minutes
setInterval(refreshCharts, 300000);

// Add error handling for missing data
window.addEventListener('load', function() {
    // Check if charts rendered properly
    setTimeout(function() {
        const charts = document.querySelectorAll('canvas');
        charts.forEach(function(canvas) {
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                console.warn('Chart context not found for:', canvas.id);
            }
        });
    }, 1000);
});

// Handle empty data states
function handleEmptyData() {
    if (chartData.councils.every(d => d.value === 0)) {
        document.querySelector('.page-header p').innerHTML = 
            'No books found in the library. Start by adding some books to generate meaningful reports.';
    }
}

handleEmptyData();

// Course filter functionality for council reports
document.querySelectorAll('.program-filter, .course-code-filter, .year-level-filter, .semester-filter').forEach(select => {
    select.addEventListener('change', function() {
        const council = this.dataset.council;
        const councilItem = this.closest('.council-report-item');
        
        // Get all filter values
        const program = councilItem.querySelector('.program-filter').value;
        const courseCode = councilItem.querySelector('.course-code-filter').value;
        const yearLevel = councilItem.querySelector('.year-level-filter').value;
        const semester = councilItem.querySelector('.semester-filter').value;
        
        // Get all export/print buttons
        const printBtn = councilItem.querySelector('.print-btn');
        const csvBtn = councilItem.querySelector('.csv-btn');
        const excelBtn = councilItem.querySelector('.excel-btn');
        
        // Build URL parameters
        let params = `dept=${council}`;
        
        if (program) {
            params += `&program=${encodeURIComponent(program)}`;
        }
        if (courseCode) {
            params += `&course_code=${encodeURIComponent(courseCode)}`;
        }
        if (yearLevel) {
            params += `&year_level=${encodeURIComponent(yearLevel)}`;
        }
        if (semester) {
            params += `&semester=${encodeURIComponent(semester)}`;
        }
        
        // Update button URLs
        printBtn.href = `?print=true&${params}`;
        csvBtn.href = `export-report.php?type=csv&${params}&report=detailed`;
        excelBtn.href = `export-report.php?type=excel&${params}&report=detailed`;
    });
});

// Subject descriptions management before printing
let subjectsForPrint = [];
let currentDescriptionIndex = 0;
let printParams = {};

// Override print button clicks to show description modal first
document.addEventListener('DOMContentLoaded', function() {
    // Handle council print buttons
    document.querySelectorAll('.print-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            const url = new URL(window.location.origin + window.location.pathname + href.substring(href.indexOf('?')));
            printParams = Object.fromEntries(url.searchParams);
            
            // Fetch subjects for this council/filter combination
            fetchSubjectsForPrint(printParams);
        });
    });
    
    // Handle "Complete Library Report" button
    const printAllBtn = document.querySelector('.print-all-btn');
    if (printAllBtn) {
        printAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            printParams = { print: 'true', dept: 'all' };
            fetchSubjectsForPrint(printParams);
        });
    }
    
    // Handle "Summary Statistics Report" button
    const printSummaryBtn = document.querySelector('.print-summary-btn');
    if (printSummaryBtn) {
        printSummaryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Summary doesn't need descriptions, print directly
            window.open(this.href, '_blank');
        });
    }
});

function fetchSubjectsForPrint(params) {
    // Show loading
    const loadingToast = document.createElement('div');
    loadingToast.className = 'toast show position-fixed bottom-0 end-0 m-3 bg-primary text-white';
    loadingToast.style.zIndex = '9999';
    loadingToast.innerHTML = '<div class="toast-body"><i class="fas fa-spinner fa-spin me-2"></i>Loading subjects...</div>';
    document.body.appendChild(loadingToast);
    
    // Fetch subjects via AJAX
    const queryString = new URLSearchParams(params).toString();
    fetch(`../api/get-subjects-for-print.php?${queryString}`)
        .then(response => response.json())
        .then(data => {
            document.body.removeChild(loadingToast);
            subjectsForPrint = data.subjects;
            
            if (subjectsForPrint.length === 0) {
                alert('No subjects found for the selected filters.');
                return;
            }
            
            currentDescriptionIndex = 0;
            showDescriptionModal();
        })
        .catch(error => {
            document.body.removeChild(loadingToast);
            console.error('Error:', error);
            if (confirm('Error loading subjects. Do you want to print without description review?')) {
                proceedToPrint();
            }
        });
}

function showDescriptionModal() {
    if (currentDescriptionIndex >= subjectsForPrint.length) {
        // All descriptions reviewed, proceed to print
        saveDescriptionsAndPrint();
        return;
    }
    
    const subject = subjectsForPrint[currentDescriptionIndex];
    const modal = new bootstrap.Modal(document.getElementById('subjectDescriptionModal'));
    
    // Populate modal
    document.getElementById('modalSubjectTitle').textContent = 
        `${subject.course_code} - ${subject.subject_name}`;
    document.getElementById('modalProgress').textContent = 
        `Subject ${currentDescriptionIndex + 1} of ${subjectsForPrint.length}`;
    document.getElementById('subjectDescription').value = subject.description || '';
    document.getElementById('currentSubjectCourseCode').value = subject.course_code;
    document.getElementById('currentSubjectName').value = subject.subject_name;
    
    // Update progress bar
    const progress = ((currentDescriptionIndex + 1) / subjectsForPrint.length) * 100;
    document.getElementById('descriptionProgressBar').style.width = progress + '%';
    
    modal.show();
}

function saveCurrentDescription() {
    const description = document.getElementById('subjectDescription').value.trim();
    const courseCode = document.getElementById('currentSubjectCourseCode').value;
    const subjectName = document.getElementById('currentSubjectName').value;
    
    // Update in our array
    subjectsForPrint[currentDescriptionIndex].description = description;
    
    // Move to next subject
    currentDescriptionIndex++;
    
    // Close current modal
    bootstrap.Modal.getInstance(document.getElementById('subjectDescriptionModal')).hide();
    
    // Show next or finish
    setTimeout(() => showDescriptionModal(), 300);
}

function skipCurrentDescription() {
    currentDescriptionIndex++;
    bootstrap.Modal.getInstance(document.getElementById('subjectDescriptionModal')).hide();
    setTimeout(() => showDescriptionModal(), 300);
}

function skipAllDescriptions() {
    bootstrap.Modal.getInstance(document.getElementById('subjectDescriptionModal')).hide();
    proceedToPrint();
}

function saveDescriptionsAndPrint() {
    // Save all descriptions to database via AJAX
    const descriptionsToSave = subjectsForPrint.filter(s => s.description && s.description.trim() !== '');
    
    if (descriptionsToSave.length > 0) {
        // Show saving toast
        const savingToast = document.createElement('div');
        savingToast.className = 'toast show position-fixed bottom-0 end-0 m-3 bg-success text-white';
        savingToast.style.zIndex = '9999';
        savingToast.innerHTML = '<div class="toast-body"><i class="fas fa-spinner fa-spin me-2"></i>Saving descriptions...</div>';
        document.body.appendChild(savingToast);
        
        fetch('../api/save-descriptions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ descriptions: descriptionsToSave })
        })
        .then(response => response.json())
        .then(data => {
            document.body.removeChild(savingToast);
            console.log('Descriptions saved:', data);
            
            // Show success toast
            const successToast = document.createElement('div');
            successToast.className = 'toast show position-fixed bottom-0 end-0 m-3 bg-success text-white';
            successToast.style.zIndex = '9999';
            successToast.innerHTML = '<div class="toast-body"><i class="fas fa-check me-2"></i>Descriptions saved successfully!</div>';
            document.body.appendChild(successToast);
            setTimeout(() => document.body.removeChild(successToast), 2000);
            
            proceedToPrint();
        })
        .catch(error => {
            if (document.body.contains(savingToast)) {
                document.body.removeChild(savingToast);
            }
            console.error('Error saving descriptions:', error);
            // Proceed anyway
            proceedToPrint();
        });
    } else {
        proceedToPrint();
    }
}

function proceedToPrint() {
    const queryString = new URLSearchParams(printParams).toString();
    window.open(`?${queryString}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
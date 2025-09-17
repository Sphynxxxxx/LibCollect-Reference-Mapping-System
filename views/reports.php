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

// Get report data with proper error handling
try {
    $stats = $book->getBookStats();
    $allBooks = $book->getAllBooks();
} catch (Exception $e) {
    // Fallback to direct database queries if Book class methods fail
    $statsQuery = "SELECT 
        COUNT(*) as total_books,
        SUM(quantity) as total_copies,
        COUNT(DISTINCT author) as unique_authors
        FROM books";
    $stmt = $pdo->query($statsQuery);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $allBooksQuery = "SELECT * FROM books ORDER BY created_at DESC";
    $stmt = $pdo->query($allBooksQuery);
    $allBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process department data - updated to match your database categories
$departments = [
    'BIT' => ['name' => 'Bachelor of Industrial Technology', 'books' => []],
    'EDUCATION' => ['name' => 'Education Department', 'books' => []],
    'HBM' => ['name' => 'Hotel and Business Management', 'books' => []],
    'COMPSTUD' => ['name' => 'Computer Studies', 'books' => []]
];

// Group books by department - handle multi-context categories
foreach ($allBooks as $bookItem) {
    $categories = explode(',', $bookItem['category']);
    foreach ($categories as $category) {
        $category = trim($category);
        if (isset($departments[$category])) {
            $departments[$category]['books'][] = $bookItem;
        }
    }
}

// Prepare chart data
$chartData = [
    'departments' => [],
    'monthly_additions' => [],
    'author_distribution' => []
];

// Department distribution for pie chart
foreach ($departments as $code => $dept) {
    $chartData['departments'][] = [
        'label' => $dept['name'],
        'value' => count($dept['books']),
        'color' => match($code) {
            'BIT' => '#ffc107',
            'EDUCATION' => '#0d6efd',
            'HBM' => '#dc3545',
            'COMPSTUD' => '#212529'
        }
    ];
}

// Monthly additions for line chart - using actual database data
$monthlyQuery = "SELECT 
    DATE_FORMAT(created_at, '%b %Y') as month,
    DATE_FORMAT(created_at, '%Y-%m') as sort_key,
    COUNT(*) as books 
    FROM books 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY sort_key ASC";

try {
    $stmt = $pdo->query($monthlyQuery);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
} catch (Exception $e) {
    // Fallback to dummy data if query fails
    $monthlyData = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-$i months"));
        $monthlyData[] = [
            'month' => $month,
            'books' => rand(0, 10)
        ];
    }
    $chartData['monthly_additions'] = $monthlyData;
}

// Author distribution (top 10 authors) - handle multi-context books properly
$authorQuery = "SELECT 
    author, 
    COUNT(*) as book_count,
    SUM(quantity) as total_copies
    FROM books 
    GROUP BY author 
    ORDER BY book_count DESC 
    LIMIT 10";

try {
    $stmt = $pdo->query($authorQuery);
    $authorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $chartData['author_distribution'] = $authorData;
} catch (Exception $e) {
    $chartData['author_distribution'] = [];
}

// Get additional statistics
$additionalStats = [];
try {
    // Get archived books count
    $archivedQuery = "SELECT COUNT(*) as archived_books FROM archived_books";
    $stmt = $pdo->query($archivedQuery);
    $additionalStats['archived_books'] = $stmt->fetchColumn();
    
    // Get recent additions (last 30 days)
    $recentQuery = "SELECT COUNT(*) as recent_additions FROM books WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $pdo->query($recentQuery);
    $additionalStats['recent_additions'] = $stmt->fetchColumn();
    
    // Get multi-context books count
    $multiContextQuery = "SELECT COUNT(*) as multi_context FROM books WHERE is_multi_context = 1";
    $stmt = $pdo->query($multiContextQuery);
    $additionalStats['multi_context'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $additionalStats = [
        'archived_books' => 0,
        'recent_additions' => 0,
        'multi_context' => 0
    ];
}

// Handle print requests
$printMode = isset($_GET['print']) ? $_GET['print'] : false;
$department = isset($_GET['dept']) ? $_GET['dept'] : 'all';

if ($printMode) {
    // Print mode - different layout
    include 'print-report.php';
    exit;
}

// Now set page title and include header
$page_title = "Reports - LibCollect: Reference Mapping System";
include '../includes/header.php';
?>

<div class="page-header">
    <h1 class="h2 mb-2">Library Reports & Analytics</h1>
    <p class="mb-0">Generate reports and visualize library data for ISAT U departments</p>
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
                    <canvas id="departmentChart" width="200" height="200"></canvas>
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
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-primary"><?php echo isset($stats['total_books']) ? $stats['total_books'] : 0; ?></h3>
                            <p class="text-muted">Total Books</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-success"><?php echo count($departments); ?></h3>
                            <p class="text-muted">Departments</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-info"><?php echo isset($stats['total_copies']) ? $stats['total_copies'] : 0; ?></h3>
                            <p class="text-muted">Total Copies</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-warning"><?php echo count($authorData); ?></h3>
                            <p class="text-muted">Active Authors</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-secondary"><?php echo $additionalStats['archived_books']; ?></h3>
                            <p class="text-muted">Archived Books</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-item">
                            <h3 class="text-primary"><?php echo $additionalStats['multi_context']; ?></h3>
                            <p class="text-muted">Multi-Context</p>
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
                <h5 class="mb-0"><i class="fas fa-print me-2"></i>Department Reports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Generate printable reports by department</p>
                <div class="d-grid gap-2">
                    <?php foreach ($departments as $code => $dept): ?>
                        <a href="?print=true&dept=<?php echo $code; ?>" 
                           class="btn btn-outline-primary text-start" 
                           target="_blank">
                            <i class="fas fa-file-alt me-2"></i>
                            <?php echo $dept['name']; ?> Report
                            <span class="badge bg-primary float-end"><?php echo count($dept['books']); ?> books</span>
                        </a>
                        <div class="btn-group w-100 mt-1" role="group">
                            <a href="export-report.php?type=csv&dept=<?php echo $code; ?>&report=detailed" 
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-csv"></i> CSV
                            </a>
                            <a href="export-report.php?type=excel&dept=<?php echo $code; ?>&report=detailed" 
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
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
                       class="btn btn-outline-success" 
                       target="_blank">
                        <i class="fas fa-file-pdf me-2"></i>Complete Library Report
                    </a>
                    <a href="?print=true&dept=summary" 
                       class="btn btn-outline-info" 
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
        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Department Overview</h5>
    </div>
    <div class="card-body">
        <!-- Department Statistics -->
        <div class="row mb-4">
            <?php foreach ($departments as $code => $dept): ?>
                <div class="col-md-3 mb-3">
                    <div class="card border-start border-4 border-primary">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo $code; ?></h6>
                            <p class="card-text small text-muted"><?php echo $dept['name']; ?></p>
                            <div class="d-flex justify-content-between">
                                <span class="text-primary fw-bold"><?php echo count($dept['books']); ?></span>
                                <small class="text-muted">books</small>
                            </div>
                            <div class="progress mt-2" style="height: 5px;">
                                <?php 
                                $totalBooks = isset($stats['total_books']) && $stats['total_books'] > 0 ? $stats['total_books'] : 1;
                                $percentage = (count($dept['books']) / $totalBooks) * 100;
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
                            <label class="form-label">Department Filter</label>
                            <select class="form-control" name="department">
                                <option value="all">All Departments</option>
                                <?php foreach ($departments as $code => $dept): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $dept['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-control" name="sort_by">
                                <option value="title">Title (A-Z)</option>
                                <option value="author">Author (A-Z)</option>
                                <option value="category">Category</option>
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
                            <input class="form-check-input" type="checkbox" name="include_context">
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

// Department Distribution Pie Chart
const departmentCtx = document.getElementById('departmentChart').getContext('2d');
new Chart(departmentCtx, {
    type: 'pie',
    data: {
        labels: ['BIT', 'Education', 'HBM', 'ComStud'],
        datasets: [{
            data: chartData.departments.map(d => d.value),
            backgroundColor: chartData.departments.map(d => d.color),
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
                        return context.parsed + ' (' + percentage + '%)';
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
        labels: ['BIT', 'Education', 'HBM', 'ComStud'], 
        datasets: [{
            label: 'Books',
            data: chartData.departments.map(d => d.value),
            backgroundColor: chartData.departments.map(d => d.color + '80'),
            borderColor: chartData.departments.map(d => d.color),
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
    if (chartData.departments.every(d => d.value === 0)) {
        document.querySelector('.page-header p').innerHTML = 
            'No books found in the library. Start by adding some books to generate meaningful reports.';
    }
}

handleEmptyData();
</script>

<?php include '../includes/footer.php'; ?>
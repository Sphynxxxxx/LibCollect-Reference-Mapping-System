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

// Get report data
$stats = $book->getBookStats();
$allBooks = $book->getAllBooks();

// Process department data
$departments = [
    'BIT' => ['name' => 'Bachelor of Industrial Technology', 'books' => []],
    'EDUCATION' => ['name' => 'Education Department', 'books' => []],
    'HBM' => ['name' => 'Hotel and Business Management', 'books' => []],
    'COMPSTUD' => ['name' => 'Computer Studies', 'books' => []]
];

// Group books by department
foreach ($allBooks as $bookItem) {
    if (isset($departments[$bookItem['category']])) {
        $departments[$bookItem['category']]['books'][] = $bookItem;
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
            'BIT' => '#007bff',
            'EDUCATION' => '#28a745',
            'HBM' => '#17a2b8',
            'COMPSTUD' => '#ffc107'
        }
    ];
}

// Monthly additions for line chart
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('M Y', strtotime("-$i months"));
    $monthlyData[] = [
        'month' => $month,
        'books' => rand(5, 25) 
    ];
}
$chartData['monthly_additions'] = $monthlyData;

// Author distribution (top 10 authors)
$authorQuery = "SELECT author, COUNT(*) as book_count FROM books GROUP BY author ORDER BY book_count DESC LIMIT 10";
$stmt = $pdo->query($authorQuery);
$authorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartData['author_distribution'] = $authorData;

// Handle print requests
$printMode = isset($_GET['print']) ? $_GET['print'] : false;
$department = isset($_GET['dept']) ? $_GET['dept'] : 'all';

if ($printMode) {
    // Print mode - different layout
    include 'print-report.php';
    exit;
}

// Now set page title and include header
$page_title = "Reports - ISAT U Library Miagao Campus";
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
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-primary"><?php echo $stats['total_books']; ?></h3>
                            <p class="text-muted">Total Books</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-success"><?php echo count($departments); ?></h3>
                            <p class="text-muted">Departments</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-info"><?php echo $stats['total_copies']; ?></h3>
                            <p class="text-muted">Total Copies</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <h3 class="text-warning"><?php echo count($authorData); ?></h3>
                            <p class="text-muted">Active Authors</p>
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
                                <div class="progress-bar" style="width: <?php echo ($stats['total_books'] > 0) ? (count($dept['books']) / $stats['total_books'] * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-control" name="sort_by">
                                <option value="title">Title (A-Z)</option>
                                <option value="author">Author (A-Z)</option>
                                <option value="category">Category</option>
                                <option value="date">Date Added</option>
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
        labels: chartData.departments.map(d => d.label.split(' ')[0]), // Shorter labels
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
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
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
        labels: ['BIT', 'EDUC', 'HBM', 'CS'], // Shortened labels
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
                    stepSize: 5,
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
        labels: chartData.author_distribution.slice(0, 5).map(d => d.author.split(' ').slice(-1)[0]), // Top 5 only
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
                        const fullName = chartData.author_distribution[context[0].dataIndex].author;
                        return fullName;
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
</script>

<?php include '../includes/footer.php'; ?>
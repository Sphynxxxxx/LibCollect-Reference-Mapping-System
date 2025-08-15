<?php
// Handle any operations first, before any output
session_start();
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
    <h1 class="h2 mb-2">Library Reports</h1>
    <p class="mb-0">Generate and print academic reports for ISAT U departments</p>
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
        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Report Preview</h5>
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

<script>
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
</script>

<?php include '../includes/footer.php'; ?>
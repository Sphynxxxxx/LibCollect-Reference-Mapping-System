<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
$isInViews = (basename($currentDir) === 'views');

// Set paths based on current directory
if ($isInViews) {
    $configPath = '../config/database.php';
    $cssPath = '../assets/css/style.css';
    $jsPath = '../assets/js/main.js';
    $sidebarPath = '../includes/sidebar.php';
    $footerPath = '../includes/footer.php';
} else {
    $configPath = 'config/database.php';
    $cssPath = 'assets/css/style.css';
    $jsPath = 'assets/js/main.js';
    $sidebarPath = 'includes/sidebar.php';
    $footerPath = 'includes/footer.php';
}

// Only include database connection if not already included
if (!class_exists('Database')) {
    require_once $configPath;
    $database = new Database();
    $pdo = $database->connect();
}

// Get user information from session
$userName = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

// Use username for display
$displayName = $userName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'ISAT U Library Management System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $cssPath; ?>" rel="stylesheet">
</head>
<body>
    <!-- University Header -->
    <!--<div class="university-header">
        <div class="container-fluid">
            <strong>Iloilo Science and Technology University Miagao Campus</strong> - LibCollect: Reference Mapping System
        </div>
    </div>-->

    <?php include $sidebarPath; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0 text-dark"><?php echo isset($page_title) ? $page_title : 'Library Management System'; ?></h4>
            </div>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted">
                    Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong>
                </span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($displayName); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <div class="text-center">
                                <i class="fas fa-user-circle fa-2x mb-2 text-primary"></i>
                                <div><strong><?php echo htmlspecialchars($fullName); ?></strong></div>
                                <div><small class="text-muted">@<?php echo htmlspecialchars($userName); ?></small></div>
                                <?php if (!empty($userEmail)): ?>
                                    <div><small class="text-muted"><?php echo htmlspecialchars($userEmail); ?></small></div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo $isInViews ? '' : 'views/'; ?>profiles.php">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo $isInViews ? '' : 'views/'; ?>settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo $isInViews ? '../' : ''; ?>auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4">
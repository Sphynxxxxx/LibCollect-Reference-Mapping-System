<?php
$currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
$isInViews = (basename($currentDir) === 'views');

// Set base path for navigation
$basePath = $isInViews ? '../' : '';
$viewsPath = $isInViews ? '' : 'views/';
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $basePath; ?>index.php" class="sidebar-brand">
            <div class="d-flex align-items-center justify-content-center">
                <img src="<?php echo $basePath; ?>assets/images/ISATU Logo.png" alt="ISAT U Logo" class="sidebar-logo">
            </div>
            <div class="sidebar-text">ISAT U Library</div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo $basePath; ?>index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'books.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>books.php">
                    <i class="fas fa-book"></i>
                    <span class="nav-text">Manage Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'add-book.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>add-book.php">
                    <i class="fas fa-plus-circle"></i>
                    <span class="nav-text">Add Book</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>categories.php">
                    <i class="fas fa-layer-group"></i>
                    <span class="nav-text">Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'archives.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>archives.php">
                    <i class="fas fa-archive"></i>
                    <span class="nav-text">Archives</span>
                </a>
            </li>
            <!--<li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'edit-book.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>edit-book.php">
                    <i class="fas fa-edit"></i>
                    <span class="nav-text">Edit Book</span>
                </a>
            </li>-->
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Reports</span>
                </a>
            </li>
            <!--<li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="<?php echo $viewsPath; ?>settings.php">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </li>-->
        </ul>
    </nav>
    <div class="sidebar-footer mt-auto p-3">
        <small class="text-white-50">© 2025 ISAT U</small>
    </div>
</div>

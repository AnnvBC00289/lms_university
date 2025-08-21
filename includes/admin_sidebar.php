<?php
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Dashboard -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="../admin/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
        </ul>

        <!-- User Management -->
        <h6 class="sidebar-heading">
            <i class="fas fa-users me-2"></i>Users
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('users.php'); ?>" href="../admin/users.php">
                    <i class="fas fa-users me-2"></i>All Users
                    <span class="badge bg-secondary ms-auto">1,234</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('students.php'); ?>" href="../admin/students.php">
                    <i class="fas fa-user-graduate me-2"></i>Students
                    <span class="badge bg-info ms-auto">987</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('instructors.php'); ?>" href="../admin/instructors.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Instructors
                    <span class="badge bg-success ms-auto">45</span>
                </a>
            </li>
        </ul>

        <!-- Academic -->
        <h6 class="sidebar-heading">
            <i class="fas fa-graduation-cap me-2"></i>Academic
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('courses.php'); ?>" href="../admin/courses.php">
                    <i class="fas fa-book me-2"></i>Courses
                    <span class="badge bg-primary ms-auto">156</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('enrollments.php'); ?>" href="../admin/enrollments.php">
                    <i class="fas fa-user-plus me-2"></i>Enrollments
                    <span class="badge bg-info ms-auto">2,345</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('departments.php'); ?>" href="../admin/departments.php">
                    <i class="fas fa-building me-2"></i>Departments
                </a>
            </li>
        </ul>

        <!-- Analytics -->
        <h6 class="sidebar-heading">
            <i class="fas fa-chart-bar me-2"></i>Analytics
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('analytics.php'); ?>" href="../admin/analytics.php">
                    <i class="fas fa-chart-line me-2"></i>System Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('reports.php'); ?>" href="../admin/reports.php">
                    <i class="fas fa-file-chart me-2"></i>Reports
                </a>
            </li>
        </ul>

        <!-- System -->
        <h6 class="sidebar-heading">
            <i class="fas fa-cog me-2"></i>System
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('settings.php'); ?>" href="../admin/settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('system_logs.php'); ?>" href="../admin/system_logs.php">
                    <i class="fas fa-list-alt me-2"></i>System Logs
                </a>
            </li>
        </ul>
    </div>
</nav>
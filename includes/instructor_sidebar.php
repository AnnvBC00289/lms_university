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
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="../instructor/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
        </ul>

        <!-- Courses -->
        <h6 class="sidebar-heading">
            <i class="fas fa-book me-2"></i>Courses
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('courses.php'); ?>" href="../instructor/courses.php">
                    <i class="fas fa-book me-2"></i>My Courses
                    <span class="badge bg-primary ms-auto">8</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('course_create.php'); ?>" href="../instructor/course_create.php">
                    <i class="fas fa-plus me-2"></i>Create Course
                </a>
            </li>
        </ul>

        <!-- Content -->
        <h6 class="sidebar-heading">
            <i class="fas fa-folder me-2"></i>Content
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('materials.php'); ?>" href="../instructor/materials.php">
                    <i class="fas fa-file-alt me-2"></i>Materials
                    <span class="badge bg-info ms-auto">24</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('assignments.php'); ?>" href="../instructor/assignments.php">
                    <i class="fas fa-tasks me-2"></i>Assignments
                    <span class="badge bg-warning ms-auto">12</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('quizzes.php'); ?>" href="../instructor/quizzes.php">
                    <i class="fas fa-question-circle me-2"></i>Quizzes
                    <span class="badge bg-secondary ms-auto">6</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('quiz_create.php'); ?>" href="../instructor/quiz_create.php">
                    <i class="fas fa-plus-circle me-2"></i>Create Quiz
                    <span class="badge bg-info ms-auto">New</span>
                </a>
            </li>
        </ul>

        <!-- Students -->
        <h6 class="sidebar-heading">
            <i class="fas fa-user-graduate me-2"></i>Students
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('students.php'); ?>" href="../instructor/students.php">
                    <i class="fas fa-users me-2"></i>My Students
                    <span class="badge bg-info ms-auto">156</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('assignment_grade.php'); ?>" href="../instructor/assignment_grade.php">
                    <i class="fas fa-edit me-2"></i>Grade Assignments
                    <span class="badge bg-warning ms-auto">12</span>
                </a>
            </li>
        </ul>

        <!-- Communication -->
        <h6 class="sidebar-heading">
            <i class="fas fa-comments me-2"></i>Communication
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('messages.php'); ?>" href="../instructor/messages.php">
                    <i class="fas fa-envelope me-2"></i>Messages
                    <span class="badge bg-danger ms-auto">7</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('forums.php'); ?>" href="../forum/index.php">
                    <i class="fas fa-comments-dollar me-2"></i>Forums
                </a>
            </li>
        </ul>

        <!-- Reports -->
        <h6 class="sidebar-heading">
            <i class="fas fa-chart-bar me-2"></i>Reports
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('reports.php'); ?>" href="../instructor/reports.php">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
            </li>
        </ul>
    </div>
</nav>
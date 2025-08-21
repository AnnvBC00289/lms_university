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
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="../student/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
        </ul>

        <!-- Academic -->
        <h6 class="sidebar-heading">
            <i class="fas fa-graduation-cap me-2"></i>Academic
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('courses.php'); ?>" href="../student/courses.php">
                    <i class="fas fa-book me-2"></i>My Courses
                    <span class="badge bg-primary ms-auto">5</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('schedule.php'); ?>" href="../student/schedule.php">
                    <i class="fas fa-calendar me-2"></i>Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('materials.php'); ?>" href="../student/materials.php">
                    <i class="fas fa-file-alt me-2"></i>Materials
                </a>
            </li>
        </ul>

        <!-- Assignments -->
        <h6 class="sidebar-heading">
            <i class="fas fa-tasks me-2"></i>Assignments
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('assignments.php'); ?>" href="../student/assignments.php">
                    <i class="fas fa-tasks me-2"></i>All Assignments
                    <span class="badge bg-warning ms-auto">3</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('assignment_submit.php'); ?>" href="../student/assignment_submit.php">
                    <i class="fas fa-upload me-2"></i>Submit Assignment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('quizzes.php'); ?>" href="../student/quizzes.php">
                    <i class="fas fa-question-circle me-2"></i>Quizzes
                    <span class="badge bg-info ms-auto">2</span>
                </a>
            </li>
        </ul>

        <!-- Progress -->
        <h6 class="sidebar-heading">
            <i class="fas fa-chart-line me-2"></i>Progress
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('grades.php'); ?>" href="../student/grades.php">
                    <i class="fas fa-star me-2"></i>My Grades
                    <span class="badge bg-success ms-auto">GPA: 3.8</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('progress.php'); ?>" href="../student/progress.php">
                    <i class="fas fa-chart-bar me-2"></i>Progress
                    <span class="badge bg-info ms-auto">New</span>
                </a>
            </li>
        </ul>

        <!-- Communication -->
        <h6 class="sidebar-heading">
            <i class="fas fa-comments me-2"></i>Communication
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('messages.php'); ?>" href="../messages/inbox.php">
                    <i class="fas fa-envelope me-2"></i>Messages
                    <span class="badge bg-danger ms-auto">2</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('forums.php'); ?>" href="../forum/index.php">
                    <i class="fas fa-comments-dollar me-2"></i>Forums
                </a>
            </li>
        </ul>

        <!-- Account -->
        <h6 class="sidebar-heading">
            <i class="fas fa-user me-2"></i>Account
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('profile.php'); ?>" href="../profile.php">
                    <i class="fas fa-user-edit me-2"></i>Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('preferences.php'); ?>" href="../student/preferences.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
        </ul>
    </div>
</nav>
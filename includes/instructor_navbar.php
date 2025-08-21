<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = isset($_SESSION['last_name']) ? $_SESSION['last_name'] : 'Instructor';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="../instructor/dashboard.php">
            <i class="fas fa-university me-2"></i>LMS Instructor
            <span class="badge bg-light text-success ms-2">Instructor</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Search -->
            <form class="d-flex me-auto ms-3">
                <input class="form-control form-control-sm" type="search" placeholder="Search..." style="width: 250px;">
            </form>
            
            <!-- Navigation Items -->
            <ul class="navbar-nav">
                <!-- Quick Create -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ms-1">Create</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../instructor/course_create.php"><i class="fas fa-book-plus me-2"></i>Course</a></li>
                        <li><a class="dropdown-item" href="../instructor/assignment_create.php"><i class="fas fa-tasks me-2"></i>Assignment</a></li>
                        <li><a class="dropdown-item" href="../instructor/material_upload.php"><i class="fas fa-upload me-2"></i>Material</a></li>
                    </ul>
                </li>
                
                <!-- Grading -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge bg-warning text-dark small">12</span>
                        <span class="d-none d-lg-inline ms-1">Grade</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">Pending Grading</h6></li>
                        <li><a class="dropdown-item small" href="../instructor/assignment_grade.php?id=1"><i class="fas fa-file-alt text-primary me-2"></i>CS101 Assignment (8)</a></li>
                        <li><a class="dropdown-item small" href="../instructor/assignment_grade.php?id=2"><i class="fas fa-question-circle text-info me-2"></i>Math Quiz (4)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../instructor/grading_queue.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge bg-danger small">5</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-comment text-primary me-2"></i>New forum question</a></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-envelope text-info me-2"></i>Student messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../instructor/notifications.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        <span class="d-none d-md-inline"><?php echo $user_name; ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../instructor/students.php"><i class="fas fa-user-graduate me-2"></i>My Students</a></li>
                        <li><a class="dropdown-item" href="../instructor/messages.php"><i class="fas fa-envelope me-2"></i>Messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
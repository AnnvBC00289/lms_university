<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Student';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="../student/dashboard.php">
            <i class="fas fa-university me-2"></i>LMS Student
            <span class="badge bg-light text-primary ms-2">Student</span>
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
                <!-- My Courses -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-book"></i>
                        <span class="d-none d-md-inline ms-1">Courses</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">My Courses</h6></li>
                        <li><a class="dropdown-item small" href="../student/course_view.php?id=1">
                            <i class="fas fa-laptop-code text-primary me-2"></i>CS101 - Due tomorrow
                        </a></li>
                        <li><a class="dropdown-item small" href="../student/course_view.php?id=2">
                            <i class="fas fa-calculator text-success me-2"></i>Math201 - Quiz Friday
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../student/courses.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- Assignments -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-tasks"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge bg-warning text-dark small">5</span>
                        <span class="d-none d-lg-inline ms-1">Tasks</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">Due Soon</h6></li>
                        <li><a class="dropdown-item small" href="../student/assignment_submit.php?id=1">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>CS101 Project - Tomorrow
                        </a></li>
                        <li><a class="dropdown-item small" href="../student/assignment_submit.php?id=2">
                            <i class="fas fa-clock text-warning me-2"></i>Math Quiz - Friday
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../student/assignments.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge bg-danger small">3</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">Updates</h6></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-bullhorn text-info me-2"></i>New announcement</a></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-grade text-success me-2"></i>Grade posted</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../student/notifications.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-graduate me-2"></i>
                        <span class="d-none d-md-inline"><?php echo $user_name; ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../student/grades.php"><i class="fas fa-chart-line me-2"></i>Grades</a></li>
                        <li><a class="dropdown-item" href="../messages/inbox.php"><i class="fas fa-envelope me-2"></i>Messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
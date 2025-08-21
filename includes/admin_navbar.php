<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Admin';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="../admin/dashboard.php">
            <i class="fas fa-university me-2"></i>LMS Admin
            <span class="badge bg-danger ms-2">Admin</span>
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
                <!-- Quick Add -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ms-1">Add</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../admin/user_create.php"><i class="fas fa-user-plus me-2"></i>User</a></li>
                        <li><a class="dropdown-item" href="../admin/course_create.php"><i class="fas fa-book-plus me-2"></i>Course</a></li>
                    </ul>
                </li>
                
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge bg-danger small">3</span>
                    </a>
                    <ul class="dropdown-menu" style="width: 280px;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-user text-info me-2"></i>New user registrations</a></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-chart-line text-success me-2"></i>Report ready</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="../admin/notifications.php">View All</a></li>
                    </ul>
                </li>
                
                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-2"></i>
                        <span class="d-none d-md-inline"><?php echo $user_name; ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../admin/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
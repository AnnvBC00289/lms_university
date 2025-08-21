<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get system statistics
$stats = [];

// Total users by role
$query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($user_stats as $stat) {
    $stats[$stat['role']] = $stat['count'];
}

// Total courses
$query = "SELECT COUNT(*) as count FROM courses";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total enrollments
$query = "SELECT COUNT(*) as count FROM enrollments WHERE status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent activities
$query = "SELECT 'user_registered' as type, CONCAT(first_name, ' ', last_name) as description, created_at as date
          FROM users 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'course_created' as type, title as description, created_at as date
          FROM courses 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'enrollment' as type, CONCAT('Student enrolled in course') as description, enrollment_date as date
          FROM enrollments 
          WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY date DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Course enrollment data for chart
$query = "SELECT c.title, COUNT(e.id) as enrollments
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
          GROUP BY c.id, c.title
          ORDER BY enrollments DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$course_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly user registrations
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
          FROM users
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --secondary: #f59e0b;
            --accent: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
        }

        .sidebar {
            background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%) !important;
            min-height: 100vh;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar .sidebar-heading {
            color: rgba(255, 255, 255, 0.6) !important;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        main {
            background: white;
            min-height: 100vh;
            border-radius: 0;
        }

        /* Modern Card Styles for Admin */
        .admin-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--gray-200);
            transition: all 0.4s ease;
            overflow: hidden;
        }

        .admin-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25);
        }

        .card-header-admin {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 2.5rem;
            border-radius: 24px 24px 0 0;
        }

        .stats-card-admin {
            background: white;
            border-radius: 28px;
            padding: 3rem;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--gray-200);
            transition: all 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stats-card-admin:hover {
            transform: translateY(-15px) scale(1.04);
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.25);
        }

        .admin-stats-icon {
            width: 100px;
            height: 100px;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            margin-left: auto;
        }

        .admin-stats-number {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--gray-900);
            line-height: 1;
        }

        .admin-stats-label {
            color: var(--gray-600);
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .welcome-card-admin {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 28px;
            padding: 3.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-card-admin::before {
            content: '';
            position: absolute;
            top: -70%;
            right: -30%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .management-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .management-item {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            border: 1px solid var(--gray-200);
            text-decoration: none;
            display: block;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .management-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }

        .management-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            color: white;
        }

        .page-header-admin {
            background: white;
            border-radius: 28px;
            padding: 3rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .btn-admin {
            border-radius: 16px;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .chart-container-admin {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .timeline-admin {
            position: relative;
            padding-left: 2.5rem;
        }

        .timeline-admin::before {
            content: '';
            position: absolute;
            left: 1.2rem;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
        }

        .timeline-item-admin {
            position: relative;
            margin-bottom: 2.5rem;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
        }

        .timeline-item-admin::before {
            content: '';
            position: absolute;
            left: -3rem;
            top: 2rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            border: 4px solid white;
            box-shadow: 0 0 0 4px var(--primary);
        }

        /* Prevent content overflow */
        .row {
            margin-left: 0;
            margin-right: 0;
        }
        
        .col-lg-4, .col-lg-6, .col-lg-7, .col-lg-8, .col-md-6, .col-xl-3, .col-xl-4, .col-xl-8 {
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (max-width: 768px) {
            .stats-card-admin {
                padding: 2.5rem;
            }
            .welcome-card-admin {
                padding: 3rem;
            }
            .chart-container-admin {
                height: 350px !important;
                padding: 1.5rem;
            }
            .chart-container-admin div {
                height: 250px !important;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header-admin" data-aos="fade-down">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="fw-bold mb-2" style="font-size: 2.5rem; color: var(--gray-900);">
                                <i class="fas fa-tachometer-alt text-primary me-3"></i>Admin Dashboard
                    </h1>
                            <p class="text-muted mb-0" style="font-size: 1.2rem;">Complete system management and analytics</p>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary btn-admin">
                                <i class="fas fa-download me-2"></i>Export Report
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-admin">
                                <i class="fas fa-cog me-2"></i>Settings
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Welcome Section -->
                <div class="row mb-5" data-aos="fade-up">
                    <div class="col-12">
                        <div class="welcome-card-admin">
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="position: relative; z-index: 2;">
                                    <h3 class="fw-bold mb-3">
                                        <i class="fas fa-user-shield me-3"></i>
                                        Welcome, Administrator!
                                    </h3>
                                    <p class="mb-3 opacity-90" style="font-size: 1.2rem;">
                                        Complete system oversight and management tools for the University LMS. 
                                        Monitor all activities, manage users, and ensure smooth operations.
                                    </p>
                                    <div class="d-flex gap-3">
                                        <div class="badge bg-white text-primary px-4 py-3" style="font-size: 0.9rem;">
                                            <i class="fas fa-users me-2"></i>
                                            <?php echo (isset($stats['student']) ? $stats['student'] : 0) + (isset($stats['instructor']) ? $stats['instructor'] : 0); ?> Total Users
                                        </div>
                                        <div class="badge bg-white text-info px-4 py-3" style="font-size: 0.9rem;">
                                            <i class="fas fa-book me-2"></i>
                                            <?php echo $stats['courses']; ?> Courses
                                        </div>
                                        <div class="badge bg-white text-success px-4 py-3" style="font-size: 0.9rem;">
                                            <i class="fas fa-chart-line me-2"></i>
                                            <?php echo $stats['enrollments']; ?> Enrollments
                                        </div>
                                    </div>
                                </div>
                                <div class="d-none d-md-block" style="position: relative; z-index: 2;">
                                    <i class="fas fa-crown" style="font-size: 5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-5">
                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="stats-card-admin">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="admin-stats-label mb-2">Total Students</div>
                                    <div class="admin-stats-number">
                                            <?php echo isset($stats['student']) ? $stats['student'] : 0; ?>
                                    </div>
                                    <small class="text-primary">
                                        <i class="fas fa-graduation-cap me-1"></i>Enrolled learners
                                    </small>
                                    </div>
                                <div class="admin-stats-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="stats-card-admin">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="admin-stats-label mb-2">Total Instructors</div>
                                    <div class="admin-stats-number">
                                            <?php echo isset($stats['instructor']) ? $stats['instructor'] : 0; ?>
                                    </div>
                                    <small class="text-success">
                                        <i class="fas fa-chalkboard-teacher me-1"></i>Teaching staff
                                    </small>
                                    </div>
                                <div class="admin-stats-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="stats-card-admin">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="admin-stats-label mb-2">Total Courses</div>
                                    <div class="admin-stats-number"><?php echo $stats['courses']; ?></div>
                                    <small class="text-info">
                                        <i class="fas fa-book me-1"></i>Available courses
                                    </small>
                                    </div>
                                <div class="admin-stats-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="stats-card-admin">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="admin-stats-label mb-2">Active Enrollments</div>
                                    <div class="admin-stats-number"><?php echo $stats['enrollments']; ?></div>
                                    <small class="text-warning">
                                        <i class="fas fa-users me-1"></i>Current semester
                                    </small>
                                    </div>
                                <div class="admin-stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-5">
                    <!-- Course Enrollments Chart -->
                    <div class="col-xl-8 col-lg-7 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="chart-container-admin" style="height: 450px;">
                            <h6 class="fw-bold mb-4 d-flex align-items-center text-muted">
                                <i class="fas fa-chart-bar me-3"></i>Course Enrollment Analytics
                                </h6>
                            <div style="position: relative; height: 350px;">
                                <canvas id="courseEnrollmentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- User Registration Trend -->
                    <div class="col-xl-4 col-lg-5 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="chart-container-admin" style="height: 450px;">
                            <h6 class="fw-bold mb-4 d-flex align-items-center text-muted">
                                <i class="fas fa-chart-line me-3"></i>Monthly User Growth
                                </h6>
                            <div style="position: relative; height: 350px;">
                                <canvas id="registrationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Tools and Recent Activity -->
                <div class="row">
                    <!-- Quick Management Tools -->
                    <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="admin-card">
                            <div class="card-header-admin">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-tools me-3"></i>System Management
                                </h5>
                                <p class="mb-0 opacity-90 small">Essential administration tools</p>
                            </div>
                            <div class="card-body p-0">
                                <div class="management-card">
                                    <div class="row g-0">
                                        <div class="col-md-6">
                                            <a href="users.php" class="management-item">
                                                <div class="management-icon" style="background: linear-gradient(135deg, #7c3aed, #6d28d9);">
                                                    <i class="fas fa-users"></i>
                                            </div>
                                                <h6 class="fw-bold mb-1">User Management</h6>
                                                <small class="text-muted">Manage students & instructors</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="courses.php" class="management-item">
                                                <div class="management-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                                    <i class="fas fa-book"></i>
                                    </div>
                                                <h6 class="fw-bold mb-1">Course Management</h6>
                                                <small class="text-muted">Oversee all courses</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="analytics.php" class="management-item">
                                                <div class="management-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                                    <i class="fas fa-chart-bar"></i>
                                    </div>
                                                <h6 class="fw-bold mb-1">Analytics Center</h6>
                                                <small class="text-muted">View detailed reports</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="settings.php" class="management-item">
                                                <div class="management-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                                    <i class="fas fa-cog"></i>
                                    </div>
                                                <h6 class="fw-bold mb-1">System Settings</h6>
                                                <small class="text-muted">Configure system</small>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="admin-card">
                            <div class="card-header-admin">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-clock me-3"></i>System Activity
                                </h5>
                                <p class="mb-0 opacity-90 small">Recent system events and activities</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="timeline-admin">
                                    <?php if (empty($recent_activities)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-clock text-muted mb-3" style="font-size: 2rem; opacity: 0.3;"></i>
                                            <p class="text-muted small">No recent activity</p>
                                        </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                            <div class="timeline-item-admin">
                                                <div class="d-flex align-items-start">
                                                    <div class="me-3">
                                                        <div style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: 
                                                        <?php 
                                                        switch($activity['type']) {
                                                            case 'user_registered': echo 'linear-gradient(135deg, #10b981, #059669)'; break;
                                                            case 'course_created': echo 'linear-gradient(135deg, #7c3aed, #6d28d9)'; break;
                                                            case 'enrollment': echo 'linear-gradient(135deg, #06b6d4, #0891b2)'; break;
                                                            default: echo 'linear-gradient(135deg, #64748b, #475569)';
                                                        }
                                                        ?>; color: white;">
                                                            <i class="fas fa-
                                                <?php 
                                                switch($activity['type']) {
                                                                case 'user_registered': echo 'user-plus'; break;
                                                                case 'course_created': echo 'book'; break;
                                                                case 'enrollment': echo 'user-graduate'; break;
                                                                default: echo 'bell';
                                                            }
                                                            ?>"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="fw-semibold mb-1">
                                                    <?php 
                                                    switch($activity['type']) {
                                                        case 'user_registered': echo 'New User Registration'; break;
                                                        case 'course_created': echo 'Course Created'; break;
                                                        case 'enrollment': echo 'New Enrollment'; break;
                                                                default: echo 'System Activity';
                                                    }
                                                    ?>
                                                </h6>
                                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo formatDate($activity['date']); ?>
                                                        </small>
                                                    </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1200,
            once: true,
            offset: 120
        });

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in body
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.7s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);

            // Animate stats numbers
            const statsNumbers = document.querySelectorAll('.admin-stats-number');
            statsNumbers.forEach((stat, index) => {
                const finalNumber = parseInt(stat.textContent);
                let currentNumber = 0;
                const increment = finalNumber / 50;
                
                setTimeout(() => {
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            stat.textContent = finalNumber;
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentNumber);
                        }
                    }, 40);
                }, index * 300);
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.stats-card-admin, .admin-card, .management-item').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (this.classList.contains('stats-card-admin')) {
                    this.style.transform = 'translateY(-15px) scale(1.04)';
                } else if (this.classList.contains('management-item')) {
                    this.style.transform = 'translateY(-8px)';
                } else {
                    this.style.transform = 'translateY(-10px)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Course Enrollment Chart
        const ctx1 = document.getElementById('courseEnrollmentChart').getContext('2d');
        const courseEnrollmentChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($course_enrollments as $course): ?>
                        '<?php echo addslashes($course['title']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Enrollments',
                    data: [
                        <?php foreach ($course_enrollments as $course): ?>
                            <?php echo $course['enrollments']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(124, 58, 237, 0.8)',
                    borderColor: '#7c3aed',
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: 'rgba(124, 58, 237, 1)',
                    hoverBorderColor: '#6d28d9'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 11
                            },
                            maxRotation: 45
                        }
                    }
                }
            }
        });

        // Monthly Registration Chart
        const ctx2 = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($monthly_registrations as $reg): ?>
                        '<?php echo $reg['month']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'New Users',
                    data: [
                        <?php foreach ($monthly_registrations as $reg): ?>
                            <?php echo $reg['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get comprehensive analytics data
$analytics = [];

// User statistics
$query = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as instructors,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
          FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$analytics['users'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Course statistics
$query = "SELECT 
            COUNT(*) as total_courses,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_courses,
            AVG(max_students) as avg_capacity
          FROM courses";
$stmt = $db->prepare($query);
$stmt->execute();
$analytics['courses'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Enrollment statistics
$query = "SELECT 
            COUNT(*) as total_enrollments,
            SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as active_enrollments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
            SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped_enrollments
          FROM enrollments";
$stmt = $db->prepare($query);
$stmt->execute();
$analytics['enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Assignment statistics
$query = "SELECT 
            COUNT(DISTINCT a.id) as total_assignments,
            COUNT(DISTINCT s.id) as total_submissions,
            COUNT(CASE WHEN s.grade IS NOT NULL THEN 1 END) as graded_submissions,
            AVG(s.grade) as avg_grade
          FROM assignments a
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id";
$stmt = $db->prepare($query);
$stmt->execute();
$analytics['assignments'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Quiz statistics
$query = "SELECT 
            COUNT(DISTINCT q.id) as total_quizzes,
            COUNT(DISTINCT qa.id) as total_attempts,
            AVG(qa.score) as avg_score
          FROM quizzes q
          LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id";
$stmt = $db->prepare($query);
$stmt->execute();
$analytics['quizzes'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly user registration trend (last 12 months)
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
          FROM users
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Course enrollment distribution
$query = "SELECT 
            c.title,
            COUNT(e.id) as enrollments
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
          GROUP BY c.id, c.title
          ORDER BY enrollments DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$course_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing courses by average grade
$query = "SELECT 
            c.title,
            AVG(s.grade) as avg_grade,
            COUNT(s.id) as submission_count
          FROM courses c
          JOIN assignments a ON c.id = a.course_id
          JOIN assignment_submissions s ON a.id = s.assignment_id
          WHERE s.grade IS NOT NULL
          GROUP BY c.id, c.title
          HAVING submission_count >= 5
          ORDER BY avg_grade DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$top_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activity summary
$query = "SELECT 
            'User Registration' as activity_type,
            CONCAT(first_name, ' ', last_name, ' registered') as description,
            created_at
          FROM users 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 
            'Course Created' as activity_type,
            CONCAT('Course \"', title, '\" was created') as description,
            created_at
          FROM courses
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 
            'New Enrollment' as activity_type,
            'Student enrolled in course' as description,
            enrollment_date as created_at
          FROM enrollments
          WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY created_at DESC
          LIMIT 15";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --secondary: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
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
            overflow-x: hidden;
        }

        .container-fluid {
            max-width: 100%;
            overflow-x: hidden;
        }

        .page-header {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .chart-container {
                padding: 1rem;
            }
            .chart-wrapper {
                height: 250px;
            }
        }

        .activity-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .activity-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 2rem;
            border: none;
        }

        .activity-item {
            padding: 1rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .activity-item:hover {
            background: #f8fafc;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item .flex-grow-1 {
            min-width: 0;
            overflow: hidden;
        }

        .activity-item h6 {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .activity-item p {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .activity-item {
                padding: 1rem;
            }
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 1rem;
        }

        .performance-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .performance-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .performance-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .performance-item h6 {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 200px;
        }

        @media (max-width: 768px) {
            .performance-item {
                padding: 1rem;
            }
            .performance-item h6 {
                max-width: 150px;
            }
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 20px;
            background: #f1f5f9;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            transition: width 0.5s ease;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 992px) {
            .metric-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
        }

        @media (max-width: 576px) {
            .metric-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .page-header .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .page-header .btn {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div class="mb-3 mb-md-0">
                            <h1 class="fw-bold mb-2" style="color: var(--dark);">
                                <i class="fas fa-chart-line text-primary me-3"></i>Analytics Dashboard
                            </h1>
                            <p class="text-muted mb-0">Comprehensive system analytics and insights</p>
                        </div>
                        <div class="btn-group flex-column flex-md-row">
                            <button class="btn btn-primary mb-2 mb-md-0" onclick="exportReport()">
                                <i class="fas fa-download me-2"></i>Export Report
                            </button>
                            <button class="btn btn-outline-primary" onclick="refreshData()">
                                <i class="fas fa-sync me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics Grid -->
                <div class="metric-grid">
                    <!-- Users Stats -->
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($analytics['users']['total_users']); ?></div>
                        <div class="stats-label">Total Users</div>
                        <small class="text-success">
                            <i class="fas fa-plus me-1"></i><?php echo $analytics['users']['new_users_30d']; ?> new this month
                        </small>
                    </div>

                    <!-- Courses Stats -->
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($analytics['courses']['total_courses']); ?></div>
                        <div class="stats-label">Total Courses</div>
                        <small class="text-info">
                            <i class="fas fa-chart-line me-1"></i><?php echo $analytics['courses']['active_courses']; ?> active courses
                        </small>
                    </div>

                    <!-- Enrollments Stats -->
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($analytics['enrollments']['total_enrollments']); ?></div>
                        <div class="stats-label">Total Enrollments</div>
                        <small class="text-warning">
                            <i class="fas fa-users me-1"></i><?php echo $analytics['enrollments']['active_enrollments']; ?> active
                        </small>
                    </div>

                    <!-- Assignments Stats -->
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2); color: white;">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($analytics['assignments']['total_assignments']); ?></div>
                        <div class="stats-label">Assignments</div>
                        <small class="text-info">
                            <i class="fas fa-percentage me-1"></i><?php echo round($analytics['assignments']['avg_grade'], 1); ?>% avg grade
                        </small>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <!-- User Registration Trend -->
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-chart-line text-primary me-2"></i>User Registration Trend
                            </h5>
                            <div class="chart-wrapper">
                                <canvas id="registrationChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Course Enrollment Distribution -->
                    <div class="col-lg-4 mb-4">
                        <div class="chart-container">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-chart-pie text-primary me-2"></i>User Distribution
                            </h5>
                            <div class="chart-wrapper">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance and Activity Row -->
                <div class="row">
                    <!-- Top Performing Courses -->
                    <div class="col-lg-6 mb-4">
                        <div class="performance-card">
                            <h5 class="fw-bold mb-4">
                                <i class="fas fa-trophy text-warning me-2"></i>Top Performing Courses
                            </h5>
                            <?php if (empty($top_courses)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar text-muted" style="font-size: 2rem; opacity: 0.3;"></i>
                                    <p class="text-muted mt-2">No performance data available</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($top_courses as $index => $course): ?>
                                    <div class="performance-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-semibold mb-0"><?php echo htmlspecialchars($course['title']); ?></h6>
                                            <span class="badge bg-success"><?php echo round($course['avg_grade'], 1); ?>%</span>
                                        </div>
                                        <div class="progress-bar-custom">
                                            <div class="progress-fill" style="width: <?php echo min($course['avg_grade'], 100); ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $course['submission_count']; ?> submissions</small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent System Activity -->
                    <div class="col-lg-6 mb-4">
                        <div class="activity-card">
                            <div class="activity-header">
                                <h5 class="fw-bold mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent System Activity
                                </h5>
                            </div>
                            <div class="activity-body">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-clock text-muted" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <p class="text-muted mt-2">No recent activity</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item d-flex align-items-center">
                                            <div class="activity-icon" style="background: 
                                                <?php 
                                                switch($activity['activity_type']) {
                                                    case 'User Registration': echo 'linear-gradient(135deg, #10b981, #059669)'; break;
                                                    case 'Course Created': echo 'linear-gradient(135deg, #7c3aed, #6d28d9)'; break;
                                                    case 'New Enrollment': echo 'linear-gradient(135deg, #06b6d4, #0891b2)'; break;
                                                    default: echo 'linear-gradient(135deg, #64748b, #475569)';
                                                }
                                                ?>; color: white;">
                                                <i class="fas fa-<?php 
                                                    switch($activity['activity_type']) {
                                                        case 'User Registration': echo 'user-plus'; break;
                                                        case 'Course Created': echo 'book'; break;
                                                        case 'New Enrollment': echo 'user-graduate'; break;
                                                        default: echo 'bell';
                                                    }
                                                ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-semibold mb-1"><?php echo $activity['activity_type']; ?></h6>
                                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                <small class="text-muted"><?php echo formatDate($activity['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Enrollment Chart -->
                <div class="row">
                    <div class="col-12">
                        <div class="chart-container">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-chart-bar text-primary me-2"></i>Course Enrollment Analytics
                            </h5>
                            <div class="chart-wrapper">
                                <canvas id="courseEnrollmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Registration Trend Chart
        const ctx1 = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($monthly_users as $month): ?>
                        '<?php echo $month['month']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'New Users',
                    data: [
                        <?php foreach ($monthly_users as $month): ?>
                            <?php echo $month['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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
                                size: window.innerWidth < 768 ? 10 : 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: window.innerWidth < 768 ? 10 : 12
                            },
                            maxRotation: window.innerWidth < 768 ? 90 : 45
                        }
                    }
                }
            }
        });

        // User Distribution Pie Chart
        const ctx2 = document.getElementById('userDistributionChart').getContext('2d');
        const userDistributionChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Students', 'Instructors', 'Admins'],
                datasets: [{
                    data: [
                        <?php echo $analytics['users']['students']; ?>,
                        <?php echo $analytics['users']['instructors']; ?>,
                        <?php echo $analytics['users']['admins']; ?>
                    ],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Course Enrollment Chart
        const ctx3 = document.getElementById('courseEnrollmentChart').getContext('2d');
        const courseEnrollmentChart = new Chart(ctx3, {
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
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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
                                size: window.innerWidth < 768 ? 10 : 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: window.innerWidth < 768 ? 9 : 11
                            },
                            maxRotation: window.innerWidth < 768 ? 90 : 45
                        }
                    }
                }
            }
        });

        function exportReport() {
            alert('Export functionality will be implemented with PDF generation library.');
        }

        function refreshData() {
            location.reload();
        }

        // Animate numbers on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach((stat, index) => {
                const finalNumber = parseInt(stat.textContent.replace(/,/g, ''));
                let currentNumber = 0;
                const increment = finalNumber / 50;
                
                setTimeout(() => {
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            stat.textContent = finalNumber.toLocaleString();
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentNumber).toLocaleString();
                        }
                    }, 40);
                }, index * 200);
            });
        });
    </script>
</body>
</html>

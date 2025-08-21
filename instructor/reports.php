<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get instructor's courses for filter
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';

// Build date condition
$date_condition = '';
$date_params = [];
switch ($date_filter) {
    case 'week':
        $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'semester':
        $date_condition = "AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)";
        break;
}

// Course filter condition
$course_condition = $course_filter ? "AND c.id = ?" : "";
$course_params = $course_filter ? [$course_filter] : [];

// Get overall statistics
$stats_query = "SELECT 
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT a.id) as total_assignments,
                AVG(s.grade) as avg_grade,
                COUNT(DISTINCT s.id) as total_submissions
                FROM courses c
                LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
                LEFT JOIN assignments a ON c.id = a.course_id
                LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                WHERE c.instructor_id = ? $course_condition";
$stats_params = array_merge([$_SESSION['user_id']], $course_params);
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get course performance data
$performance_query = "SELECT c.id, c.title, c.course_code,
                     COUNT(DISTINCT e.student_id) as enrolled_students,
                     COUNT(DISTINCT a.id) as total_assignments,
                     COUNT(DISTINCT s.id) as total_submissions,
                     AVG(s.grade) as avg_grade,
                     COUNT(CASE WHEN s.grade >= 80 THEN 1 END) as high_performers,
                     COUNT(CASE WHEN s.grade < 60 THEN 1 END) as struggling_students
                     FROM courses c
                     LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
                     LEFT JOIN assignments a ON c.id = a.course_id
                     LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                     WHERE c.instructor_id = ? $course_condition
                     GROUP BY c.id
                     ORDER BY c.title";
$performance_stmt = $db->prepare($performance_query);
$performance_stmt->execute($stats_params);
$course_performance = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment analytics
$assignments_query = "SELECT a.id, a.title, c.course_code,
                     COUNT(s.id) as total_submissions,
                     AVG(s.grade) as avg_grade,
                     MAX(s.grade) as max_grade,
                     MIN(s.grade) as min_grade,
                     COUNT(CASE WHEN s.submitted_at > a.due_date THEN 1 END) as late_submissions,
                     a.max_points, a.due_date, a.created_at
                     FROM assignments a
                     JOIN courses c ON a.course_id = c.id
                     LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                     WHERE c.instructor_id = ? $course_condition
                     GROUP BY a.id
                     ORDER BY a.created_at DESC
                     LIMIT 10";
$assignments_stmt = $db->prepare($assignments_query);
$assignments_stmt->execute($stats_params);
$assignment_analytics = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get student progress data
$students_query = "SELECT u.id, u.first_name, u.last_name, u.email, c.course_code,
                  COUNT(s.id) as assignments_submitted,
                  COUNT(a.id) as total_assignments,
                  AVG(s.grade) as avg_grade,
                  MAX(s.submitted_at) as last_activity,
                  e.enrollment_date
                  FROM users u
                  JOIN enrollments e ON u.id = e.student_id AND e.status = 'enrolled'
                  JOIN courses c ON e.course_id = c.id
                  LEFT JOIN assignments a ON c.id = a.course_id
                  LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = u.id
                  WHERE c.instructor_id = ? $course_condition AND u.role = 'student'
                  GROUP BY u.id, c.id
                  ORDER BY avg_grade DESC";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute($stats_params);
$student_progress = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade distribution data for chart
$grades_query = "SELECT 
                CASE 
                    WHEN s.grade >= 90 THEN 'A (90-100)'
                    WHEN s.grade >= 80 THEN 'B (80-89)'
                    WHEN s.grade >= 70 THEN 'C (70-79)'
                    WHEN s.grade >= 60 THEN 'D (60-69)'
                    ELSE 'F (0-59)'
                END as grade_range,
                COUNT(*) as count
                FROM assignment_submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE c.instructor_id = ? $course_condition AND s.grade IS NOT NULL
                GROUP BY grade_range
                ORDER BY count DESC";
$grades_stmt = $db->prepare($grades_query);
$grades_stmt->execute($stats_params);
$grade_distribution = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get submission trends (last 30 days)
$trends_query = "SELECT DATE(s.submitted_at) as submission_date, COUNT(*) as submissions
                FROM assignment_submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN courses c ON a.course_id = c.id
                WHERE c.instructor_id = ? $course_condition 
                AND s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(s.submitted_at)
                ORDER BY submission_date";
$trends_stmt = $db->prepare($trends_query);
$trends_stmt->execute($stats_params);
$submission_trends = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - University LMS</title>
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
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0891b2;
        }

        body {

        .sidebar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
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
        }

        .reports-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-courses { color: var(--primary); }
        .stat-students { color: #0891b2; }
        .stat-assignments { color: #7c3aed; }
        .stat-grade { color: #f59e0b; }
        .stat-submissions { color: #ef4444; }

        .filters-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 1rem;
        }

        .table-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }

        .table-modern tbody td {
            border: none;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .performance-indicator {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .indicator-excellent {
            background: #dcfce7;
            color: #166534;
        }

        .indicator-good {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .indicator-average {
            background: #fef3c7;
            color: #d97706;
        }

        .indicator-poor {
            background: #fee2e2;
            color: #991b1b;
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Reports Header -->
                <div class="reports-header" data-aos="fade-down">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="fw-bold mb-2">
                                <i class="fas fa-chart-bar me-3"></i>Reports & Analytics
                            </h1>
                            <p class="mb-0 opacity-90">Comprehensive insights into your teaching performance</p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="export-buttons">
                                <button class="btn-export" onclick="exportData('pdf')">
                                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                                </button>
                                <button class="btn-export" onclick="exportData('excel')">
                                    <i class="fas fa-file-excel me-2"></i>Export Excel
                                </button>
                                <button class="btn-export" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-card" data-aos="fade-up">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date_range" class="form-label">Time Period</label>
                            <select class="form-select" id="date_range" name="date_range">
                                <option value="all" <?php echo ($date_filter == 'all') ? 'selected' : ''; ?>>All Time</option>
                                <option value="week" <?php echo ($date_filter == 'week') ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo ($date_filter == 'month') ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="semester" <?php echo ($date_filter == 'semester') ? 'selected' : ''; ?>>This Semester</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i>Apply Filters
                            </button>
                            <a href="reports.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Overall Statistics -->
                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-number stat-courses"><?php echo $stats['total_courses'] ?: 0; ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number stat-students"><?php echo $stats['total_students'] ?: 0; ?></div>
                        <div class="stat-label">Students Enrolled</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number stat-assignments"><?php echo $stats['total_assignments'] ?: 0; ?></div>
                        <div class="stat-label">Assignments Created</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number stat-submissions"><?php echo $stats['total_submissions'] ?: 0; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number stat-grade"><?php echo $stats['avg_grade'] ? round($stats['avg_grade'], 1) : 'N/A'; ?></div>
                        <div class="stat-label">Average Grade</div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <!-- Grade Distribution Chart -->
                    <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="chart-card">
                            <h5 class="fw-bold text-success mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Grade Distribution
                            </h5>
                            <div class="chart-container">
                                <canvas id="gradeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Trends Chart -->
                    <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="chart-card">
                            <h5 class="fw-bold text-success mb-0">
                                <i class="fas fa-chart-line me-2"></i>Submission Trends (Last 30 Days)
                            </h5>
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Performance Table -->
                <div class="table-card" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-success mb-0">
                            <i class="fas fa-book me-2"></i>Course Performance Analysis
                        </h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Students</th>
                                    <th>Assignments</th>
                                    <th>Submissions</th>
                                    <th>Avg Grade</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_performance as $course): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($course['title']); ?></small>
                                        </td>
                                        <td><?php echo $course['enrolled_students']; ?></td>
                                        <td><?php echo $course['total_assignments']; ?></td>
                                        <td><?php echo $course['total_submissions']; ?></td>
                                        <td><?php echo $course['avg_grade'] ? round($course['avg_grade'], 1) : 'N/A'; ?></td>
                                        <td>
                                            <?php
                                            $avg_grade = $course['avg_grade'];
                                            if ($avg_grade >= 85) {
                                                echo '<span class="performance-indicator indicator-excellent">Excellent</span>';
                                            } elseif ($avg_grade >= 75) {
                                                echo '<span class="performance-indicator indicator-good">Good</span>';
                                            } elseif ($avg_grade >= 60) {
                                                echo '<span class="performance-indicator indicator-average">Average</span>';
                                            } else {
                                                echo '<span class="performance-indicator indicator-poor">Needs Attention</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Assignment Analytics Table -->
                <div class="table-card" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-success mb-0">
                            <i class="fas fa-tasks me-2"></i>Assignment Analytics
                        </h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Course</th>
                                    <th>Submissions</th>
                                    <th>Avg Grade</th>
                                    <th>Range</th>
                                    <th>Late Submissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignment_analytics as $assignment): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                            <small class="text-muted">Max: <?php echo $assignment['max_points']; ?> pts</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                        <td><?php echo $assignment['total_submissions']; ?></td>
                                        <td><?php echo $assignment['avg_grade'] ? round($assignment['avg_grade'], 1) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($assignment['min_grade'] !== null && $assignment['max_grade'] !== null): ?>
                                                <?php echo $assignment['min_grade']; ?> - <?php echo $assignment['max_grade']; ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['late_submissions'] > 0): ?>
                                                <span class="text-danger fw-bold"><?php echo $assignment['late_submissions']; ?></span>
                                            <?php else: ?>
                                                <span class="text-success">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Student Progress Table -->
                <div class="table-card" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-success mb-0">
                            <i class="fas fa-users me-2"></i>Student Progress Overview
                        </h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Progress</th>
                                    <th>Avg Grade</th>
                                    <th>Last Activity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($student_progress, 0, 15) as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['course_code']); ?></td>
                                        <td>
                                            <?php 
                                            $progress = $student['total_assignments'] > 0 ? 
                                                round(($student['assignments_submitted'] / $student['total_assignments']) * 100) : 0;
                                            ?>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo $student['assignments_submitted']; ?>/<?php echo $student['total_assignments']; ?> (<?php echo $progress; ?>%)</small>
                                        </td>
                                        <td><?php echo $student['avg_grade'] ? round($student['avg_grade'], 1) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($student['last_activity']): ?>
                                                <?php echo date('M j, Y', strtotime($student['last_activity'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No activity</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $avg_grade = $student['avg_grade'];
                                            if ($avg_grade >= 80) {
                                                echo '<span class="performance-indicator indicator-excellent">Excellent</span>';
                                            } elseif ($avg_grade >= 70) {
                                                echo '<span class="performance-indicator indicator-good">Good</span>';
                                            } elseif ($avg_grade >= 60) {
                                                echo '<span class="performance-indicator indicator-average">Average</span>';
                                            } else {
                                                echo '<span class="performance-indicator indicator-poor">At Risk</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($grade_distribution, 'grade_range')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($grade_distribution, 'count')); ?>,
                    backgroundColor: [
                        '#059669',
                        '#0891b2', 
                        '#7c3aed',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter'
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Submission Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($submission_trends, 'submission_date')); ?>,
                datasets: [{
                    label: 'Submissions',
                    data: <?php echo json_encode(array_column($submission_trends, 'submissions')); ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
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
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });

        // Export functions
        function exportData(format) {
            alert(`Exporting data in ${format.toUpperCase()} format would be implemented here.`);
        }

        // Add hover effects
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

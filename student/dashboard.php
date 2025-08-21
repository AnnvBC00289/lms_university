<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get enrolled courses
$query = "SELECT c.*, u.first_name, u.last_name, e.enrollment_date, e.final_grade 
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          JOIN users u ON c.instructor_id = u.id 
          WHERE e.student_id = ? AND e.status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assignments
$query = "SELECT a.*, c.title as course_title, c.course_code,
          CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status,
          s.grade, s.submitted_at
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE e.student_id = ? AND e.status = 'enrolled'
          ORDER BY a.due_date ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent quiz attempts
$query = "SELECT q.title, c.title as course_title, qa.score, qa.total_points, qa.completed_at
          FROM quiz_attempts qa
          JOIN quizzes q ON qa.quiz_id = q.id
          JOIN courses c ON q.course_id = c.id
          WHERE qa.student_id = ? AND qa.completed_at IS NOT NULL
          ORDER BY qa.completed_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
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
        }

        .sidebar {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
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

        /* Modern Card Styles */
        .dashboard-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .card-header-modern {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0;
        }

        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .stats-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            margin-left: auto;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
        }

        .stats-label {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            padding: 2.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .course-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .timeline-modern {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-modern::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
        }

        .timeline-item-modern {
            position: relative;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }

        .timeline-item-modern::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary);
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .table-modern thead th {
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
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
            border-bottom: 1px solid var(--gray-100);
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }

        .section-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }

        /* Prevent content overflow */
        .row {
            margin-left: 0;
            margin-right: 0;
        }
        
        .col-lg-4, .col-lg-8, .col-md-6, .col-xl-3 {
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (max-width: 768px) {
            .stats-card {
                padding: 1.5rem;
            }
            .welcome-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header" data-aos="fade-down">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="section-title">
                                <i class="fas fa-tachometer-alt text-primary me-3"></i>Student Dashboard
                    </h1>
                            <p class="section-subtitle">Welcome to your learning hub</p>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-modern">
                                <i class="fas fa-calendar me-2"></i>This Week
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-modern">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Welcome Section -->
                <div class="row mb-5" data-aos="fade-up">
                    <div class="col-12">
                        <div class="welcome-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="position: relative; z-index: 2;">
                                    <h3 class="fw-bold mb-2">
                                        <i class="fas fa-user-graduate me-3"></i>
                                        Welcome back, <?php echo $_SESSION['first_name']; ?>!
                                    </h3>
                                    <p class="mb-3 opacity-90">
                                        You have <?php echo count($enrolled_courses); ?> active courses and 
                                        <?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'pending'; })); ?> 
                                        pending assignments to complete.
                                    </p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-white text-primary">
                                            <?php echo count($enrolled_courses); ?> Courses
                                        </span>
                                        <span class="badge bg-white text-warning">
                                            <?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'pending'; })); ?> Pending
                                        </span>
                                    </div>
                                </div>
                                <div class="d-none d-md-block" style="position: relative; z-index: 2;">
                                    <i class="fas fa-graduation-cap" style="font-size: 4rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-5">
                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-label mb-2">Enrolled Courses</div>
                                    <div class="stats-number"><?php echo count($enrolled_courses); ?></div>
                                    <small class="text-success">
                                        <i class="fas fa-arrow-up me-1"></i>Active enrollment
                                    </small>
                                    </div>
                                <div class="stats-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-label mb-2">Completed Assignments</div>
                                    <div class="stats-number">
                                            <?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'submitted'; })); ?>
                                    </div>
                                    <small class="text-success">
                                        <i class="fas fa-check me-1"></i>Well done!
                                    </small>
                                    </div>
                                <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-label mb-2">Quiz Attempts</div>
                                    <div class="stats-number"><?php echo count($recent_quizzes); ?></div>
                                    <small class="text-info">
                                        <i class="fas fa-chart-line me-1"></i>Recent activity
                                    </small>
                                    </div>
                                <div class="stats-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-label mb-2">Pending Tasks</div>
                                    <div class="stats-number">
                                            <?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'pending'; })); ?>
                                    </div>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>Due soon
                                    </small>
                                    </div>
                                <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- Enrolled Courses -->
                    <div class="col-lg-8 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-book me-3"></i> My Courses
                                </h5>
                                <p class="mb-0 opacity-90 small">Your enrolled courses this semester</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (empty($enrolled_courses)): ?>
                                    <div class="text-center py-5">
                                        <div class="mb-4">
                                            <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">No Courses Enrolled</h5>
                                        <p class="text-muted mb-4">You haven't enrolled in any courses yet. Start your learning journey today!</p>
                                        <a href="courses.php" class="btn btn-primary btn-modern">
                                            <i class="fas fa-search me-2"></i>Browse Courses
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($enrolled_courses as $index => $course): ?>
                                            <div class="col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                                                <div class="course-card">
                                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                                        <div class="course-icon" style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-graduation-cap text-white"></i>
                                                        </div>
                                                        <span class="badge bg-success badge-modern">Active</span>
                                                    </div>
                                                    <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                    <p class="text-muted small mb-3">
                                                        <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                            <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo formatDate($course['enrollment_date']); ?>
                                                            </small>
                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary btn-modern">
                                                            <i class="fas fa-arrow-right me-1"></i>View
                                                            </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-clock me-3"></i> Recent Activity
                                </h5>
                                <p class="mb-0 opacity-90 small">Your latest assignments</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="timeline-modern">
                                    <?php if (empty($recent_assignments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-tasks text-muted mb-3" style="font-size: 2rem; opacity: 0.3;"></i>
                                            <p class="text-muted small">No recent assignments</p>
                                        </div>
                                    <?php else: ?>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                            <div class="timeline-item-modern">
                                                <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <i class="fas fa-book me-1"></i>
                                                    <?php echo htmlspecialchars($assignment['course_title']); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo formatDate($assignment['due_date']); ?>
                                                    </small>
                                                    <?php if ($assignment['status'] == 'submitted'): ?>
                                                        <span class="badge bg-success badge-modern">
                                                            <i class="fas fa-check me-1"></i>Submitted
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning badge-modern">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Quiz Results -->
                <?php if (!empty($recent_quizzes)): ?>
                <div class="row" data-aos="fade-up" data-aos-delay="300">
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-chart-line me-3"></i> Recent Quiz Results
                                </h5>
                                <p class="mb-0 opacity-90 small">Your latest quiz performances</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Quiz Title</th>
                                                <th>Course</th>
                                                <th>Score</th>
                                                <th>Performance</th>
                                                <th>Completed Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_quizzes as $quiz): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($quiz['title']); ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo htmlspecialchars($quiz['course_title']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-bold"><?php echo $quiz['score']; ?>/<?php echo $quiz['total_points']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $percentage = ($quiz['score'] / $quiz['total_points']) * 100;
                                                        if ($percentage >= 90) {
                                                            $badge_class = 'bg-success';
                                                            $icon = 'fas fa-trophy';
                                                        } elseif ($percentage >= 70) {
                                                            $badge_class = 'bg-warning';
                                                            $icon = 'fas fa-star';
                                                        } else {
                                                            $badge_class = 'bg-danger';
                                                            $icon = 'fas fa-exclamation-circle';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?> badge-modern">
                                                            <i class="<?php echo $icon; ?> me-1"></i>
                                                            <?php echo number_format($percentage, 1); ?>%
                                                        </span>
                                                    </td>
                                                    <td class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo formatDate($quiz['completed_at']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in body
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);

            // Animate stats numbers
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.textContent);
                let currentNumber = 0;
                const increment = finalNumber / 30;
                
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= finalNumber) {
                        stat.textContent = finalNumber;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(currentNumber);
                    }
                }, 50);
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.stats-card, .course-card, .dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = this.classList.contains('stats-card') 
                    ? 'translateY(-8px) scale(1.02)' 
                    : 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
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
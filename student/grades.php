<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$course_filter = (int)($_GET['course'] ?? 0);
$semester_filter = $_GET['semester'] ?? '';

// Build base conditions
$conditions = ['e.student_id = ?'];
$params = [$_SESSION['user_id']];

if ($course_filter > 0) {
    $conditions[] = 'c.id = ?';
    $params[] = $course_filter;
}

if ($semester_filter) {
    $conditions[] = 'c.semester = ?';
    $params[] = $semester_filter;
}

// Get enrolled courses with grades
$query = "SELECT c.*, e.enrollment_date, e.final_grade, e.status,
          u.first_name as instructor_first, u.last_name as instructor_last,
          
          -- Assignment stats
          COUNT(DISTINCT a.id) as total_assignments,
          COUNT(DISTINCT CASE WHEN asub.id IS NOT NULL THEN a.id END) as submitted_assignments,
          COUNT(DISTINCT CASE WHEN asub.grade IS NOT NULL THEN a.id END) as graded_assignments,
          ROUND(AVG(CASE WHEN asub.grade IS NOT NULL THEN (asub.grade / a.max_points) * 100 END), 2) as avg_assignment_grade,
          
          -- Quiz stats  
          COUNT(DISTINCT q.id) as total_quizzes,
          COUNT(DISTINCT CASE WHEN qattempt.completed_at IS NOT NULL THEN q.id END) as completed_quizzes,
          ROUND(AVG(CASE WHEN qattempt.score IS NOT NULL AND qattempt.total_points > 0 
                   THEN (qattempt.score / qattempt.total_points) * 100 END), 2) as avg_quiz_grade
          
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          JOIN users u ON c.instructor_id = u.id
          LEFT JOIN assignments a ON c.id = a.course_id
          LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = e.student_id
          LEFT JOIN quizzes q ON c.id = q.course_id
          LEFT JOIN quiz_attempts qattempt ON q.id = qattempt.quiz_id AND qattempt.student_id = e.student_id 
                                           AND qattempt.completed_at IS NOT NULL
          WHERE " . implode(' AND ', $conditions) . " AND e.status = 'enrolled'
          GROUP BY c.id, e.id
          ORDER BY c.semester DESC, c.title";

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed assignment grades for selected course or all courses
$assignment_conditions = $conditions;
$assignment_params = $params;

$query = "SELECT a.*, asub.grade, asub.submitted_at, asub.feedback, c.title as course_title, c.course_code,
          ROUND((asub.grade / a.max_points) * 100, 1) as percentage
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
          WHERE " . implode(' AND ', $assignment_conditions) . " AND e.status = 'enrolled'
          AND asub.grade IS NOT NULL
          ORDER BY a.due_date DESC";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$_SESSION['user_id']], $assignment_params));
$assignment_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed quiz grades
$query = "SELECT q.*, qa.score, qa.total_points, qa.completed_at, c.title as course_title, c.course_code,
          ROUND((qa.score / qa.total_points) * 100, 1) as percentage,
          qa.attempt_number
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
          WHERE " . implode(' AND ', $assignment_conditions) . " AND e.status = 'enrolled'
          AND qa.completed_at IS NOT NULL AND qa.score IS NOT NULL
          ORDER BY qa.completed_at DESC";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$_SESSION['user_id']], $assignment_params));
$quiz_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available semesters for filter
$query = "SELECT DISTINCT c.semester 
          FROM courses c
          JOIN enrollments e ON c.id = e.course_id
          WHERE e.student_id = ? AND e.status = 'enrolled' AND c.semester IS NOT NULL
          ORDER BY c.semester DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate overall statistics
$total_courses = count($courses);
$completed_courses = count(array_filter($courses, function($c) { return $c['final_grade'] !== null; }));

$all_assignment_percentages = array_filter(array_column($assignment_grades, 'percentage'));
$overall_assignment_avg = $all_assignment_percentages ? array_sum($all_assignment_percentages) / count($all_assignment_percentages) : 0;

$all_quiz_percentages = array_filter(array_column($quiz_grades, 'percentage'));
$overall_quiz_avg = $all_quiz_percentages ? array_sum($all_quiz_percentages) / count($all_quiz_percentages) : 0;

$final_grades = array_filter(array_column($courses, 'final_grade'));
$overall_gpa = $final_grades ? array_sum($final_grades) / count($final_grades) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></link>
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .grade-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .grade-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
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
            border-radius: 16px 16px 0 0;
        }

        .stats-card.gpa::before {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }

        .stats-card.assignments::before {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stats-card.quizzes::before {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stats-card.courses::before {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .grade-display {
            font-size: 3rem;
            font-weight: 900;
            line-height: 1;
        }

        .grade-a { color: #10b981; }
        .grade-b { color: #06b6d4; }
        .grade-c { color: #f59e0b; }
        .grade-d { color: #ef4444; }
        .grade-f { color: #dc2626; }

        .course-grade-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .course-grade-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 16px 0 0 16px;
        }

        .course-grade-card.grade-a::before { background: #10b981; }
        .course-grade-card.grade-b::before { background: #06b6d4; }
        .course-grade-card.grade-c::before { background: #f59e0b; }
        .course-grade-card.grade-d::before { background: #ef4444; }
        .course-grade-card.grade-f::before { background: #dc2626; }

        .progress-ring {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .progress-ring svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .progress-ring-circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .progress-ring-bg {
            stroke: #e2e8f0;
        }

        .progress-ring-progress {
            stroke: #6366f1;
            stroke-dasharray: 0 251.2;
            transition: stroke-dasharray 0.8s ease-in-out;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 700;
            font-size: 0.875rem;
            color: #1e293b;
        }

        .section-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .table-modern thead th {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            color: #475569;
        }

        .table-modern tbody td {
            border: none;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
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
                                <i class="fas fa-chart-line text-primary me-3"></i>My Grades
                            </h1>
                            <p class="text-muted">Track your academic performance and progress</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="printReport()">
                                <i class="fas fa-print me-2"></i>Print Report
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportGrades()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Overview Stats -->
                <div class="row mb-4" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card gpa">
                            <div class="stats-number text-primary"><?php echo number_format($overall_gpa, 2); ?></div>
                            <div class="stats-label">Overall GPA</div>
                            <div class="progress-ring mt-3 mx-auto">
                                <svg>
                                    <circle class="progress-ring-circle progress-ring-bg" cx="40" cy="40" r="32"></circle>
                                    <circle class="progress-ring-circle progress-ring-progress" cx="40" cy="40" r="32" 
                                            style="stroke-dasharray: <?php echo ($overall_gpa / 4.0) * 251.2; ?> 251.2;"></circle>
                                </svg>
                                <div class="progress-text"><?php echo number_format(($overall_gpa / 4.0) * 100, 0); ?>%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card assignments">
                            <div class="stats-number text-success"><?php echo number_format($overall_assignment_avg, 1); ?>%</div>
                            <div class="stats-label">Assignment Average</div>
                            <small class="text-muted mt-2 d-block"><?php echo count($assignment_grades); ?> graded assignments</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card quizzes">
                            <div class="stats-number text-warning"><?php echo number_format($overall_quiz_avg, 1); ?>%</div>
                            <div class="stats-label">Quiz Average</div>
                            <small class="text-muted mt-2 d-block"><?php echo count($quiz_grades); ?> completed quizzes</small>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card courses">
                            <div class="stats-number text-info"><?php echo $total_courses; ?></div>
                            <div class="stats-label">Enrolled Courses</div>
                            <small class="text-muted mt-2 d-block"><?php echo $completed_courses; ?> with final grades</small>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-book me-2"></i>Filter by Course</h6>
                            <select class="form-select" id="courseFilter" onchange="filterByCourse(this.value)">
                                <option value="0">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-calendar me-2"></i>Filter by Semester</h6>
                            <select class="form-select" id="semesterFilter" onchange="filterBySemester(this.value)">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?php echo htmlspecialchars($semester); ?>" 
                                            <?php echo $semester_filter === $semester ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($semester); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-pills mb-4" data-aos="fade-up" data-aos-delay="150">
                    <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview" role="tab">
                            <i class="fas fa-tachometer-alt me-2"></i>Course Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assignments-tab" data-bs-toggle="pill" href="#assignments" role="tab">
                            <i class="fas fa-tasks me-2"></i>Assignment Grades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="quizzes-tab" data-bs-toggle="pill" href="#quizzes" role="tab">
                            <i class="fas fa-question-circle me-2"></i>Quiz Grades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="analytics-tab" data-bs-toggle="pill" href="#analytics" role="tab">
                            <i class="fas fa-chart-bar me-2"></i>Performance Analytics
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" data-aos="fade-up" data-aos-delay="200">
                    <!-- Course Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <?php if (empty($courses)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-graduation-cap text-muted mb-3" style="font-size: 4rem; opacity: 0.3;"></i>
                                <h5 class="text-muted">No Courses Found</h5>
                                <p class="text-muted">You are not enrolled in any courses yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($courses as $index => $course): ?>
                                    <?php
                                    $grade_class = '';
                                    $grade_letter = '';
                                    if ($course['final_grade']) {
                                        if ($course['final_grade'] >= 90) { $grade_class = 'grade-a'; $grade_letter = 'A'; }
                                        elseif ($course['final_grade'] >= 80) { $grade_class = 'grade-b'; $grade_letter = 'B'; }
                                        elseif ($course['final_grade'] >= 70) { $grade_class = 'grade-c'; $grade_letter = 'C'; }
                                        elseif ($course['final_grade'] >= 60) { $grade_class = 'grade-d'; $grade_letter = 'D'; }
                                        else { $grade_class = 'grade-f'; $grade_letter = 'F'; }
                                    }
                                    ?>
                                    <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                        <div class="course-grade-card <?php echo $grade_class; ?>">
                                            <div class="row align-items-center">
                                                <div class="col-8">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <h5 class="fw-bold mb-0 me-2"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                    </div>
                                                    
                                                    <p class="text-muted small mb-2">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($course['instructor_first'] . ' ' . $course['instructor_last']); ?>
                                                        <?php if ($course['semester']): ?>
                                                            <span class="mx-2">•</span>
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo htmlspecialchars($course['semester']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    
                                                    <div class="row g-2 text-center">
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Assignments</small>
                                                            <strong class="text-success">
                                                                <?php echo $course['avg_assignment_grade'] ? number_format($course['avg_assignment_grade'], 1) . '%' : 'N/A'; ?>
                                                            </strong>
                                                            <small class="text-muted d-block">
                                                                (<?php echo $course['graded_assignments']; ?>/<?php echo $course['total_assignments']; ?>)
                                                            </small>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Quizzes</small>
                                                            <strong class="text-warning">
                                                                <?php echo $course['avg_quiz_grade'] ? number_format($course['avg_quiz_grade'], 1) . '%' : 'N/A'; ?>
                                                            </strong>
                                                            <small class="text-muted d-block">
                                                                (<?php echo $course['completed_quizzes']; ?>/<?php echo $course['total_quizzes']; ?>)
                                                            </small>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Progress</small>
                                                            <strong class="text-info">
                                                                <?php 
                                                                $progress = 0;
                                                                if ($course['total_assignments'] > 0 && $course['total_quizzes'] > 0) {
                                                                    $assignment_progress = ($course['submitted_assignments'] / $course['total_assignments']) * 50;
                                                                    $quiz_progress = ($course['completed_quizzes'] / $course['total_quizzes']) * 50;
                                                                    $progress = $assignment_progress + $quiz_progress;
                                                                }
                                                                echo number_format($progress, 0) . '%';
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-4 text-center">
                                                    <?php if ($course['final_grade']): ?>
                                                        <div class="grade-display <?php echo $grade_class; ?>">
                                                            <?php echo $grade_letter; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo number_format($course['final_grade'], 1); ?>%
                                                        </small>
                                                    <?php else: ?>
                                                        <div class="text-muted">
                                                            <i class="fas fa-clock fa-2x mb-2"></i>
                                                            <div>In Progress</div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mt-2">
                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View Course
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Assignment Grades Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <?php if (empty($assignment_grades)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks text-muted mb-3" style="font-size: 4rem; opacity: 0.3;"></i>
                                <h5 class="text-muted">No Graded Assignments</h5>
                                <p class="text-muted">Your graded assignments will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Assignment</th>
                                            <th>Course</th>
                                            <th>Due Date</th>
                                            <th>Submitted</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignment_grades as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                        <?php if ($assignment['description']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars(substr($assignment['description'], 0, 50)) . '...'; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($assignment['course_code']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($assignment['due_date']); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <?php echo formatDate($assignment['submitted_at']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $percentage = $assignment['percentage'];
                                                    $badge_class = $percentage >= 90 ? 'success' : ($percentage >= 80 ? 'primary' : ($percentage >= 70 ? 'warning' : 'danger'));
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="showFeedback('<?php echo htmlspecialchars($assignment['title']); ?>', '<?php echo htmlspecialchars($assignment['feedback'] ?? 'No feedback provided'); ?>')">
                                                        <i class="fas fa-comment"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quiz Grades Tab -->
                    <div class="tab-pane fade" id="quizzes" role="tabpanel">
                        <?php if (empty($quiz_grades)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-question-circle text-muted mb-3" style="font-size: 4rem; opacity: 0.3;"></i>
                                <h5 class="text-muted">No Quiz Results</h5>
                                <p class="text-muted">Your completed quiz results will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Quiz</th>
                                            <th>Course</th>
                                            <th>Completed</th>
                                            <th>Attempt</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quiz_grades as $quiz): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                        <?php if ($quiz['description']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . '...'; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($quiz['course_code']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($quiz['completed_at']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        #<?php echo $quiz['attempt_number']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo $quiz['score']; ?>/<?php echo $quiz['total_points']; ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $percentage = $quiz['percentage'];
                                                    $badge_class = $percentage >= 90 ? 'success' : ($percentage >= 80 ? 'primary' : ($percentage >= 70 ? 'warning' : 'danger'));
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($percentage >= 90) {
                                                        echo '<i class="fas fa-trophy text-warning" title="Excellent"></i>';
                                                    } elseif ($percentage >= 80) {
                                                        echo '<i class="fas fa-medal text-primary" title="Good"></i>';
                                                    } elseif ($percentage >= 70) {
                                                        echo '<i class="fas fa-thumbs-up text-success" title="Satisfactory"></i>';
                                                    } else {
                                                        echo '<i class="fas fa-arrow-up text-info" title="Needs Improvement"></i>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Performance Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="chart-container">
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-chart-line me-2 text-primary"></i>Grade Distribution
                                    </h6>
                                    <canvas id="gradeDistributionChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="chart-container">
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-chart-pie me-2 text-success"></i>Assignment vs Quiz Performance
                                    </h6>
                                    <canvas id="performanceChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Insights -->
                        <div class="chart-container">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-lightbulb me-2 text-warning"></i>Performance Insights
                            </h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-success mb-1">Strengths</h6>
                                        <ul class="list-unstyled small mb-0">
                                            <?php if ($overall_assignment_avg > $overall_quiz_avg): ?>
                                                <li><i class="fas fa-check text-success me-1"></i> Better at assignments</li>
                                            <?php else: ?>
                                                <li><i class="fas fa-check text-success me-1"></i> Strong quiz performance</li>
                                            <?php endif; ?>
                                            <?php if ($overall_gpa >= 3.5): ?>
                                                <li><i class="fas fa-check text-success me-1"></i> High GPA achievement</li>
                                            <?php endif; ?>
                                            <?php if (count($assignment_grades) > 0): ?>
                                                <li><i class="fas fa-check text-success me-1"></i> Consistent submissions</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-warning mb-1">Areas for Improvement</h6>
                                        <ul class="list-unstyled small mb-0">
                                            <?php if ($overall_assignment_avg < 75): ?>
                                                <li><i class="fas fa-arrow-up text-warning me-1"></i> Assignment performance</li>
                                            <?php endif; ?>
                                            <?php if ($overall_quiz_avg < 75): ?>
                                                <li><i class="fas fa-arrow-up text-warning me-1"></i> Quiz preparation</li>
                                            <?php endif; ?>
                                            <?php if ($overall_gpa < 3.0): ?>
                                                <li><i class="fas fa-arrow-up text-warning me-1"></i> Overall GPA</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h6 class="text-info mb-1">Recommendations</h6>
                                        <ul class="list-unstyled small mb-0">
                                            <li><i class="fas fa-star text-info me-1"></i> Review feedback regularly</li>
                                            <li><i class="fas fa-star text-info me-1"></i> Attend study groups</li>
                                            <li><i class="fas fa-star text-info me-1"></i> Visit instructor office hours</li>
                                            <li><i class="fas fa-star text-info me-1"></i> Practice time management</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalLabel">Assignment Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="feedbackTitle" class="fw-bold mb-3"></h6>
                    <p id="feedbackContent" class="text-muted"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // Filter functions
        function filterByCourse(courseId) {
            const currentSemester = document.getElementById('semesterFilter').value;
            window.location.href = `?course=${courseId}&semester=${currentSemester}`;
        }

        function filterBySemester(semester) {
            const currentCourse = document.getElementById('courseFilter').value;
            window.location.href = `?course=${currentCourse}&semester=${semester}`;
        }

        // Show feedback modal
        function showFeedback(title, feedback) {
            document.getElementById('feedbackTitle').textContent = title;
            document.getElementById('feedbackContent').textContent = feedback;
            new bootstrap.Modal(document.getElementById('feedbackModal')).show();
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Export grades
        function exportGrades() {
            alert('Export feature will be implemented soon!');
        }

        // Initialize charts when analytics tab is shown
        document.getElementById('analytics-tab').addEventListener('shown.bs.tab', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Grade Distribution Chart
            const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
            
            // Calculate grade distribution
            const grades = [<?php echo implode(',', array_column($assignment_grades, 'percentage')); ?>];
            const aCount = grades.filter(g => g >= 90).length;
            const bCount = grades.filter(g => g >= 80 && g < 90).length;
            const cCount = grades.filter(g => g >= 70 && g < 80).length;
            const dCount = grades.filter(g => g >= 60 && g < 70).length;
            const fCount = grades.filter(g => g < 60).length;

            new Chart(gradeCtx, {
                type: 'bar',
                data: {
                    labels: ['A (90-100%)', 'B (80-89%)', 'C (70-79%)', 'D (60-69%)', 'F (<60%)'],
                    datasets: [{
                        label: 'Number of Assignments',
                        data: [aCount, bCount, cCount, dCount, fCount],
                        backgroundColor: ['#10b981', '#06b6d4', '#f59e0b', '#ef4444', '#dc2626'],
                        borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Performance Comparison Chart
            const perfCtx = document.getElementById('performanceChart').getContext('2d');
            
            new Chart(perfCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Assignments', 'Quizzes'],
                    datasets: [{
                        data: [<?php echo number_format($overall_assignment_avg, 1); ?>, <?php echo number_format($overall_quiz_avg, 1); ?>],
                        backgroundColor: ['#10b981', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        // Animate stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach(stat => {
                const finalNumber = parseFloat(stat.textContent);
                let currentNumber = 0;
                const increment = finalNumber / 30;
                const isFloat = stat.textContent.includes('.');
                
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= finalNumber) {
                        stat.textContent = isFloat ? finalNumber.toFixed(2) : finalNumber;
                        clearInterval(timer);
                    } else {
                        stat.textContent = isFloat ? currentNumber.toFixed(2) : Math.floor(currentNumber);
                    }
                }, 50);
            });

            // Animate progress rings
            const progressRings = document.querySelectorAll('.progress-ring-progress');
            progressRings.forEach(ring => {
                const dashArray = ring.style.strokeDasharray;
                ring.style.strokeDasharray = '0 251.2';
                setTimeout(() => {
                    ring.style.strokeDasharray = dashArray;
                }, 500);
            });
        });

        // Tab switching animation
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                AOS.refresh();
            });
        });
    </script>
</body>
</html>

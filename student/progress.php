<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get student's academic progress
$query = "SELECT c.*, e.enrollment_date, e.final_grade,
          COUNT(DISTINCT a.id) as total_assignments,
          COUNT(DISTINCT s.id) as submitted_assignments,
          AVG(s.grade) as avg_assignment_grade,
          COUNT(DISTINCT q.id) as total_quizzes,
          COUNT(DISTINCT qa.id) as attempted_quizzes,
          AVG(qa.score) as avg_quiz_score
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          LEFT JOIN assignments a ON c.id = a.course_id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          LEFT JOIN quizzes q ON c.id = q.course_id
          LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
          WHERE e.student_id = ? AND e.status = 'enrolled'
          GROUP BY c.id
          ORDER BY c.course_code";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$courses_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall statistics
$total_courses = count($courses_progress);
$completed_courses = count(array_filter($courses_progress, function($c) { return $c['final_grade'] !== null; }));
$total_assignments = array_sum(array_column($courses_progress, 'total_assignments'));
$submitted_assignments = array_sum(array_column($courses_progress, 'submitted_assignments'));
$total_quizzes = array_sum(array_column($courses_progress, 'total_quizzes'));
$attempted_quizzes = array_sum(array_column($courses_progress, 'attempted_quizzes'));

// Calculate GPA
$total_credits = 0;
$total_grade_points = 0;
foreach ($courses_progress as $course) {
    if ($course['final_grade'] !== null) {
        $credits = $course['credits'] ?? 3;
        $total_credits += $credits;
        
        // Convert percentage to GPA (simplified)
        $percentage = $course['final_grade'];
        if ($percentage >= 90) $gpa_points = 4.0;
        elseif ($percentage >= 80) $gpa_points = 3.0;
        elseif ($percentage >= 70) $gpa_points = 2.0;
        elseif ($percentage >= 60) $gpa_points = 1.0;
        else $gpa_points = 0.0;
        
        $total_grade_points += ($gpa_points * $credits);
    }
}
$gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;

// Get recent activity
$query = "SELECT 'assignment' as type, a.title, c.course_code, s.submitted_at as date, s.grade
          FROM assignment_submissions s
          JOIN assignments a ON s.assignment_id = a.id
          JOIN courses c ON a.course_id = c.id
          WHERE s.student_id = ?
          UNION ALL
          SELECT 'quiz' as type, q.title, c.course_code, qa.completed_at as date, qa.score as grade
          FROM quiz_attempts qa
          JOIN quizzes q ON qa.quiz_id = q.id
          JOIN courses c ON q.course_id = c.id
          WHERE qa.student_id = ?
          ORDER BY date DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade distribution
$grade_ranges = [
    'A (90-100)' => 0,
    'B (80-89)' => 0,
    'C (70-79)' => 0,
    'D (60-69)' => 0,
    'F (0-59)' => 0
];

foreach ($courses_progress as $course) {
    if ($course['final_grade'] !== null) {
        $grade = $course['final_grade'];
        if ($grade >= 90) $grade_ranges['A (90-100)']++;
        elseif ($grade >= 80) $grade_ranges['B (80-89)']++;
        elseif ($grade >= 70) $grade_ranges['C (70-79)']++;
        elseif ($grade >= 60) $grade_ranges['D (60-69)']++;
        else $grade_ranges['F (0-59)']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Progress - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .progress-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0deg 180deg, #e9ecef 180deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .progress-circle.completed {
            background: conic-gradient(#28a745 0deg 360deg);
        }
        .activity-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .activity-item.quiz {
            border-left-color: #ffc107;
        }
        .grade-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Academic Progress
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="exportProgress()">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printProgress()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card progress-card">
                            <div class="card-body text-center">
                                <div class="progress-circle <?php echo $completed_courses == $total_courses ? 'completed' : ''; ?>">
                                    <div class="text-center">
                                        <h4 class="mb-0"><?php echo $completed_courses; ?>/<?php echo $total_courses; ?></h4>
                                        <small>Courses</small>
                                    </div>
                                </div>
                                <h6 class="mt-3">Course Completion</h6>
                                <p class="mb-0"><?php echo $total_courses > 0 ? round(($completed_courses / $total_courses) * 100, 1) : 0; ?>%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card">
                            <div class="card-body text-center">
                                <i class="fas fa-star fa-3x mb-3"></i>
                                <h3 class="mb-0"><?php echo $gpa; ?></h3>
                                <h6>Current GPA</h6>
                                <p class="mb-0"><?php echo $total_credits; ?> Credits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks fa-3x mb-3"></i>
                                <h3 class="mb-0"><?php echo $submitted_assignments; ?>/<?php echo $total_assignments; ?></h3>
                                <h6>Assignments</h6>
                                <p class="mb-0"><?php echo $total_assignments > 0 ? round(($submitted_assignments / $total_assignments) * 100, 1) : 0; ?>% Submitted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card progress-card">
                            <div class="card-body text-center">
                                <i class="fas fa-question-circle fa-3x mb-3"></i>
                                <h3 class="mb-0"><?php echo $attempted_quizzes; ?>/<?php echo $total_quizzes; ?></h3>
                                <h6>Quizzes</h6>
                                <p class="mb-0"><?php echo $total_quizzes > 0 ? round(($attempted_quizzes / $total_quizzes) * 100, 1) : 0; ?>% Attempted</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Course Progress -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-book me-2"></i>Course Progress
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($courses_progress)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                        <h5>No Courses Enrolled</h5>
                                        <p class="text-muted">You haven't enrolled in any courses yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Progress</th>
                                                    <th>Assignments</th>
                                                    <th>Quizzes</th>
                                                    <th>Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($courses_progress as $course): ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($course['course_code']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($course['title']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $progress = 0;
                                                            if ($course['total_assignments'] > 0) {
                                                                $progress += ($course['submitted_assignments'] / $course['total_assignments']) * 50;
                                                            }
                                                            if ($course['total_quizzes'] > 0) {
                                                                $progress += ($course['attempted_quizzes'] / $course['total_quizzes']) * 50;
                                                            }
                                                            ?>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                            </div>
                                                            <small class="text-muted"><?php echo round($progress, 1); ?>%</small>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <?php echo $course['submitted_assignments']; ?>/<?php echo $course['total_assignments']; ?>
                                                                <?php if ($course['avg_assignment_grade']): ?>
                                                                    <br>
                                                                    <span class="text-success"><?php echo round($course['avg_assignment_grade'], 1); ?>% avg</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <?php echo $course['attempted_quizzes']; ?>/<?php echo $course['total_quizzes']; ?>
                                                                <?php if ($course['avg_quiz_score']): ?>
                                                                    <br>
                                                                    <span class="text-warning"><?php echo round($course['avg_quiz_score'], 1); ?>% avg</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php if ($course['final_grade'] !== null): ?>
                                                                <span class="badge <?php 
                                                                    echo $course['final_grade'] >= 90 ? 'bg-success' : 
                                                                        ($course['final_grade'] >= 80 ? 'bg-info' : 
                                                                        ($course['final_grade'] >= 70 ? 'bg-warning' : 
                                                                        ($course['final_grade'] >= 60 ? 'bg-secondary' : 'bg-danger'))); 
                                                                ?> grade-badge">
                                                                    <?php echo $course['final_grade']; ?>%
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">In Progress</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Grade Distribution Chart -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>Grade Distribution
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="gradeDistributionChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <p class="text-muted small">No recent activity</p>
                                <?php else: ?>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item <?php echo $activity['type']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($activity['course_code']); ?> • 
                                                        <?php echo ucfirst($activity['type']); ?>
                                                    </small>
                                                </div>
                                                <?php if ($activity['grade'] !== null): ?>
                                                    <span class="badge <?php 
                                                        echo $activity['grade'] >= 90 ? 'bg-success' : 
                                                            ($activity['grade'] >= 80 ? 'bg-info' : 
                                                            ($activity['grade'] >= 70 ? 'bg-warning' : 
                                                            ($activity['grade'] >= 60 ? 'bg-secondary' : 'bg-danger'))); 
                                                    ?> grade-badge">
                                                        <?php echo $activity['grade']; ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Quick Stats
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?php echo $total_courses; ?></h4>
                                        <small class="text-muted">Enrolled Courses</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo $completed_courses; ?></h4>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-info"><?php echo $total_assignments; ?></h4>
                                        <small class="text-muted">Total Assignments</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-warning"><?php echo $total_quizzes; ?></h4>
                                        <small class="text-muted">Total Quizzes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grade Distribution Chart
        const ctx = document.getElementById('gradeDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($grade_ranges)); ?>,
                datasets: [{
                    label: 'Number of Courses',
                    data: <?php echo json_encode(array_values($grade_ranges)); ?>,
                    backgroundColor: [
                        '#28a745', // A
                        '#17a2b8', // B
                        '#ffc107', // C
                        '#6c757d', // D
                        '#dc3545'  // F
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function exportProgress() {
            alert('Export functionality will be implemented soon!');
        }

        function printProgress() {
            window.print();
        }
    </script>
</body>
</html>


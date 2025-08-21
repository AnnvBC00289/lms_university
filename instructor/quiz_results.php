<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$quiz_id = (int)($_GET['id'] ?? 0);
if (!$quiz_id) {
    header('Location: quizzes.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get quiz details
$query = "SELECT q.*, c.title as course_title, c.course_code
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          WHERE q.id = ? AND c.instructor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: quizzes.php?error=unauthorized');
    exit();
}

// Get quiz attempts
$query = "SELECT qa.*, u.first_name, u.last_name, u.email
          FROM quiz_attempts qa
          JOIN users u ON qa.student_id = u.id
          WHERE qa.quiz_id = ?
          ORDER BY qa.completed_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$quiz_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_attempts = count($attempts);
$completed_attempts = count(array_filter($attempts, function($a) { return $a['completed_at'] !== null; }));
$avg_score = $total_attempts > 0 ? round(array_sum(array_column($attempts, 'score')) / $total_attempts, 1) : 0;
$highest_score = $total_attempts > 0 ? max(array_column($attempts, 'score')) : 0;
$lowest_score = $total_attempts > 0 ? min(array_column($attempts, 'score')) : 0;

// Get question statistics
$query = "SELECT qq.*, 
          COUNT(qa.id) as total_answers,
          SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
          FROM quiz_questions qq
          LEFT JOIN quiz_answers qa ON qq.id = qa.question_id
          WHERE qq.quiz_id = ?
          GROUP BY qq.id
          ORDER BY qq.question_order";
$stmt = $db->prepare($query);
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get score distribution
$score_ranges = [
    '90-100' => 0,
    '80-89' => 0,
    '70-79' => 0,
    '60-69' => 0,
    '0-59' => 0
];

foreach ($attempts as $attempt) {
    if ($attempt['score'] !== null) {
        $score = $attempt['score'];
        if ($score >= 90) $score_ranges['90-100']++;
        elseif ($score >= 80) $score_ranges['80-89']++;
        elseif ($score >= 70) $score_ranges['70-79']++;
        elseif ($score >= 60) $score_ranges['60-69']++;
        else $score_ranges['0-59']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - Instructor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .score-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
        .question-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Quiz Results: <?php echo htmlspecialchars($quiz['title']); ?>
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="exportResults()">
                            <i class="fas fa-download me-2"></i>Export Results
                        </button>
                        <a href="quizzes.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                        </a>
                    </div>
                </div>

                <!-- Quiz Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Quiz Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_code'] . ' - ' . $quiz['course_title']); ?></p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($quiz['description'] ?: 'No description'); ?></p>
                                <p><strong>Time Limit:</strong> <?php echo $quiz['time_limit'] ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Max Attempts:</strong> <?php echo $quiz['max_attempts']; ?></p>
                                <p><strong>Due Date:</strong> <?php echo $quiz['due_date'] ? date('M j, Y H:i', strtotime($quiz['due_date'])) : 'No due date'; ?></p>
                                <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <h3 class="mb-0"><?php echo $total_attempts; ?></h3>
                                <h6>Total Attempts</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h3 class="mb-0"><?php echo $completed_attempts; ?></h3>
                                <h6>Completed</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-3"></i>
                                <h3 class="mb-0"><?php echo $avg_score; ?>%</h3>
                                <h6>Average Score</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-trophy fa-2x mb-3"></i>
                                <h3 class="mb-0"><?php echo $highest_score; ?>%</h3>
                                <h6>Highest Score</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Attempts List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Student Attempts
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attempts)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No Attempts Yet</h5>
                                        <p class="text-muted">Students haven't taken this quiz yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Attempt</th>
                                                    <th>Score</th>
                                                    <th>Time Taken</th>
                                                    <th>Status</th>
                                                    <th>Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attempts as $attempt): ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($attempt['email']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?php echo $attempt['attempt_number']; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($attempt['score'] !== null): ?>
                                                                <span class="badge <?php 
                                                                    echo $attempt['score'] >= 90 ? 'bg-success' : 
                                                                        ($attempt['score'] >= 80 ? 'bg-info' : 
                                                                        ($attempt['score'] >= 70 ? 'bg-warning' : 
                                                                        ($attempt['score'] >= 60 ? 'bg-secondary' : 'bg-danger'))); 
                                                                ?> score-badge">
                                                                    <?php echo $attempt['score']; ?>%
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">In Progress</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($attempt['started_at'] && $attempt['completed_at']) {
                                                                $start = new DateTime($attempt['started_at']);
                                                                $end = new DateTime($attempt['completed_at']);
                                                                $diff = $start->diff($end);
                                                                echo $diff->format('%H:%I:%S');
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($attempt['completed_at']): ?>
                                                                <span class="badge bg-success">Completed</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">In Progress</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $attempt['completed_at'] ? date('M j, Y H:i', strtotime($attempt['completed_at'])) : '-'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Question Analysis -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-question-circle me-2"></i>Question Analysis
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($questions)): ?>
                                    <p class="text-muted">No questions found for this quiz.</p>
                                <?php else: ?>
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="question-stats">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">Question <?php echo $index + 1; ?></h6>
                                                <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                            </div>
                                            <p class="mb-2"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <small class="text-muted">Total Answers: <?php echo $question['total_answers']; ?></small>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Correct: <?php echo $question['correct_answers']; ?></small>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">
                                                        Success Rate: 
                                                        <?php echo $question['total_answers'] > 0 ? round(($question['correct_answers'] / $question['total_answers']) * 100, 1) : 0; ?>%
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="col-md-4">
                        <!-- Score Distribution Chart -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Score Distribution
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="scoreDistributionChart" width="300" height="200"></canvas>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Quick Stats
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?php echo $lowest_score; ?>%</h4>
                                        <small class="text-muted">Lowest Score</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo count($questions); ?></h4>
                                        <small class="text-muted">Questions</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-info"><?php echo $quiz['max_attempts']; ?></h4>
                                        <small class="text-muted">Max Attempts</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-warning"><?php echo $quiz['time_limit'] ?: '∞'; ?></h4>
                                        <small class="text-muted">Time Limit (min)</small>
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
        // Score Distribution Chart
        const ctx = document.getElementById('scoreDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($score_ranges)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($score_ranges)); ?>,
                    backgroundColor: [
                        '#28a745', // 90-100
                        '#17a2b8', // 80-89
                        '#ffc107', // 70-79
                        '#6c757d', // 60-69
                        '#dc3545'  // 0-59
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        function exportResults() {
            alert('Export functionality will be implemented soon!');
        }
    </script>
</body>
</html>




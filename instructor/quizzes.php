<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get instructor's quizzes
$query = "SELECT q.*, c.title as course_title, c.course_code,
          COUNT(DISTINCT qa.id) as total_attempts,
          COUNT(DISTINCT qa.student_id) as unique_students,
          AVG(qa.score) as avg_score,
          MAX(qa.score) as highest_score
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
          WHERE c.instructor_id = ?
          GROUP BY q.id
          ORDER BY q.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course filter options
$query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - Instructor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <style>
        .quiz-card {
            transition: transform 0.2s ease-in-out;
        }
        .quiz-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                        <i class="fas fa-question-circle text-primary me-2"></i>
                        Quiz Management
                    </h1>
                    <a href="quiz_create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create New Quiz
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Quizzes</h6>
                                        <h3 class="mb-0"><?php echo count($quizzes); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-question-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Active Quizzes</h6>
                                        <h3 class="mb-0"><?php echo count(array_filter($quizzes, function($q) { return $q['due_date'] > date('Y-m-d H:i:s'); })); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-play-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Attempts</h6>
                                        <h3 class="mb-0"><?php echo array_sum(array_column($quizzes, 'total_attempts')); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Avg Score</h6>
                                        <h3 class="mb-0"><?php 
                                            $avg_scores = array_filter(array_column($quizzes, 'avg_score'));
                                            echo !empty($avg_scores) ? round(array_sum($avg_scores) / count($avg_scores), 1) . '%' : 'N/A';
                                        ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="course_filter" class="form-label">Filter by Course</label>
                                <select class="form-select" id="course_filter" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course']) && $_GET['course'] == $course['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status_filter" class="form-label">Filter by Status</label>
                                <select class="form-select" id="status_filter" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="expired" <?php echo (isset($_GET['status']) && $_GET['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="quizzes.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quizzes List -->
                <div class="row">
                    <?php if (empty($quizzes)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                    <h5>No Quizzes Found</h5>
                                    <p class="text-muted">You haven't created any quizzes yet.</p>
                                    <a href="quiz_create.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create Your First Quiz
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card quiz-card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                            <span class="badge <?php echo ($quiz['due_date'] > date('Y-m-d H:i:s')) ? 'bg-success' : 'bg-secondary'; ?> status-badge">
                                                <?php echo ($quiz['due_date'] > date('Y-m-d H:i:s')) ? 'Active' : 'Expired'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text text-muted small">
                                            <i class="fas fa-book me-1"></i>
                                            <?php echo htmlspecialchars($quiz['course_code'] . ' - ' . $quiz['course_title']); ?>
                                        </p>
                                        
                                        <?php if ($quiz['description']): ?>
                                            <p class="card-text small"><?php echo htmlspecialchars(substr($quiz['description'], 0, 100)) . (strlen($quiz['description']) > 100 ? '...' : ''); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <div class="border-end">
                                                    <h6 class="mb-0"><?php echo $quiz['total_attempts']; ?></h6>
                                                    <small class="text-muted">Attempts</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border-end">
                                                    <h6 class="mb-0"><?php echo $quiz['unique_students']; ?></h6>
                                                    <small class="text-muted">Students</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['avg_score'] ? round($quiz['avg_score'], 1) . '%' : 'N/A'; ?></h6>
                                                <small class="text-muted">Avg Score</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Due: <?php echo date('M j, Y', strtotime($quiz['due_date'])); ?>
                                            </small>
                                            <div class="btn-group btn-group-sm">
                                                <a href="quiz_view.php?id=<?php echo $quiz['id']; ?>" class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="quiz_edit.php?id=<?php echo $quiz['id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="quiz_results.php?id=<?php echo $quiz['id']; ?>" class="btn btn-outline-info" title="Results">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete" onclick="deleteQuiz(<?php echo $quiz['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteQuiz(quizId) {
            if (confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'quiz_delete.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'quiz_id';
                input.value = quizId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-submit form when filters change
        document.getElementById('course_filter').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('status_filter').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>


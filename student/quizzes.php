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
$filter = $_GET['filter'] ?? 'all';
$course_filter = (int)($_GET['course'] ?? 0);

// Build query based on filters
$conditions = ['e.student_id = ?'];
$params = [$_SESSION['user_id']];

if ($course_filter > 0) {
    $conditions[] = 'c.id = ?';
    $params[] = $course_filter;
}

// Get quizzes with attempt status
$query = "SELECT q.*, c.title as course_title, c.course_code, c.id as course_id,
          u.first_name as instructor_first, u.last_name as instructor_last,
          COUNT(qa.id) as attempt_count,
          MAX(qa.score) as best_score,
          MAX(qa.total_points) as total_points,
          MAX(qa.completed_at) as last_attempt_date,
          (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
          CASE 
            WHEN q.due_date < NOW() AND COUNT(qa.id) = 0 THEN 'overdue'
            WHEN COUNT(qa.id) >= q.max_attempts THEN 'completed'
            WHEN COUNT(qa.id) > 0 THEN 'attempted'
            ELSE 'available'
          END as status
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          JOIN users u ON c.instructor_id = u.id
          LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
          WHERE " . implode(' AND ', $conditions) . " AND e.status = 'enrolled'
          GROUP BY q.id
          ORDER BY q.due_date ASC";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$_SESSION['user_id']], $params));
$all_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter quizzes based on status filter
$quizzes = [];
switch ($filter) {
    case 'available':
        $quizzes = array_filter($all_quizzes, function($q) { return $q['status'] === 'available'; });
        break;
    case 'attempted':
        $quizzes = array_filter($all_quizzes, function($q) { return $q['status'] === 'attempted'; });
        break;
    case 'completed':
        $quizzes = array_filter($all_quizzes, function($q) { return $q['status'] === 'completed'; });
        break;
    case 'overdue':
        $quizzes = array_filter($all_quizzes, function($q) { return $q['status'] === 'overdue'; });
        break;
    default:
        $quizzes = $all_quizzes;
}

// Get enrolled courses for filter
$query = "SELECT c.id, c.title, c.course_code
          FROM courses c
          JOIN enrollments e ON c.id = e.course_id
          WHERE e.student_id = ? AND e.status = 'enrolled'
          ORDER BY c.title";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_quizzes = count($all_quizzes);
$available_quizzes = count(array_filter($all_quizzes, function($q) { return $q['status'] === 'available'; }));
$attempted_quizzes = count(array_filter($all_quizzes, function($q) { return $q['attempt_count'] > 0; }));
$completed_quizzes = count(array_filter($all_quizzes, function($q) { return $q['status'] === 'completed'; }));
$overdue_quizzes = count(array_filter($all_quizzes, function($q) { return $q['status'] === 'overdue'; }));

// Calculate average score
$attempted_with_scores = array_filter($all_quizzes, function($q) { return $q['best_score'] !== null && $q['total_points'] > 0; });
$average_score = 0;
if (count($attempted_with_scores) > 0) {
    $total_percentage = array_sum(array_map(function($q) {
        return ($q['best_score'] / $q['total_points']) * 100;
    }, $attempted_with_scores));
    $average_score = $total_percentage / count($attempted_with_scores);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .quiz-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
        }

        .quiz-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .quiz-card.available {
            border-left: 4px solid #10b981;
        }

        .quiz-card.attempted {
            border-left: 4px solid #f59e0b;
        }

        .quiz-card.completed {
            border-left: 4px solid #6366f1;
        }

        .quiz-card.overdue {
            border-left: 4px solid #ef4444;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
        }

        .stats-number {
            font-size: 2rem;
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

        .filter-btn {
            background: #f1f5f9;
            border: none;
            color: #64748b;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .filter-btn:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .filter-btn.active:hover {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .quiz-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            border: 3px solid;
        }

        .section-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .time-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .attempt-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-take-quiz {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-take-quiz:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-retake-quiz {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-retake-quiz:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(245, 158, 11, 0.3);
            color: white;
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
                                <i class="fas fa-question-circle text-primary me-3"></i>My Quizzes
                            </h1>
                            <p class="text-muted">Take quizzes and track your performance</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="showQuizCalendar()">
                                <i class="fas fa-calendar me-2"></i>Calendar View
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportResults()">
                                <i class="fas fa-download me-2"></i>Export Results
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-primary"><?php echo $total_quizzes; ?></div>
                            <div class="stats-label">Total Quizzes</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?php echo $attempted_quizzes; ?></div>
                            <div class="stats-label">Attempted</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-info"><?php echo $completed_quizzes; ?></div>
                            <div class="stats-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-warning"><?php echo number_format($average_score, 1); ?>%</div>
                            <div class="stats-label">Average Score</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Quizzes</h6>
                            <div class="filter-buttons">
                                <a href="?filter=all&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                    All (<?php echo $total_quizzes; ?>)
                                </a>
                                <a href="?filter=available&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'available' ? 'active' : ''; ?>">
                                    Available (<?php echo $available_quizzes; ?>)
                                </a>
                                <a href="?filter=attempted&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'attempted' ? 'active' : ''; ?>">
                                    In Progress (<?php echo $attempted_quizzes - $completed_quizzes; ?>)
                                </a>
                                <a href="?filter=completed&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                                    Completed (<?php echo $completed_quizzes; ?>)
                                </a>
                                <a href="?filter=overdue&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                                    Overdue (<?php echo $overdue_quizzes; ?>)
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-3"><i class="fas fa-book me-2"></i>Filter by Course</h6>
                            <select class="form-select" id="courseFilter" onchange="filterByCourse(this.value)">
                                <option value="0">All Courses</option>
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Quizzes List -->
                <div data-aos="fade-up" data-aos-delay="200">
                    <?php if (empty($quizzes)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-question-circle text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                            </div>
                            <h5 class="text-muted mb-3">No Quizzes Found</h5>
                            <p class="text-muted">
                                <?php if ($filter !== 'all'): ?>
                                    No quizzes match your current filter. Try changing the filter above.
                                <?php else: ?>
                                    You don't have any quizzes assigned yet. Check back later!
                                <?php endif; ?>
                            </p>
                            <?php if ($filter !== 'all'): ?>
                                <a href="?filter=all" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>View All Quizzes
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($quizzes as $index => $quiz): ?>
                            <div class="quiz-card <?php echo $quiz['status']; ?>" 
                                 data-aos="fade-up" data-aos-delay="<?php echo ($index % 5) * 100; ?>">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <div class="d-flex align-items-start">
                                            <div class="quiz-icon bg-primary text-white">
                                                <i class="fas fa-question"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h5 class="fw-bold mb-0 me-3"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($quiz['course_code']); ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if ($quiz['description']): ?>
                                                    <p class="text-muted mb-2">
                                                        <?php echo htmlspecialchars($quiz['description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex align-items-center text-muted small mb-2">
                                                    <span class="me-3">
                                                        <i class="fas fa-book me-1"></i>
                                                        <?php echo htmlspecialchars($quiz['course_title']); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($quiz['instructor_first'] . ' ' . $quiz['instructor_last']); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-list me-1"></i>
                                                        <?php echo $quiz['question_count']; ?> questions
                                                    </span>
                                                    <?php if ($quiz['due_date']): ?>
                                                        <span class="<?php echo strtotime($quiz['due_date']) < time() ? 'text-danger' : 'text-info'; ?>">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            Due: <?php echo formatDate($quiz['due_date']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-flex gap-2 mb-2">
                                                    <?php if ($quiz['time_limit']): ?>
                                                        <span class="time-badge">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo $quiz['time_limit']; ?> min
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="attempt-badge">
                                                        <i class="fas fa-redo me-1"></i>
                                                        <?php echo $quiz['attempt_count']; ?>/<?php echo $quiz['max_attempts']; ?> attempts
                                                    </span>
                                                </div>

                                                <?php if ($quiz['attempt_count'] > 0 && $quiz['last_attempt_date']): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <small class="text-muted">
                                                            <i class="fas fa-history me-1"></i>
                                                            <strong>Last attempt:</strong> <?php echo formatDate($quiz['last_attempt_date']); ?>
                                                            <?php if ($quiz['best_score'] !== null): ?>
                                                                - Best score: <?php echo $quiz['best_score']; ?>/<?php echo $quiz['total_points']; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-3">
                                            <?php if ($quiz['best_score'] !== null && $quiz['total_points'] > 0): ?>
                                                <div class="text-center">
                                                    <?php
                                                    $percentage = ($quiz['best_score'] / $quiz['total_points']) * 100;
                                                    $score_color = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                                    ?>
                                                    <div class="score-circle border-<?php echo $score_color; ?> text-<?php echo $score_color; ?>">
                                                        <?php echo number_format($percentage, 0); ?>%
                                                    </div>
                                                    <small class="text-muted d-block mt-1">
                                                        Best Score
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex flex-column gap-2">
                                                <?php
                                                switch ($quiz['status']) {
                                                    case 'available':
                                                        echo '<span class="badge bg-success">Available</span>';
                                                        if (strtotime($quiz['due_date']) > time() || !$quiz['due_date']) {
                                                            echo '<a href="quiz_take.php?id=' . $quiz['id'] . '" class="btn btn-sm btn-take-quiz">
                                                                    <i class="fas fa-play me-1"></i>Start Quiz
                                                                  </a>';
                                                        } else {
                                                            echo '<button class="btn btn-sm btn-secondary" disabled>
                                                                    <i class="fas fa-lock me-1"></i>Expired
                                                                  </button>';
                                                        }
                                                        break;
                                                    
                                                    case 'attempted':
                                                        echo '<span class="badge bg-warning text-dark">In Progress</span>';
                                                        if ($quiz['attempt_count'] < $quiz['max_attempts'] && (strtotime($quiz['due_date']) > time() || !$quiz['due_date'])) {
                                                            echo '<a href="quiz_take.php?id=' . $quiz['id'] . '" class="btn btn-sm btn-retake-quiz">
                                                                    <i class="fas fa-redo me-1"></i>Retake
                                                                  </a>';
                                                        } else {
                                                            echo '<button class="btn btn-sm btn-outline-primary" disabled>
                                                                    <i class="fas fa-eye me-1"></i>View Results
                                                                  </button>';
                                                        }
                                                        break;
                                                    
                                                    case 'completed':
                                                        echo '<span class="badge bg-primary">Completed</span>';
                                                        echo '<button class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-chart-bar me-1"></i>View Results
                                                              </button>';
                                                        break;
                                                    
                                                    case 'overdue':
                                                        echo '<span class="badge bg-danger">Overdue</span>';
                                                        echo '<button class="btn btn-sm btn-secondary" disabled>
                                                                <i class="fas fa-ban me-1"></i>Missed
                                                              </button>';
                                                        break;
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <?php if (!empty($quizzes)): ?>
                    <div class="row mt-4" data-aos="fade-up">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-trophy fa-2x text-warning mb-3"></i>
                                    <h6 class="fw-bold mb-2">Study Tips</h6>
                                    <p class="small text-muted mb-3">Review course materials before taking quizzes for better results</p>
                                    <a href="../help/quiz-tips.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-lightbulb me-1"></i>View Tips
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-2x text-info mb-3"></i>
                                    <h6 class="fw-bold mb-2">Performance Report</h6>
                                    <p class="small text-muted mb-3">Track your progress across all courses and subjects</p>
                                    <a href="grades.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-chart-bar me-1"></i>View Report
                                    </a>
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

        // Filter by course function
        function filterByCourse(courseId) {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            window.location.href = `?filter=${currentFilter}&course=${courseId}`;
        }

        // Quiz card hover effects
        document.querySelectorAll('.quiz-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

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
                        stat.textContent = isFloat ? finalNumber.toFixed(1) + '%' : finalNumber;
                        clearInterval(timer);
                    } else {
                        stat.textContent = isFloat ? currentNumber.toFixed(1) + '%' : Math.floor(currentNumber);
                    }
                }, 50);
            });
        });

        // Show quiz calendar (placeholder)
        function showQuizCalendar() {
            alert('Quiz calendar feature will be implemented soon!');
        }

        // Export results (placeholder)
        function exportResults() {
            alert('Export feature will be implemented soon!');
        }

        // Confirmation before taking quiz
        document.querySelectorAll('a[href*="quiz_take.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const quizTitle = this.closest('.quiz-card').querySelector('h5').textContent.trim();
                const timeLimit = this.closest('.quiz-card').querySelector('.time-badge');
                let message = `Are you ready to start "${quizTitle}"?`;
                
                if (timeLimit) {
                    const minutes = timeLimit.textContent.match(/\d+/)[0];
                    message += `\n\nTime limit: ${minutes} minutes`;
                    message += '\nOnce started, the timer cannot be paused.';
                }
                
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        // Show countdown for quizzes due soon
        document.querySelectorAll('.quiz-card').forEach(card => {
            const dueDateElement = card.querySelector('[class*="text-info"], [class*="text-danger"]');
            if (dueDateElement && dueDateElement.textContent.includes('Due:')) {
                const dueDateText = dueDateElement.textContent;
                const dueDateMatch = dueDateText.match(/Due: (.+)/);
                if (dueDateMatch) {
                    const dueDate = new Date(dueDateMatch[1]);
                    const now = new Date();
                    const timeDiff = dueDate - now;
                    
                    if (timeDiff > 0 && timeDiff < 24 * 60 * 60 * 1000) { // Less than 24 hours
                        const hours = Math.floor(timeDiff / (60 * 60 * 1000));
                        const minutes = Math.floor((timeDiff % (60 * 60 * 1000)) / (60 * 1000));
                        
                        const urgencyBadge = document.createElement('span');
                        urgencyBadge.className = 'badge bg-danger ms-2';
                        urgencyBadge.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i>Due in ${hours}h ${minutes}m`;
                        dueDateElement.appendChild(urgencyBadge);
                    }
                }
            }
        });

        // Add pulse animation to overdue quizzes
        document.querySelectorAll('.quiz-card.overdue').forEach(card => {
            card.style.animation = 'pulse 2s infinite';
        });

        // Add CSS for pulse animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
                50% { box-shadow: 0 8px 15px rgba(239, 68, 68, 0.2); }
                100% { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    header('Location: courses.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if student is enrolled in this course
$query = "SELECT e.*, c.*, u.first_name, u.last_name, u.email
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          JOIN users u ON c.instructor_id = u.id
          WHERE e.student_id = ? AND e.course_id = ? AND e.status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $course_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    header('Location: courses.php?error=not_enrolled');
    exit();
}

// Get course materials
$query = "SELECT cm.*, u.first_name, u.last_name
          FROM course_materials cm
          JOIN users u ON cm.uploaded_by = u.id
          WHERE cm.course_id = ?
          ORDER BY cm.upload_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignments for this course
$query = "SELECT a.*, 
          CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status,
          s.grade, s.submitted_at, s.feedback
          FROM assignments a
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE a.course_id = ?
          ORDER BY a.due_date ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $course_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quizzes for this course
$query = "SELECT q.*, 
          COUNT(qa.id) as attempt_count,
          MAX(qa.score) as best_score,
          MAX(qa.total_points) as total_points
          FROM quizzes q
          LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
          WHERE q.course_id = ?
          GROUP BY q.id
          ORDER BY q.due_date ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $course_id]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent forum activity for this course
$query = "SELECT ft.*, u.first_name, u.last_name, fc.name as category_name,
          (SELECT COUNT(*) FROM forum_posts WHERE topic_id = ft.id) as post_count
          FROM forum_topics ft
          JOIN forum_categories fc ON ft.category_id = fc.id
          JOIN users u ON ft.created_by = u.id
          WHERE fc.course_id = ?
          ORDER BY ft.last_post_at DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$course_id]);
$recent_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate course progress
$total_assignments = count($assignments);
$completed_assignments = count(array_filter($assignments, function($a) { return $a['status'] === 'submitted'; }));
$assignment_progress = $total_assignments > 0 ? ($completed_assignments / $total_assignments) * 100 : 0;

$total_quizzes = count($quizzes);
$completed_quizzes = count(array_filter($quizzes, function($q) { return $q['attempt_count'] > 0; }));
$quiz_progress = $total_quizzes > 0 ? ($completed_quizzes / $total_quizzes) * 100 : 0;

$overall_progress = ($assignment_progress + $quiz_progress) / 2;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($enrollment['title']); ?> - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        body {

        .course-header {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .progress-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }

        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
            font-weight: 600;
            color: #64748b;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #6366f1;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Course Header -->
                <div class="course-header" data-aos="fade-down">
                    <div class="position-relative" style="z-index: 2;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h1 class="display-6 fw-bold mb-2"><?php echo htmlspecialchars($enrollment['title']); ?></h1>
                                <p class="mb-1 opacity-90">
                                    <span class="badge bg-white text-dark me-2"><?php echo htmlspecialchars($enrollment['course_code']); ?></span>
                                    <?php echo htmlspecialchars($enrollment['credits']); ?> Credits
                                </p>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-user me-2"></i>
                                    Instructor: <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <div class="progress-circle bg-white text-primary">
                                    <?php echo round($overall_progress); ?>%
                                </div>
                                <small class="d-block mt-2 opacity-90">Overall Progress</small>
                            </div>
                        </div>
                        <p class="mb-0 opacity-90">
                            <?php echo htmlspecialchars($enrollment['description'] ?? 'No description available'); ?>
                        </p>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                        <div class="stat-label">Total Assignments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $completed_assignments; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_quizzes; ?></div>
                        <div class="stat-label">Quizzes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($materials); ?></div>
                        <div class="stat-label">Materials</div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-pills mb-4" data-aos="fade-up">
                    <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview" role="tab">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="materials-tab" data-bs-toggle="pill" href="#materials" role="tab">
                            <i class="fas fa-file-alt me-2"></i>Materials
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assignments-tab" data-bs-toggle="pill" href="#assignments" role="tab">
                            <i class="fas fa-tasks me-2"></i>Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="quizzes-tab" data-bs-toggle="pill" href="#quizzes" role="tab">
                            <i class="fas fa-question-circle me-2"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="discussions-tab" data-bs-toggle="pill" href="#discussions" role="tab">
                            <i class="fas fa-comments me-2"></i>Discussions
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" data-aos="fade-up">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Recent Assignments -->
                                <div class="section-card">
                                    <div class="section-header">
                                        <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Recent Assignments</h5>
                                    </div>
                                    <div class="p-3">
                                        <?php if (empty($assignments)): ?>
                                            <p class="text-muted text-center py-3">No assignments available</p>
                                        <?php else: ?>
                                            <?php foreach (array_slice($assignments, 0, 3) as $assignment): ?>
                                                <div class="item-card">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                            <p class="text-muted small mb-2">
                                                                Due: <?php echo formatDate($assignment['due_date']); ?>
                                                            </p>
                                                            <?php if ($assignment['status'] === 'submitted'): ?>
                                                                <small class="text-success">
                                                                    <i class="fas fa-check-circle me-1"></i>
                                                                    Submitted <?php echo formatDate($assignment['submitted_at']); ?>
                                                                    <?php if ($assignment['grade']): ?>
                                                                        - Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <?php if ($assignment['status'] === 'submitted'): ?>
                                                                <span class="badge badge-status bg-success text-white">Submitted</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-status bg-warning text-dark">Pending</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="text-center mt-3">
                                                <a href="#assignments-tab" onclick="document.getElementById('assignments-tab').click();" class="btn btn-outline-primary btn-sm">
                                                    View All Assignments
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Progress Cards -->
                                <div class="progress-card mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="progress-circle bg-primary text-white me-3">
                                            <?php echo round($assignment_progress); ?>%
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Assignment Progress</h6>
                                            <small class="text-muted"><?php echo $completed_assignments; ?>/<?php echo $total_assignments; ?> completed</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="progress-card">
                                    <div class="d-flex align-items-center">
                                        <div class="progress-circle bg-success text-white me-3">
                                            <?php echo round($quiz_progress); ?>%
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Quiz Progress</h6>
                                            <small class="text-muted"><?php echo $completed_quizzes; ?>/<?php echo $total_quizzes; ?> attempted</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Instructor Contact -->
                                <div class="section-card">
                                    <div class="section-header">
                                        <h6 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>Instructor</h6>
                                    </div>
                                    <div class="p-3 text-center">
                                        <div class="mb-3">
                                            <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="fas fa-user fa-2x"></i>
                                            </div>
                                        </div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></h6>
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($enrollment['email']); ?></p>
                                        <a href="../messages/compose.php?to=<?php echo $enrollment['instructor_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-envelope me-1"></i>Send Message
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Materials Tab -->
                    <div class="tab-pane fade" id="materials" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Course Materials</h5>
                            </div>
                            <div class="p-3">
                                <?php if (empty($materials)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-folder-open text-muted mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <h6 class="text-muted">No materials uploaded yet</h6>
                                        <p class="text-muted small">Course materials will appear here when uploaded by instructor</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($materials as $material): ?>
                                        <div class="item-card">
                                            <div class="d-flex align-items-center">
                                                <div class="file-icon bg-primary text-white">
                                                    <i class="fas fa-file"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                                    <p class="text-muted small mb-1">
                                                        <?php echo htmlspecialchars($material['description'] ?? ''); ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($material['first_name'] . ' ' . $material['last_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo formatDate($material['upload_date']); ?>
                                                        <?php if ($material['file_size']): ?>
                                                            <span class="mx-2">•</span>
                                                            <i class="fas fa-file me-1"></i>
                                                            <?php echo number_format($material['file_size'] / 1024, 1); ?> KB
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php if ($material['file_path']): ?>
                                                    <a href="material_download.php?id=<?php echo $material['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Course Assignments</h5>
                            </div>
                            <div class="p-3">
                                <?php if (empty($assignments)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-tasks text-muted mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <h6 class="text-muted">No assignments available</h6>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="item-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-semibold mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                    <p class="text-muted small mb-2">
                                                        <?php echo htmlspecialchars($assignment['description'] ?? ''); ?>
                                                    </p>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            Due: <?php echo formatDate($assignment['due_date']); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-star me-1"></i>
                                                            <?php echo $assignment['max_points']; ?> points
                                                        </small>
                                                        <?php if ($assignment['status'] === 'submitted' && $assignment['grade']): ?>
                                                            <small class="text-success fw-semibold">
                                                                <i class="fas fa-trophy me-1"></i>
                                                                Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($assignment['feedback']): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <small class="text-muted">
                                                                <strong>Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($assignment['status'] === 'submitted'): ?>
                                                        <span class="badge badge-status bg-success text-white mb-2">Submitted</span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo formatDate($assignment['submitted_at']); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge badge-status bg-warning text-dark mb-2">Pending</span>
                                                        <br>
                                                        <a href="assignment_submit.php?id=<?php echo $assignment['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-upload me-1"></i>Submit
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quizzes Tab -->
                    <div class="tab-pane fade" id="quizzes" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-question-circle me-2 text-primary"></i>Course Quizzes</h5>
                            </div>
                            <div class="p-3">
                                <?php if (empty($quizzes)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-question-circle text-muted mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <h6 class="text-muted">No quizzes available</h6>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <div class="item-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-semibold mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                    <p class="text-muted small mb-2">
                                                        <?php echo htmlspecialchars($quiz['description'] ?? ''); ?>
                                                    </p>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <?php if ($quiz['due_date']): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Due: <?php echo formatDate($quiz['due_date']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($quiz['time_limit']): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo $quiz['time_limit']; ?> minutes
                                                            </small>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-repeat me-1"></i>
                                                            <?php echo $quiz['max_attempts']; ?> attempts allowed
                                                        </small>
                                                        <?php if ($quiz['best_score']): ?>
                                                            <small class="text-success fw-semibold">
                                                                <i class="fas fa-trophy me-1"></i>
                                                                Best: <?php echo $quiz['best_score']; ?>/<?php echo $quiz['total_points']; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($quiz['attempt_count'] > 0): ?>
                                                        <span class="badge badge-status bg-info text-white mb-2">
                                                            <?php echo $quiz['attempt_count']; ?> attempt(s)
                                                        </span>
                                                        <br>
                                                        <?php if ($quiz['attempt_count'] < $quiz['max_attempts']): ?>
                                                            <a href="quiz_take.php?id=<?php echo $quiz['id']; ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="fas fa-play me-1"></i>Retake
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>
                                                                <i class="fas fa-lock me-1"></i>Complete
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <a href="quiz_take.php?id=<?php echo $quiz['id']; ?>" 
                                                           class="btn btn-success btn-sm">
                                                            <i class="fas fa-play me-1"></i>Start Quiz
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Discussions Tab -->
                    <div class="tab-pane fade" id="discussions" role="tabpanel">
                        <div class="section-card">
                            <div class="section-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i>Course Discussions</h5>
                                <a href="../forum/index.php?course=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i>View Forum
                                </a>
                            </div>
                            <div class="p-3">
                                <?php if (empty($recent_topics)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-comments text-muted mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <h6 class="text-muted">No discussions yet</h6>
                                        <p class="text-muted small">Be the first to start a discussion!</p>
                                        <a href="../forum/index.php?course=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>Start Discussion
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <div class="item-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($topic['title']); ?></h6>
                                                    <p class="text-muted small mb-2">
                                                        in <strong><?php echo htmlspecialchars($topic['category_name']); ?></strong>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($topic['first_name'] . ' ' . $topic['last_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo formatDate($topic['created_at']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-comments me-1"></i>
                                                        <?php echo $topic['post_count']; ?> posts
                                                    </small>
                                                </div>
                                                <a href="../forum/topic.php?id=<?php echo $topic['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
            duration: 800,
            once: true,
            offset: 50
        });

        // Tab switching animation
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                AOS.refresh();
            });
        });

        // Item card hover effects
        document.querySelectorAll('.item-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Progress animation
        document.addEventListener('DOMContentLoaded', function() {
            const progressCircles = document.querySelectorAll('.progress-circle');
            progressCircles.forEach(circle => {
                const percentage = circle.textContent.trim();
                const numericValue = parseInt(percentage);
                
                // Add color based on progress
                if (numericValue >= 80) {
                    circle.classList.remove('bg-primary', 'bg-warning');
                    circle.classList.add('bg-success');
                } else if (numericValue >= 50) {
                    circle.classList.remove('bg-primary', 'bg-success');
                    circle.classList.add('bg-warning', 'text-dark');
                }
            });
        });
    </script>
</body>
</html>

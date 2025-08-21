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

$status_condition = '';
switch ($filter) {
    case 'pending':
        $status_condition = 'AND s.id IS NULL';
        break;
    case 'submitted':
        $status_condition = 'AND s.id IS NOT NULL';
        break;
    case 'graded':
        $status_condition = 'AND s.id IS NOT NULL AND s.grade IS NOT NULL';
        break;
    case 'overdue':
        $status_condition = 'AND s.id IS NULL AND a.due_date < NOW()';
        break;
}

// Get assignments with submission status
$query = "SELECT a.*, c.title as course_title, c.course_code, c.id as course_id,
          u.first_name as instructor_first, u.last_name as instructor_last,
          CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status,
          s.grade, s.submitted_at, s.feedback, s.submission_text,
          CASE WHEN a.due_date < NOW() AND s.id IS NULL THEN 1 ELSE 0 END as is_overdue
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          JOIN users u ON c.instructor_id = u.id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE " . implode(' AND ', $conditions) . " AND e.status = 'enrolled'
          $status_condition
          ORDER BY a.due_date ASC";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$_SESSION['user_id']], $params));
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$total_assignments = count($assignments);
$submitted_assignments = count(array_filter($assignments, function($a) { return $a['status'] === 'submitted'; }));
$graded_assignments = count(array_filter($assignments, function($a) { return $a['grade'] !== null; }));
$overdue_assignments = count(array_filter($assignments, function($a) { return $a['is_overdue']; }));

// Calculate average grade
$graded_scores = array_filter(array_column($assignments, 'grade'), function($grade) { return $grade !== null; });
$average_grade = $graded_scores ? array_sum($graded_scores) / count($graded_scores) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        body {

        .assignment-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
        }

        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .assignment-card.overdue {
            border-left: 4px solid #ef4444;
        }

        .assignment-card.submitted {
            border-left: 4px solid #10b981;
        }

        .assignment-card.graded {
            border-left: 4px solid #6366f1;
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

        .priority-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid white;
        }

        .priority-high { background: #ef4444; }
        .priority-medium { background: #f59e0b; }
        .priority-low { background: #10b981; }

        .grade-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .section-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
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
                                <i class="fas fa-tasks text-primary me-3"></i>My Assignments
                            </h1>
                            <p class="text-muted">Track and manage your course assignments</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#calendarModal">
                                <i class="fas fa-calendar me-2"></i>Calendar View
                            </button>
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-primary"><?php echo $total_assignments; ?></div>
                            <div class="stats-label">Total Assignments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?php echo $submitted_assignments; ?></div>
                            <div class="stats-label">Submitted</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-info"><?php echo $graded_assignments; ?></div>
                            <div class="stats-label">Graded</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-warning"><?php echo number_format($average_grade, 1); ?>%</div>
                            <div class="stats-label">Average Grade</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Assignments</h6>
                            <div class="filter-buttons">
                                <a href="?filter=all&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                    All (<?php echo $total_assignments; ?>)
                                </a>
                                <a href="?filter=pending&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                                    Pending (<?php echo $total_assignments - $submitted_assignments; ?>)
                                </a>
                                <a href="?filter=submitted&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'submitted' ? 'active' : ''; ?>">
                                    Submitted (<?php echo $submitted_assignments; ?>)
                                </a>
                                <a href="?filter=graded&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'graded' ? 'active' : ''; ?>">
                                    Graded (<?php echo $graded_assignments; ?>)
                                </a>
                                <a href="?filter=overdue&course=<?php echo $course_filter; ?>" 
                                   class="filter-btn <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                                    Overdue (<?php echo $overdue_assignments; ?>)
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

                <!-- Assignments List -->
                <div data-aos="fade-up" data-aos-delay="200">
                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-tasks text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                            </div>
                            <h5 class="text-muted mb-3">No Assignments Found</h5>
                            <p class="text-muted">
                                <?php if ($filter !== 'all'): ?>
                                    No assignments match your current filter. Try changing the filter above.
                                <?php else: ?>
                                    You don't have any assignments yet. Check back later!
                                <?php endif; ?>
                            </p>
                            <?php if ($filter !== 'all'): ?>
                                <a href="?filter=all" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>View All Assignments
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assignments as $index => $assignment): ?>
                            <div class="assignment-card <?php echo $assignment['status']; ?> 
                                 <?php echo $assignment['is_overdue'] ? 'overdue' : ''; ?>"
                                 data-aos="fade-up" data-aos-delay="<?php echo ($index % 5) * 100; ?>">
                                
                                <!-- Priority indicator -->
                                <?php
                                $due_date = strtotime($assignment['due_date']);
                                $days_until_due = ($due_date - time()) / (60 * 60 * 24);
                                
                                if ($assignment['status'] === 'pending') {
                                    if ($days_until_due < 0) {
                                        $priority_class = 'priority-high';
                                    } elseif ($days_until_due <= 3) {
                                        $priority_class = 'priority-medium';
                                    } else {
                                        $priority_class = 'priority-low';
                                    }
                                    echo '<div class="priority-badge ' . $priority_class . '"></div>';
                                }
                                ?>

                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h5 class="fw-bold mb-0 me-3"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($assignment['course_code']); ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="text-muted mb-2">
                                                    <?php echo htmlspecialchars($assignment['description'] ?? ''); ?>
                                                </p>
                                                
                                                <div class="d-flex align-items-center text-muted small">
                                                    <span class="me-3">
                                                        <i class="fas fa-book me-1"></i>
                                                        <?php echo htmlspecialchars($assignment['course_title']); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($assignment['instructor_first'] . ' ' . $assignment['instructor_last']); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-star me-1"></i>
                                                        <?php echo $assignment['max_points']; ?> points
                                                    </span>
                                                    <span class="<?php echo $assignment['is_overdue'] ? 'text-danger' : 'text-info'; ?>">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Due: <?php echo formatDate($assignment['due_date']); ?>
                                                        <?php if ($assignment['is_overdue']): ?>
                                                            <strong>(OVERDUE)</strong>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>

                                                <?php if ($assignment['status'] === 'submitted'): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <small class="text-muted">
                                                            <i class="fas fa-check-circle text-success me-1"></i>
                                                            <strong>Submitted:</strong> <?php echo formatDate($assignment['submitted_at']); ?>
                                                        </small>
                                                        <?php if ($assignment['feedback']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-comment me-1"></i>
                                                                <strong>Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-3">
                                            <?php if ($assignment['grade'] !== null): ?>
                                                <div class="text-center">
                                                    <?php
                                                    $percentage = ($assignment['grade'] / $assignment['max_points']) * 100;
                                                    $grade_color = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                                    ?>
                                                    <div class="grade-circle bg-<?php echo $grade_color; ?> text-white">
                                                        <?php echo number_format($percentage, 0); ?>%
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex flex-column gap-2">
                                                <?php if ($assignment['status'] === 'submitted'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Submitted
                                                    </span>
                                                    <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                <?php else: ?>
                                                    <?php if ($assignment['is_overdue']): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                    <a href="assignment_submit.php?id=<?php echo $assignment['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-upload me-1"></i>Submit Now
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination would go here if needed -->
                        <?php if (count($assignments) > 10): ?>
                            <nav aria-label="Assignment pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <!-- Pagination implementation -->
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Calendar Modal -->
    <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="calendarModalLabel">
                        <i class="fas fa-calendar me-2"></i>Assignment Calendar
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                        <p>Calendar view feature coming soon!</p>
                    </div>
                </div>
            </div>
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

        // Assignment card hover effects
        document.querySelectorAll('.assignment-card').forEach(card => {
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

        // Add pulse animation to overdue assignments
        document.querySelectorAll('.assignment-card.overdue').forEach(card => {
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

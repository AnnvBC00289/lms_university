<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get current week
$current_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get student's enrolled courses
$query = "SELECT c.*, e.enrollment_date, u.first_name, u.last_name
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          JOIN users u ON c.instructor_id = u.id
          WHERE e.student_id = ? AND e.status = 'enrolled' AND c.status = 'active'
          ORDER BY c.course_code";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming assignments
$query = "SELECT a.*, c.title as course_title, c.course_code
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          WHERE e.student_id = ? AND e.status = 'enrolled'
          AND a.due_date >= NOW()
          ORDER BY a.due_date ASC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$upcoming_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming quizzes
$query = "SELECT q.*, c.title as course_title, c.course_code
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          WHERE e.student_id = ? AND e.status = 'enrolled'
          AND q.due_date >= NOW()
          ORDER BY q.due_date ASC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$upcoming_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate week days
function getWeekDays($week, $year) {
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $ret = [];
    for ($i = 0; $i < 7; $i++) {
        $ret[] = $dto->format('Y-m-d');
        $dto->add(new DateInterval('P1D'));
    }
    return $ret;
}

$week_days = getWeekDays($current_week, $current_year);
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            gap: 1px;
            background: #dee2e6;
            border: 1px solid #dee2e6;
        }
        .schedule-header {
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .schedule-cell {
            background: white;
            padding: 10px;
            min-height: 120px;
            position: relative;
        }
        .schedule-cell.today {
            background: #e3f2fd;
        }
        .time-slot {
            background: #f8f9fa;
            padding: 5px;
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .course-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-bottom: 2px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .course-item:hover {
            transform: scale(1.02);
        }
        .event-item {
            background: #ff6b6b;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            margin-bottom: 2px;
        }
        .week-navigation {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        My Schedule
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="exportSchedule()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printSchedule()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="week-navigation">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-week me-2"></i>
                                Week <?php echo $current_week; ?>, <?php echo $current_year; ?>
                            </h5>
                            <small class="text-muted">
                                <?php echo date('M j', strtotime($week_days[0])); ?> - 
                                <?php echo date('M j, Y', strtotime($week_days[6])); ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <a href="?week=<?php echo $current_week - 1; ?>&year=<?php echo $current_year; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="?week=<?php echo date('W'); ?>&year=<?php echo date('Y'); ?>" 
                                   class="btn btn-outline-secondary">
                                    Today
                                </a>
                                <a href="?week=<?php echo $current_week + 1; ?>&year=<?php echo $current_year; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Enrolled Courses</h6>
                                        <h3 class="mb-0"><?php echo count($courses); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-book fa-2x"></i>
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
                                        <h6 class="card-title">Upcoming Assignments</h6>
                                        <h3 class="mb-0"><?php echo count($upcoming_assignments); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tasks fa-2x"></i>
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
                                        <h6 class="card-title">Upcoming Quizzes</h6>
                                        <h3 class="mb-0"><?php echo count($upcoming_quizzes); ?></h3>
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
                                        <h6 class="card-title">This Week</h6>
                                        <h3 class="mb-0"><?php echo count(array_filter($upcoming_assignments, function($a) use ($week_days) { 
                                            return in_array(date('Y-m-d', strtotime($a['due_date'])), $week_days); 
                                        })); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Schedule Grid -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>Weekly Schedule
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="schedule-grid">
                                    <!-- Time column header -->
                                    <div class="schedule-header">Time</div>
                                    
                                    <!-- Day headers -->
                                    <?php foreach ($day_names as $index => $day): ?>
                                        <div class="schedule-header <?php echo (date('Y-m-d') === $week_days[$index]) ? 'today' : ''; ?>">
                                            <?php echo $day; ?><br>
                                            <small><?php echo date('M j', strtotime($week_days[$index])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Time slots -->
                                    <?php 
                                    $time_slots = [
                                        '08:00' => '08:00-09:00',
                                        '09:00' => '09:00-10:00',
                                        '10:00' => '10:00-11:00',
                                        '11:00' => '11:00-12:00',
                                        '12:00' => '12:00-13:00',
                                        '13:00' => '13:00-14:00',
                                        '14:00' => '14:00-15:00',
                                        '15:00' => '15:00-16:00',
                                        '16:00' => '16:00-17:00'
                                    ];
                                    
                                    foreach ($time_slots as $time => $slot): ?>
                                        <div class="time-slot"><?php echo $slot; ?></div>
                                        <?php foreach ($day_names as $index => $day): ?>
                                            <div class="schedule-cell <?php echo (date('Y-m-d') === $week_days[$index]) ? 'today' : ''; ?>">
                                                <?php
                                                // Placeholder for course scheduling
                                                // In a real system, you would have a courses_schedule table
                                                $course_schedule = [
                                                    'Monday' => ['08:00' => 'CS101', '10:00' => 'MATH201'],
                                                    'Tuesday' => ['09:00' => 'ENG101', '14:00' => 'CS101'],
                                                    'Wednesday' => ['08:00' => 'MATH201', '13:00' => 'ENG101'],
                                                    'Thursday' => ['10:00' => 'CS101', '15:00' => 'MATH201'],
                                                    'Friday' => ['09:00' => 'ENG101', '14:00' => 'CS101']
                                                ];
                                                
                                                if (isset($course_schedule[$day][$time])) {
                                                    $course_code = $course_schedule[$day][$time];
                                                    echo "<div class='course-item' title='$course_code'>$course_code</div>";
                                                }
                                                ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-md-4">
                        <!-- Upcoming Assignments -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-tasks me-2"></i>Upcoming Assignments
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_assignments)): ?>
                                    <p class="text-muted small">No upcoming assignments</p>
                                <?php else: ?>
                                    <?php foreach ($upcoming_assignments as $assignment): ?>
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($assignment['course_code']); ?>
                                                </small>
                                            </div>
                                            <small class="text-danger">
                                                <?php echo date('M j', strtotime($assignment['due_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Quizzes -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-question-circle me-2"></i>Upcoming Quizzes
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_quizzes)): ?>
                                    <p class="text-muted small">No upcoming quizzes</p>
                                <?php else: ?>
                                    <?php foreach ($upcoming_quizzes as $quiz): ?>
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($quiz['course_code']); ?>
                                                </small>
                                            </div>
                                            <small class="text-warning">
                                                <?php echo date('M j', strtotime($quiz['due_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="assignments.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-tasks me-2"></i>View All Assignments
                                    </a>
                                    <a href="quizzes.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-question-circle me-2"></i>View All Quizzes
                                    </a>
                                    <a href="courses.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-book me-2"></i>My Courses
                                    </a>
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
        function exportSchedule() {
            alert('Export functionality will be implemented soon!');
        }

        function printSchedule() {
            window.print();
        }

        // Add click handlers for course items
        document.addEventListener('DOMContentLoaded', function() {
            const courseItems = document.querySelectorAll('.course-item');
            courseItems.forEach(item => {
                item.addEventListener('click', function() {
                    const courseCode = this.textContent;
                    alert(`Course: ${courseCode}\nThis would show course details in a modal.`);
                });
            });
        });
    </script>
</body>
</html>


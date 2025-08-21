<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get course ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Get course details
$query = "SELECT c.*, u.first_name, u.last_name,
          COUNT(e.id) as total_enrollments,
          COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as active_enrollments
          FROM courses c 
          JOIN users u ON c.instructor_id = u.id
          LEFT JOIN enrollments e ON c.id = e.course_id 
          WHERE c.id = ? AND c.instructor_id = ?
          GROUP BY c.id";
$stmt = $db->prepare($query);
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get enrolled students
$students_query = "SELECT u.first_name, u.last_name, u.email, e.enrollment_date, e.final_grade, e.status
                  FROM enrollments e
                  JOIN users u ON e.student_id = u.id
                  WHERE e.course_id = ?
                  ORDER BY e.enrollment_date DESC";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute([$course_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignments for this course
$assignments_query = "SELECT a.*, COUNT(s.id) as submissions_count
                     FROM assignments a
                     LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
                     WHERE a.course_id = ?
                     GROUP BY a.id
                     ORDER BY a.due_date ASC";
$assignments_stmt = $db->prepare($assignments_query);
$assignments_stmt->execute([$course_id]);
$assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity for this course
$activity_query = "SELECT 'assignment_submission' as type, s.submitted_at as date, 
                  CONCAT(u.first_name, ' ', u.last_name, ' submitted ', a.title) as description
                  FROM assignment_submissions s
                  JOIN users u ON s.student_id = u.id
                  JOIN assignments a ON s.assignment_id = a.id
                  WHERE a.course_id = ?
                  UNION ALL
                  SELECT 'enrollment' as type, e.enrollment_date as date,
                  CONCAT(u.first_name, ' ', u.last_name, ' enrolled in course') as description
                  FROM enrollments e
                  JOIN users u ON e.student_id = u.id
                  WHERE e.course_id = ?
                  ORDER BY date DESC LIMIT 10";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->execute([$course_id, $course_id]);
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0891b2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

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

        .course-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .course-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .stat-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
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

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary);
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Course Hero Section -->
                <div class="course-hero" data-aos="fade-down">
                    <div class="row align-items-center">
                        <div class="col-lg-8" style="position: relative; z-index: 2;">
                            <div class="mb-3">
                                <span class="badge bg-white text-primary me-2">
                                    <?php echo htmlspecialchars($course['course_code']); ?>
                                </span>
                                <span class="badge bg-white text-success">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                            <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                            <p class="mb-3 opacity-90"><?php echo htmlspecialchars($course['description']); ?></p>
                            <div class="d-flex gap-4">
                                <div>
                                    <i class="fas fa-credit-card me-2"></i>
                                    <?php echo $course['credits']; ?> Credits
                                </div>
                                <div>
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo $course['semester'] . ' ' . $course['year']; ?>
                                </div>
                                <div>
                                    <i class="fas fa-users me-2"></i>
                                    <?php echo $course['active_enrollments']; ?>/<?php echo $course['max_students']; ?> Students
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center" style="position: relative; z-index: 2;">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="course_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-light">
                                    <i class="fas fa-edit me-2"></i>Edit Course
                                </a>
                                <a href="courses.php" class="btn btn-outline-light">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Row -->
                <div class="row mb-4" data-aos="fade-up">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $course['active_enrollments']; ?></div>
                            <div class="stat-label">Active Students</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo count($assignments); ?></div>
                            <div class="stat-label">Assignments</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number">
                                <?php 
                                $completion_rate = $course['active_enrollments'] > 0 ? 
                                    round(($course['active_enrollments'] / $course['max_students']) * 100) : 0;
                                echo $completion_rate;
                                ?>%
                            </div>
                            <div class="stat-label">Enrollment Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo count($activities); ?></div>
                            <div class="stat-label">Recent Activities</div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- Students List -->
                    <div class="col-lg-8 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-success mb-0">
                                    <i class="fas fa-users me-2"></i>Enrolled Students
                                </h5>
                                <span class="badge bg-success"><?php echo count($students); ?> Students</span>
                            </div>
                            
                            <?php if (empty($students)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No students enrolled yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Email</th>
                                                <th>Enrolled Date</th>
                                                <th>Status</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td class="fw-semibold">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($student['enrollment_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-modern <?php echo $student['status'] == 'enrolled' ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo ucfirst($student['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['final_grade']): ?>
                                                            <span class="fw-bold text-primary"><?php echo $student['final_grade']; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not graded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Assignments List -->
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-success mb-0">
                                    <i class="fas fa-tasks me-2"></i>Course Assignments
                                </h5>
                                <a href="assignment_create.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus me-1"></i>Add Assignment
                                </a>
                            </div>
                            
                            <?php if (empty($assignments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No assignments created yet</p>
                                    <a href="assignment_create.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Create First Assignment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Assignment Title</th>
                                                <th>Due Date</th>
                                                <th>Submissions</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignments as $assignment): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $assignment['submissions_count']; ?> submissions</span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $due_date = strtotime($assignment['due_date']);
                                                        $now = time();
                                                        if ($due_date > $now) {
                                                            echo '<span class="badge bg-success">Active</span>';
                                                        } else {
                                                            echo '<span class="badge bg-secondary">Closed</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-clipboard-check"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="info-card">
                            <h5 class="fw-bold text-success mb-3">
                                <i class="fas fa-clock me-2"></i>Recent Activity
                            </h5>
                            
                            <?php if (empty($activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                    <p class="text-muted small">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline-modern">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="timeline-item">
                                            <h6 class="fw-semibold mb-1">
                                                <?php echo $activity['type'] == 'enrollment' ? 'New Enrollment' : 'Assignment Submission'; ?>
                                            </h6>
                                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Course Progress Chart -->
                        <div class="info-card">
                            <h5 class="fw-bold text-success mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Course Progress
                            </h5>
                            <canvas id="progressChart" height="200"></canvas>
                        </div>
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

        // Progress Chart
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Enrolled', 'Available Spots'],
                datasets: [{
                    data: [<?php echo $course['active_enrollments']; ?>, <?php echo $course['max_students'] - $course['active_enrollments']; ?>],
                    backgroundColor: ['#059669', '#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
                cutout: '70%'
            }
        });
    </script>
</body>
</html>

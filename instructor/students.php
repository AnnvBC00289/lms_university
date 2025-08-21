<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle student actions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['enrollment_id'])) {
        $action = $_POST['action'];
        $enrollment_id = (int)$_POST['enrollment_id'];
        
        // Verify enrollment belongs to instructor's course
        $verify_query = "SELECT e.id FROM enrollments e 
                        JOIN courses c ON e.course_id = c.id 
                        WHERE e.id = ? AND c.instructor_id = ?";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->execute([$enrollment_id, $_SESSION['user_id']]);
        
        if ($verify_stmt->rowCount() > 0) {
            switch ($action) {
                case 'approve':
                    $update_query = "UPDATE enrollments SET status = 'enrolled' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if ($update_stmt->execute([$enrollment_id])) {
                        $message = "Student enrollment approved successfully.";
                    } else {
                        $error = "Failed to approve enrollment.";
                    }
                    break;
                    
                case 'suspend':
                    $update_query = "UPDATE enrollments SET status = 'suspended' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if ($update_stmt->execute([$enrollment_id])) {
                        $message = "Student enrollment suspended.";
                    } else {
                        $error = "Failed to suspend enrollment.";
                    }
                    break;
                    
                case 'reactivate':
                    $update_query = "UPDATE enrollments SET status = 'enrolled' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if ($update_stmt->execute([$enrollment_id])) {
                        $message = "Student enrollment reactivated.";
                    } else {
                        $error = "Failed to reactivate enrollment.";
                    }
                    break;
                    
                case 'remove':
                    $delete_query = "DELETE FROM enrollments WHERE id = ?";
                    $delete_stmt = $db->prepare($delete_query);
                    if ($delete_stmt->execute([$enrollment_id])) {
                        $message = "Student removed from course.";
                    } else {
                        $error = "Failed to remove student.";
                    }
                    break;
            }
        } else {
            $error = "Invalid enrollment ID.";
        }
    }
}

// Get instructor's courses for filter
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build students query with filters
$where_conditions = ["c.instructor_id = ?"];
$params = [$_SESSION['user_id']];

if ($course_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get students with enrollment info and course details
$query = "SELECT u.*, e.id as enrollment_id, e.enrollment_date, e.status as enrollment_status, 
          e.final_grade, c.title as course_title, c.course_code, c.id as course_id,
          (SELECT COUNT(*) FROM assignment_submissions sub 
           JOIN assignments a ON sub.assignment_id = a.id 
           WHERE sub.student_id = u.id AND a.course_id = c.id) as assignments_submitted,
          (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as total_assignments,
          (SELECT AVG(sub.grade) FROM assignment_submissions sub 
           JOIN assignments a ON sub.assignment_id = a.id 
           WHERE sub.student_id = u.id AND a.course_id = c.id AND sub.grade IS NOT NULL) as avg_grade
          FROM users u 
          JOIN enrollments e ON u.id = e.student_id
          JOIN courses c ON e.course_id = c.id
          WHERE $where_clause AND u.role = 'student'
          ORDER BY c.course_code, u.last_name, u.first_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
                COUNT(DISTINCT u.id) as total_students,
                COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as active_students,
                COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_students,
                COUNT(CASE WHEN e.status = 'suspended' THEN 1 END) as suspended_students
                FROM users u 
                JOIN enrollments e ON u.id = e.student_id
                JOIN courses c ON e.course_id = c.id
                WHERE c.instructor_id = ? AND u.role = 'student'";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0891b2;
        }

        body {

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

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-total { color: var(--primary); }
        .stat-active { color: #0891b2; }
        .stat-pending { color: #f59e0b; }
        .stat-suspended { color: #dc2626; }

        .filters-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .student-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .student-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .student-info h5 {
            margin: 0;
            font-weight: 700;
            color: #1f2937;
        }

        .student-info .text-muted {
            font-size: 0.875rem;
        }

        .student-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .meta-item i {
            color: var(--primary);
        }

        .student-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .stat-item-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-item-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .enrollment-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-enrolled {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .student-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .grade-display {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            text-align: center;
            min-width: 80px;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        @media (max-width: 768px) {
            .student-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header" data-aos="fade-down">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="fw-bold mb-2" style="color: #1f2937;">
                                <i class="fas fa-user-graduate text-success me-3"></i>My Students
                            </h1>
                            <p class="text-muted mb-0">Manage students enrolled in your courses</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="reports.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                            <a href="courses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-book me-2"></i>Manage Courses
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Row -->
                <div class="row stats-row" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-total"><?php echo $stats['total_students']; ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-active"><?php echo $stats['active_students']; ?></div>
                            <div class="stat-label">Active Enrollments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-pending"><?php echo $stats['pending_students']; ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-suspended"><?php echo $stats['suspended_students']; ?></div>
                            <div class="stat-label">Suspended</div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success" data-aos="fade-up">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" data-aos="fade-up">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filters-card" data-aos="fade-up">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="course" class="form-label">Filter by Course</label>
                            <select class="form-select" id="course" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Students</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Name, email, or student ID">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-outline-primary me-2">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Students List -->
                <?php if (empty($students)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Students Found</h3>
                        <p>No students are enrolled in your courses or match your search criteria.</p>
                        <a href="courses.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus me-2"></i>Manage Your Courses
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($students as $index => $student): ?>
                            <div class="col-lg-6 col-xl-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-info flex-grow-1">
                                            <h5><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                            <div class="text-muted"><?php echo htmlspecialchars($student['email']); ?></div>
                                            <?php if ($student['student_id']): ?>
                                                <small class="text-muted">ID: <?php echo htmlspecialchars($student['student_id']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="enrollment-status status-<?php echo $student['enrollment_status']; ?>">
                                                <?php echo ucfirst($student['enrollment_status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="student-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-book"></i>
                                            <span><?php echo htmlspecialchars($student['course_code']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <span>Enrolled <?php echo date('M j, Y', strtotime($student['enrollment_date'])); ?></span>
                                        </div>
                                    </div>

                                    <div class="student-stats">
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $student['assignments_submitted']; ?>/<?php echo $student['total_assignments']; ?></div>
                                            <div class="stat-item-label">Assignments</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $student['avg_grade'] ? round($student['avg_grade'], 1) : 'N/A'; ?></div>
                                            <div class="stat-item-label">Avg Grade</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $student['final_grade'] ?: 'N/A'; ?></div>
                                            <div class="stat-item-label">Final Grade</div>
                                        </div>
                                    </div>

                                    <div class="student-actions">
                                        <a href="student_profile.php?id=<?php echo $student['id']; ?>&course=<?php echo $student['course_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-user me-1"></i>Profile
                                        </a>
                                        
                                        <a href="student_grades.php?id=<?php echo $student['id']; ?>&course=<?php echo $student['course_id']; ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-clipboard-check me-1"></i>Grades
                                        </a>
                                        
                                        <?php if ($student['enrollment_status'] == 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-outline-success btn-sm" 
                                                        onclick="return confirm('Approve this student enrollment?')">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                            </form>
                                        <?php elseif ($student['enrollment_status'] == 'enrolled'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-outline-warning btn-sm"
                                                        onclick="return confirm('Suspend this student enrollment?')">
                                                    <i class="fas fa-pause me-1"></i>Suspend
                                                </button>
                                            </form>
                                        <?php elseif ($student['enrollment_status'] == 'suspended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                <input type="hidden" name="action" value="reactivate">
                                                <button type="submit" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-play me-1"></i>Reactivate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Remove this student from the course? This action cannot be undone.')">
                                                <i class="fas fa-trash me-1"></i>Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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

        // Add hover effects
        document.querySelectorAll('.student-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-submit search form on enter
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>

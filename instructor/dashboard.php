<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get instructor's courses
$query = "SELECT c.*, COUNT(e.id) as enrolled_students 
          FROM courses c 
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
          WHERE c.instructor_id = ? 
          GROUP BY c.id";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assignments
$query = "SELECT a.*, c.title as course_title, COUNT(s.id) as submissions
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
          WHERE c.instructor_id = ?
          GROUP BY a.id
          ORDER BY a.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending grading count
$query = "SELECT COUNT(*) as pending_count
          FROM assignment_submissions s
          JOIN assignments a ON s.assignment_id = a.id
          JOIN courses c ON a.course_id = c.id
          WHERE c.instructor_id = ? AND s.grade IS NULL";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$pending_grading = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get total students across all courses
$query = "SELECT COUNT(DISTINCT e.student_id) as total_students
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          WHERE c.instructor_id = ? AND e.status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - University LMS</title>
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
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
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
            border-radius: 0;
        }

        /* Modern Card Styles for Instructor */
        .instructor-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--gray-200);
            transition: all 0.4s ease;
            overflow: hidden;
        }

        .instructor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .card-header-instructor {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
        }

        .stats-card-instructor {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card-instructor::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stats-card-instructor:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        .instructor-stats-icon {
            width: 90px;
            height: 90px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            margin-left: auto;
        }

        .instructor-stats-number {
            font-size: 3rem;
            font-weight: 900;
            color: var(--gray-900);
            line-height: 1;
        }

        .instructor-stats-label {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .welcome-card-instructor {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 24px;
            padding: 3rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-card-instructor::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -25%;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .course-table-modern {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .course-table-modern thead th {
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
            border: none;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            padding: 1.2rem;
            color: var(--gray-800);
        }

        .course-table-modern tbody td {
            border: none;
            padding: 1.2rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .quick-actions-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--gray-200);
        }

        .action-btn {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            text-decoration: none;
            display: block;
            margin-bottom: 1rem;
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }

        .page-header-instructor {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
        }

        .btn-instructor {
            border-radius: 14px;
            padding: 0.8rem 1.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-instructor:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-instructor.btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: transparent;
        }

        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        /* Prevent content overflow */
        .row {
            margin-left: 0;
            margin-right: 0;
        }
        
        .col-lg-4, .col-lg-8, .col-md-6, .col-xl-3 {
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (max-width: 768px) {
            .stats-card-instructor {
                padding: 2rem;
            }
            .welcome-card-instructor {
                padding: 2.5rem;
            }
            .chart-container {
                height: 300px !important;
            }
            .chart-container div {
                height: 220px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header-instructor" data-aos="fade-down">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="fw-bold mb-2" style="font-size: 2.2rem; color: var(--gray-900);">
                                <i class="fas fa-chalkboard-teacher text-success me-3"></i>Instructor Dashboard
                    </h1>
                            <p class="text-muted mb-0" style="font-size: 1.1rem;">Manage your courses and track student progress</p>
                        </div>
                        <div class="btn-group">
                            <a href="course_create.php" class="btn btn-success btn-instructor">
                                <i class="fas fa-plus me-2"></i>New Course
                            </a>
                            <a href="assignment_create.php" class="btn btn-outline-success btn-instructor">
                                <i class="fas fa-tasks me-2"></i>New Assignment
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Welcome Section -->
                <div class="row mb-5" data-aos="fade-up">
                    <div class="col-12">
                        <div class="welcome-card-instructor">
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="position: relative; z-index: 2;">
                                    <h3 class="fw-bold mb-3">
                                        <i class="fas fa-chalkboard-teacher me-3"></i>
                                        Welcome back, Professor <?php echo $_SESSION['last_name']; ?>!
                                    </h3>
                                    <p class="mb-3 opacity-90" style="font-size: 1.1rem;">
                                        You are currently teaching <?php echo count($courses); ?> courses with 
                                        <?php echo $total_students; ?> total students enrolled across all your classes.
                                    </p>
                                    <div class="d-flex gap-3">
                                        <div class="badge bg-white text-success px-3 py-2">
                                            <i class="fas fa-book me-2"></i>
                                            <?php echo count($courses); ?> Active Courses
                                        </div>
                                        <div class="badge bg-white text-info px-3 py-2">
                                            <i class="fas fa-users me-2"></i>
                                            <?php echo $total_students; ?> Students
                                        </div>
                                        <?php if ($pending_grading > 0): ?>
                                        <div class="badge bg-white text-warning px-3 py-2">
                                            <i class="fas fa-clipboard-check me-2"></i>
                                            <?php echo $pending_grading; ?> To Grade
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-none d-md-block" style="position: relative; z-index: 2;">
                                    <i class="fas fa-user-tie" style="font-size: 5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-5">
                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="stats-card-instructor">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="instructor-stats-label mb-2">Active Courses</div>
                                    <div class="instructor-stats-number"><?php echo count($courses); ?></div>
                                    <small class="text-success">
                                        <i class="fas fa-arrow-up me-1"></i>Currently teaching
                                    </small>
                                    </div>
                                <div class="instructor-stats-icon" style="background: linear-gradient(135deg, #059669, #047857);">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="stats-card-instructor">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="instructor-stats-label mb-2">Total Students</div>
                                    <div class="instructor-stats-number"><?php echo $total_students; ?></div>
                                    <small class="text-info">
                                        <i class="fas fa-users me-1"></i>Across all courses
                                    </small>
                                    </div>
                                <div class="instructor-stats-icon" style="background: linear-gradient(135deg, #0891b2, #0e7490);">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="stats-card-instructor">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="instructor-stats-label mb-2">Assignments Created</div>
                                    <div class="instructor-stats-number"><?php echo count($recent_assignments); ?></div>
                                    <small class="text-primary">
                                        <i class="fas fa-tasks me-1"></i>Recent activity
                                    </small>
                                    </div>
                                <div class="instructor-stats-icon" style="background: linear-gradient(135deg, #7c3aed, #6d28d9);">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="stats-card-instructor">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="instructor-stats-label mb-2">Pending Grading</div>
                                    <div class="instructor-stats-number"><?php echo $pending_grading; ?></div>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>Needs attention
                                    </small>
                                    </div>
                                <div class="instructor-stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- My Courses -->
                    <div class="col-lg-8 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="instructor-card">
                            <div class="card-header-instructor">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="m-0 fw-bold d-flex align-items-center">
                                            <i class="fas fa-book me-3"></i>My Courses
                                        </h5>
                                        <p class="mb-0 opacity-90 small">Manage and monitor your teaching courses</p>
                                    </div>
                                    <a href="course_create.php" class="btn btn-light btn-instructor">
                                        <i class="fas fa-plus me-2"></i>Add Course
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <?php if (empty($courses)): ?>
                                    <div class="text-center py-5">
                                        <div class="mb-4">
                                            <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                                        </div>
                                        <h5 class="text-muted mb-3">No Courses Created</h5>
                                        <p class="text-muted mb-4">Start your teaching journey by creating your first course!</p>
                                        <a href="course_create.php" class="btn btn-success btn-instructor">
                                            <i class="fas fa-plus me-2"></i>Create Your First Course
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table course-table-modern">
                                            <thead>
                                                <tr>
                                                    <th>Course Information</th>
                                                    <th>Course Code</th>
                                                    <th>Students</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($courses as $course): ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-calendar me-1"></i>
                                                                    <?php echo htmlspecialchars($course['semester'] . ' ' . $course['year']); ?>
                                                                </small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark px-3 py-2">
                                                                <?php echo htmlspecialchars($course['course_code']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <span class="badge bg-info px-3 py-2">
                                                                    <i class="fas fa-users me-1"></i>
                                                                    <?php echo $course['enrolled_students']; ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $course['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?> px-3 py-2">
                                                                <?php echo ucfirst($course['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="course_view.php?id=<?php echo $course['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-primary btn-instructor" title="View Course">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="course_edit.php?id=<?php echo $course['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-secondary btn-instructor" title="Edit Course">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="course_analytics.php?id=<?php echo $course['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-info btn-instructor" title="View Analytics">
                                                                    <i class="fas fa-chart-bar"></i>
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
                    </div>

                    <!-- Quick Actions & Chart -->
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <!-- Quick Actions -->
                        <div class="instructor-card mb-4">
                            <div class="card-header-instructor">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-bolt me-3"></i>Quick Actions
                                </h5>
                                <p class="mb-0 opacity-90 small">Common instructor tasks</p>
                            </div>
                            <div class="card-body p-0">
                                <div class="quick-actions-card">
                                    <a href="courses.php" class="action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-book text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">Manage Courses</h6>
                                                <small class="text-muted">Create, edit and manage your courses</small>
                                            </div>
                                        </div>
                                    </a>
                                    
                                    <a href="assignments.php" class="action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--secondary), #0e7490); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-tasks text-white"></i>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <div>
                                                    <h6 class="mb-1 fw-bold">Assignment Center</h6>
                                                    <small class="text-muted">Create, manage and grade assignments</small>
                                                </div>
                                                <?php if ($pending_grading > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $pending_grading; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    
                                    <a href="students.php" class="action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--accent), #6d28d9); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user-graduate text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">My Students</h6>
                                                <small class="text-muted">Manage enrolled students and enrollments</small>
                                            </div>
                                        </div>
                                    </a>
                                    
                                    <a href="messages.php" class="action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--warning), #d97706); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-comments text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">Messages</h6>
                                                <small class="text-muted">Communicate with students and staff</small>
                                            </div>
                                        </div>
                                    </a>
                                    
                                    <a href="reports.php" class="action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-chart-bar text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-bold">Reports & Analytics</h6>
                                                <small class="text-muted">View performance reports and analytics</small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Course Enrollment Chart -->
                        <div class="chart-container" style="height: 400px;">
                            <h6 class="fw-bold mb-3 d-flex align-items-center text-muted">
                                <i class="fas fa-chart-pie me-2"></i>Course Enrollment Distribution
                                </h6>
                            <div style="position: relative; height: 320px;">
                                <canvas id="enrollmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Assignments -->
                <?php if (!empty($recent_assignments)): ?>
                <div class="row" data-aos="fade-up" data-aos-delay="300">
                    <div class="col-12">
                        <div class="instructor-card">
                            <div class="card-header-instructor">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-clipboard-list me-3"></i>Recent Assignments
                                </h5>
                                <p class="mb-0 opacity-90 small">Your recently created assignments and submission status</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table course-table-modern">
                                        <thead>
                                            <tr>
                                                <th>Assignment Title</th>
                                                <th>Course</th>
                                                <th>Due Date</th>
                                                <th>Submissions</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_assignments as $assignment): ?>
                                                <tr>
                                                    <td>
                                                        <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            Created <?php echo formatDate($assignment['created_at']); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark px-3 py-2">
                                                            <?php echo htmlspecialchars($assignment['course_title']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clock text-muted me-2"></i>
                                                            <span><?php echo formatDate($assignment['due_date']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="badge bg-info px-3 py-2">
                                                                <i class="fas fa-file-text me-1"></i>
                                                                <?php echo $assignment['submissions']; ?> submissions
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary btn-instructor" title="View Assignment">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" 
                                                               class="btn btn-sm btn-outline-success btn-instructor" title="Grade Submissions">
                                                                <i class="fas fa-clipboard-check"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
            duration: 1000,
            once: true,
            offset: 100
        });

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in body
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.6s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);

            // Animate stats numbers
            const statsNumbers = document.querySelectorAll('.instructor-stats-number');
            statsNumbers.forEach((stat, index) => {
                const finalNumber = parseInt(stat.textContent);
                let currentNumber = 0;
                const increment = finalNumber / 40;
                
                setTimeout(() => {
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            stat.textContent = finalNumber;
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentNumber);
                        }
                    }, 50);
                }, index * 200);
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.stats-card-instructor, .instructor-card, .action-btn').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (this.classList.contains('stats-card-instructor')) {
                    this.style.transform = 'translateY(-12px) scale(1.03)';
                } else if (this.classList.contains('action-btn')) {
                    this.style.transform = 'translateY(-4px)';
                } else {
                    this.style.transform = 'translateY(-8px)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Course Enrollment Chart
        const ctx = document.getElementById('enrollmentChart').getContext('2d');
        const enrollmentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($courses as $course): ?>
                        '<?php echo addslashes($course['title']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($courses as $course): ?>
                            <?php echo $course['enrolled_students']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#059669', '#0891b2', '#7c3aed', '#f59e0b', '#ef4444',
                        '#06b6d4', '#10b981', '#8b5cf6', '#f97316', '#14b8a6'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                family: 'Inter',
                                size: 11
                            },
                            boxWidth: 12
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
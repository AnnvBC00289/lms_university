<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get enrolled courses with instructor info and course materials count
$query = "SELECT c.*, u.first_name, u.last_name, e.enrollment_date, e.final_grade,
          COUNT(DISTINCT cm.id) as materials_count,
          COUNT(DISTINCT a.id) as assignments_count,
          COUNT(DISTINCT q.id) as quizzes_count
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          JOIN users u ON c.instructor_id = u.id 
          LEFT JOIN course_materials cm ON c.id = cm.course_id
          LEFT JOIN assignments a ON c.id = a.course_id
          LEFT JOIN quizzes q ON c.id = q.course_id
          WHERE e.student_id = ? AND e.status = 'enrolled'
          GROUP BY c.id
          ORDER BY c.title";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available courses for enrollment
$query = "SELECT c.*, u.first_name, u.last_name,
          (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'enrolled') as enrolled_count
          FROM courses c 
          JOIN users u ON c.instructor_id = u.id 
          WHERE c.status = 'active' 
          AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'enrolled')
          ORDER BY c.title";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle course enrollment
if ($_POST['action'] ?? '' === 'enroll' && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    
    // Check if course exists and has space
    $query = "SELECT max_students, 
              (SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'enrolled') as current_count
              FROM courses WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$course_id, $course_id]);
    $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($course_info && $course_info['current_count'] < $course_info['max_students']) {
        $query = "INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$_SESSION['user_id'], $course_id])) {
            $success_message = "Successfully enrolled in the course!";
            header("Location: courses.php?enrolled=1");
            exit();
        }
    } else {
        $error_message = "Course is full or not available.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .course-card {
            background: white;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .course-header {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            padding: 1.5rem;
            position: relative;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .course-body {
            padding: 1.5rem;
        }

        .stats-row {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat-number {
            font-weight: 700;
            font-size: 1.25rem;
            color: #6366f1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 12px 12px 0 0;
            color: #64748b;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .tab-content {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 2rem;
        }

        .enroll-card {
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s ease;
        }

        .enroll-card:hover {
            border-color: #6366f1;
            background: #f8faff;
        }

        .btn-enroll {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-enroll:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
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
                                <i class="fas fa-book text-primary me-3"></i>My Courses
                            </h1>
                            <p class="text-muted">Manage your enrolled courses and discover new ones</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['enrolled'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Successfully enrolled in the course!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Course Tabs -->
                <div class="card border-0 shadow-sm" data-aos="fade-up">
                    <div class="card-header bg-transparent border-0 p-0">
                        <ul class="nav nav-tabs" id="courseTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="enrolled-tab" data-bs-toggle="tab" data-bs-target="#enrolled" type="button" role="tab">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    Enrolled Courses (<?php echo count($enrolled_courses); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Available Courses (<?php echo count($available_courses); ?>)
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="tab-content" id="courseTabContent">
                        <!-- Enrolled Courses Tab -->
                        <div class="tab-pane fade show active" id="enrolled" role="tabpanel">
                            <?php if (empty($enrolled_courses)): ?>
                                <div class="text-center py-5">
                                    <div class="mb-4">
                                        <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No Courses Enrolled</h5>
                                    <p class="text-muted mb-4">You haven't enrolled in any courses yet. Check out available courses below!</p>
                                    <button class="btn btn-primary" onclick="document.getElementById('available-tab').click();">
                                        <i class="fas fa-search me-2"></i>Browse Available Courses
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($enrolled_courses as $index => $course): ?>
                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                            <div class="course-card">
                                                <div class="course-header">
                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                                                        <div>
                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                            <p class="mb-1 opacity-90">
                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>
                                                            </p>
                                                            <p class="mb-0 opacity-75">
                                                                <small><i class="fas fa-user me-1"></i>
                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                                </small>
                                                            </p>
                                                        </div>
                                                        <span class="badge bg-success">Enrolled</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="course-body">
                                                    <p class="text-muted small mb-3">
                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>
                                                    </p>
                                                    
                                                    <div class="stats-row">
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo $course['materials_count']; ?></div>
                                                            <div class="stat-label">Materials</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo $course['assignments_count']; ?></div>
                                                            <div class="stat-label">Assignments</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo $course['quizzes_count']; ?></div>
                                                            <div class="stat-label">Quizzes</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            Enrolled <?php echo formatDate($course['enrollment_date']); ?>
                                                        </small>
                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-arrow-right me-1"></i>View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Available Courses Tab -->
                        <div class="tab-pane fade" id="available" role="tabpanel">
                            <?php if (empty($available_courses)): ?>
                                <div class="text-center py-5">
                                    <div class="mb-4">
                                        <i class="fas fa-search text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No Available Courses</h5>
                                    <p class="text-muted">All available courses are already enrolled or there are no active courses at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($available_courses as $index => $course): ?>
                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                            <div class="course-card">
                                                <div class="course-header">
                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                                                        <div>
                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                            <p class="mb-1 opacity-90">
                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>
                                                            </p>
                                                            <p class="mb-0 opacity-75">
                                                                <small><i class="fas fa-user me-1"></i>
                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                                </small>
                                                            </p>
                                                        </div>
                                                        <span class="badge bg-info">Available</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="course-body">
                                                    <p class="text-muted small mb-3">
                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>
                                                    </p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-users me-1"></i>
                                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> enrolled
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-star me-1"></i>
                                                                <?php echo $course['credits']; ?> credits
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="enroll">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <?php if ($course['enrolled_count'] >= $course['max_students']): ?>
                                                            <button type="button" class="btn btn-secondary w-100" disabled>
                                                                <i class="fas fa-users me-2"></i>Course Full
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="btn btn-enroll w-100" 
                                                                    onclick="return confirm('Are you sure you want to enroll in this course?')">
                                                                <i class="fas fa-plus me-2"></i>Enroll Now
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                AOS.refresh();
            });
        });

        // Course card hover effects
        document.querySelectorAll('.course-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

                            <p class="text-muted">Manage your enrolled courses and discover new ones</p>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="button" class="btn btn-outline-primary">

                                <i class="fas fa-filter me-2"></i>Filter

                            </button>

                            <button type="button" class="btn btn-outline-secondary">

                                <i class="fas fa-download me-2"></i>Export

                            </button>

                        </div>

                    </div>

                </div>



                <?php if (isset($_GET['enrolled'])): ?>

                <div class="alert alert-success alert-dismissible fade show" role="alert">

                    <i class="fas fa-check-circle me-2"></i>

                    Successfully enrolled in the course!

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if (isset($error_message)): ?>

                <div class="alert alert-danger alert-dismissible fade show" role="alert">

                    <i class="fas fa-exclamation-circle me-2"></i>

                    <?php echo $error_message; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <!-- Course Tabs -->

                <div class="card border-0 shadow-sm" data-aos="fade-up">

                    <div class="card-header bg-transparent border-0 p-0">

                        <ul class="nav nav-tabs" id="courseTab" role="tablist">

                            <li class="nav-item" role="presentation">

                                <button class="nav-link active" id="enrolled-tab" data-bs-toggle="tab" data-bs-target="#enrolled" type="button" role="tab">

                                    <i class="fas fa-graduation-cap me-2"></i>

                                    Enrolled Courses (<?php echo count($enrolled_courses); ?>)

                                </button>

                            </li>

                            <li class="nav-item" role="presentation">

                                <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">

                                    <i class="fas fa-plus-circle me-2"></i>

                                    Available Courses (<?php echo count($available_courses); ?>)

                                </button>

                            </li>

                        </ul>

                    </div>

                    

                    <div class="tab-content" id="courseTabContent">

                        <!-- Enrolled Courses Tab -->

                        <div class="tab-pane fade show active" id="enrolled" role="tabpanel">

                            <?php if (empty($enrolled_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Courses Enrolled</h5>

                                    <p class="text-muted mb-4">You haven't enrolled in any courses yet. Check out available courses below!</p>

                                    <button class="btn btn-primary" onclick="document.getElementById('available-tab').click();">

                                        <i class="fas fa-search me-2"></i>Browse Available Courses

                                    </button>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($enrolled_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-success">Enrolled</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="stats-row">

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['materials_count']; ?></div>

                                                            <div class="stat-label">Materials</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['assignments_count']; ?></div>

                                                            <div class="stat-label">Assignments</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['quizzes_count']; ?></div>

                                                            <div class="stat-label">Quizzes</div>

                                                        </div>

                                                    </div>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mt-3">

                                                        <small class="text-muted">

                                                            <i class="fas fa-calendar me-1"></i>

                                                            Enrolled <?php echo formatDate($course['enrollment_date']); ?>

                                                        </small>

                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">

                                                            <i class="fas fa-arrow-right me-1"></i>View

                                                        </a>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>



                        <!-- Available Courses Tab -->

                        <div class="tab-pane fade" id="available" role="tabpanel">

                            <?php if (empty($available_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-search text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Available Courses</h5>

                                    <p class="text-muted">All available courses are already enrolled or there are no active courses at the moment.</p>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($available_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-info">Available</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-users me-1"></i>

                                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> enrolled

                                                            </small>

                                                        </div>

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-star me-1"></i>

                                                                <?php echo $course['credits']; ?> credits

                                                            </small>

                                                        </div>

                                                    </div>

                                                    

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="action" value="enroll">

                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                                                        <?php if ($course['enrolled_count'] >= $course['max_students']): ?>

                                                            <button type="button" class="btn btn-secondary w-100" disabled>

                                                                <i class="fas fa-users me-2"></i>Course Full

                                                            </button>

                                                        <?php else: ?>

                                                            <button type="submit" class="btn btn-enroll w-100" 

                                                                    onclick="return confirm('Are you sure you want to enroll in this course?')">

                                                                <i class="fas fa-plus me-2"></i>Enroll Now

                                                            </button>

                                                        <?php endif; ?>

                                                    </form>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

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

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {

            tab.addEventListener('shown.bs.tab', function() {

                AOS.refresh();

            });

        });



        // Course card hover effects

        document.querySelectorAll('.course-card').forEach(card => {

            card.addEventListener('mouseenter', function() {

                this.style.transform = 'translateY(-5px)';

            });

            

            card.addEventListener('mouseleave', function() {

                this.style.transform = 'translateY(0)';

            });

        });

    </script>

</body>

</html>



                            <p class="text-muted">Manage your enrolled courses and discover new ones</p>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="button" class="btn btn-outline-primary">

                                <i class="fas fa-filter me-2"></i>Filter

                            </button>

                            <button type="button" class="btn btn-outline-secondary">

                                <i class="fas fa-download me-2"></i>Export

                            </button>

                        </div>

                    </div>

                </div>



                <?php if (isset($_GET['enrolled'])): ?>

                <div class="alert alert-success alert-dismissible fade show" role="alert">

                    <i class="fas fa-check-circle me-2"></i>

                    Successfully enrolled in the course!

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if (isset($error_message)): ?>

                <div class="alert alert-danger alert-dismissible fade show" role="alert">

                    <i class="fas fa-exclamation-circle me-2"></i>

                    <?php echo $error_message; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <!-- Course Tabs -->

                <div class="card border-0 shadow-sm" data-aos="fade-up">

                    <div class="card-header bg-transparent border-0 p-0">

                        <ul class="nav nav-tabs" id="courseTab" role="tablist">

                            <li class="nav-item" role="presentation">

                                <button class="nav-link active" id="enrolled-tab" data-bs-toggle="tab" data-bs-target="#enrolled" type="button" role="tab">

                                    <i class="fas fa-graduation-cap me-2"></i>

                                    Enrolled Courses (<?php echo count($enrolled_courses); ?>)

                                </button>

                            </li>

                            <li class="nav-item" role="presentation">

                                <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">

                                    <i class="fas fa-plus-circle me-2"></i>

                                    Available Courses (<?php echo count($available_courses); ?>)

                                </button>

                            </li>

                        </ul>

                    </div>

                    

                    <div class="tab-content" id="courseTabContent">

                        <!-- Enrolled Courses Tab -->

                        <div class="tab-pane fade show active" id="enrolled" role="tabpanel">

                            <?php if (empty($enrolled_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Courses Enrolled</h5>

                                    <p class="text-muted mb-4">You haven't enrolled in any courses yet. Check out available courses below!</p>

                                    <button class="btn btn-primary" onclick="document.getElementById('available-tab').click();">

                                        <i class="fas fa-search me-2"></i>Browse Available Courses

                                    </button>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($enrolled_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-success">Enrolled</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="stats-row">

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['materials_count']; ?></div>

                                                            <div class="stat-label">Materials</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['assignments_count']; ?></div>

                                                            <div class="stat-label">Assignments</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['quizzes_count']; ?></div>

                                                            <div class="stat-label">Quizzes</div>

                                                        </div>

                                                    </div>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mt-3">

                                                        <small class="text-muted">

                                                            <i class="fas fa-calendar me-1"></i>

                                                            Enrolled <?php echo formatDate($course['enrollment_date']); ?>

                                                        </small>

                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">

                                                            <i class="fas fa-arrow-right me-1"></i>View

                                                        </a>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>



                        <!-- Available Courses Tab -->

                        <div class="tab-pane fade" id="available" role="tabpanel">

                            <?php if (empty($available_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-search text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Available Courses</h5>

                                    <p class="text-muted">All available courses are already enrolled or there are no active courses at the moment.</p>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($available_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-info">Available</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-users me-1"></i>

                                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> enrolled

                                                            </small>

                                                        </div>

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-star me-1"></i>

                                                                <?php echo $course['credits']; ?> credits

                                                            </small>

                                                        </div>

                                                    </div>

                                                    

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="action" value="enroll">

                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                                                        <?php if ($course['enrolled_count'] >= $course['max_students']): ?>

                                                            <button type="button" class="btn btn-secondary w-100" disabled>

                                                                <i class="fas fa-users me-2"></i>Course Full

                                                            </button>

                                                        <?php else: ?>

                                                            <button type="submit" class="btn btn-enroll w-100" 

                                                                    onclick="return confirm('Are you sure you want to enroll in this course?')">

                                                                <i class="fas fa-plus me-2"></i>Enroll Now

                                                            </button>

                                                        <?php endif; ?>

                                                    </form>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

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

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {

            tab.addEventListener('shown.bs.tab', function() {

                AOS.refresh();

            });

        });



        // Course card hover effects

        document.querySelectorAll('.course-card').forEach(card => {

            card.addEventListener('mouseenter', function() {

                this.style.transform = 'translateY(-5px)';

            });

            

            card.addEventListener('mouseleave', function() {

                this.style.transform = 'translateY(0)';

            });

        });

    </script>

</body>

</html>



                            <p class="text-muted">Manage your enrolled courses and discover new ones</p>

                        </div>

                        <div class="d-flex gap-2">

                            <button type="button" class="btn btn-outline-primary">

                                <i class="fas fa-filter me-2"></i>Filter

                            </button>

                            <button type="button" class="btn btn-outline-secondary">

                                <i class="fas fa-download me-2"></i>Export

                            </button>

                        </div>

                    </div>

                </div>



                <?php if (isset($_GET['enrolled'])): ?>

                <div class="alert alert-success alert-dismissible fade show" role="alert">

                    <i class="fas fa-check-circle me-2"></i>

                    Successfully enrolled in the course!

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if (isset($error_message)): ?>

                <div class="alert alert-danger alert-dismissible fade show" role="alert">

                    <i class="fas fa-exclamation-circle me-2"></i>

                    <?php echo $error_message; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <!-- Course Tabs -->

                <div class="card border-0 shadow-sm" data-aos="fade-up">

                    <div class="card-header bg-transparent border-0 p-0">

                        <ul class="nav nav-tabs" id="courseTab" role="tablist">

                            <li class="nav-item" role="presentation">

                                <button class="nav-link active" id="enrolled-tab" data-bs-toggle="tab" data-bs-target="#enrolled" type="button" role="tab">

                                    <i class="fas fa-graduation-cap me-2"></i>

                                    Enrolled Courses (<?php echo count($enrolled_courses); ?>)

                                </button>

                            </li>

                            <li class="nav-item" role="presentation">

                                <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">

                                    <i class="fas fa-plus-circle me-2"></i>

                                    Available Courses (<?php echo count($available_courses); ?>)

                                </button>

                            </li>

                        </ul>

                    </div>

                    

                    <div class="tab-content" id="courseTabContent">

                        <!-- Enrolled Courses Tab -->

                        <div class="tab-pane fade show active" id="enrolled" role="tabpanel">

                            <?php if (empty($enrolled_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Courses Enrolled</h5>

                                    <p class="text-muted mb-4">You haven't enrolled in any courses yet. Check out available courses below!</p>

                                    <button class="btn btn-primary" onclick="document.getElementById('available-tab').click();">

                                        <i class="fas fa-search me-2"></i>Browse Available Courses

                                    </button>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($enrolled_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-success">Enrolled</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="stats-row">

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['materials_count']; ?></div>

                                                            <div class="stat-label">Materials</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['assignments_count']; ?></div>

                                                            <div class="stat-label">Assignments</div>

                                                        </div>

                                                        <div class="stat-item">

                                                            <div class="stat-number"><?php echo $course['quizzes_count']; ?></div>

                                                            <div class="stat-label">Quizzes</div>

                                                        </div>

                                                    </div>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mt-3">

                                                        <small class="text-muted">

                                                            <i class="fas fa-calendar me-1"></i>

                                                            Enrolled <?php echo formatDate($course['enrollment_date']); ?>

                                                        </small>

                                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">

                                                            <i class="fas fa-arrow-right me-1"></i>View

                                                        </a>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>



                        <!-- Available Courses Tab -->

                        <div class="tab-pane fade" id="available" role="tabpanel">

                            <?php if (empty($available_courses)): ?>

                                <div class="text-center py-5">

                                    <div class="mb-4">

                                        <i class="fas fa-search text-muted" style="font-size: 4rem; opacity: 0.3;"></i>

                                    </div>

                                    <h5 class="text-muted mb-3">No Available Courses</h5>

                                    <p class="text-muted">All available courses are already enrolled or there are no active courses at the moment.</p>

                                </div>

                            <?php else: ?>

                                <div class="row">

                                    <?php foreach ($available_courses as $index => $course): ?>

                                        <div class="col-lg-6 col-xl-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">

                                            <div class="course-card">

                                                <div class="course-header">

                                                    <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">

                                                        <div>

                                                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                                                            <p class="mb-1 opacity-90">

                                                                <small><?php echo htmlspecialchars($course['course_code']); ?></small>

                                                            </p>

                                                            <p class="mb-0 opacity-75">

                                                                <small><i class="fas fa-user me-1"></i>

                                                                <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>

                                                                </small>

                                                            </p>

                                                        </div>

                                                        <span class="badge bg-info">Available</span>

                                                    </div>

                                                </div>

                                                

                                                <div class="course-body">

                                                    <p class="text-muted small mb-3">

                                                        <?php echo htmlspecialchars(substr($course['description'] ?? 'No description available', 0, 120)) . '...'; ?>

                                                    </p>

                                                    

                                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-users me-1"></i>

                                                                <?php echo $course['enrolled_count']; ?>/<?php echo $course['max_students']; ?> enrolled

                                                            </small>

                                                        </div>

                                                        <div>

                                                            <small class="text-muted">

                                                                <i class="fas fa-star me-1"></i>

                                                                <?php echo $course['credits']; ?> credits

                                                            </small>

                                                        </div>

                                                    </div>

                                                    

                                                    <form method="POST" class="d-inline">

                                                        <input type="hidden" name="action" value="enroll">

                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                                                        <?php if ($course['enrolled_count'] >= $course['max_students']): ?>

                                                            <button type="button" class="btn btn-secondary w-100" disabled>

                                                                <i class="fas fa-users me-2"></i>Course Full

                                                            </button>

                                                        <?php else: ?>

                                                            <button type="submit" class="btn btn-enroll w-100" 

                                                                    onclick="return confirm('Are you sure you want to enroll in this course?')">

                                                                <i class="fas fa-plus me-2"></i>Enroll Now

                                                            </button>

                                                        <?php endif; ?>

                                                    </form>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

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

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {

            tab.addEventListener('shown.bs.tab', function() {

                AOS.refresh();

            });

        });



        // Course card hover effects

        document.querySelectorAll('.course-card').forEach(card => {

            card.addEventListener('mouseenter', function() {

                this.style.transform = 'translateY(-5px)';

            });

            

            card.addEventListener('mouseleave', function() {

                this.style.transform = 'translateY(0)';

            });

        });

    </script>

</body>

</html>



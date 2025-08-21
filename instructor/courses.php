<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle course status toggle
if (isset($_POST['toggle_status']) && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE courses SET status = ? WHERE id = ? AND instructor_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([$new_status, $course_id, $_SESSION['user_id']]);
}

// Handle course deletion
if (isset($_POST['delete_course']) && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    
    // Check if course has enrollments
    $check_query = "SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$course_id]);
    $enrollment_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($enrollment_count > 0) {
        $error = "Cannot delete course with active enrollments. Please archive it instead.";
    } else {
        $delete_query = "DELETE FROM courses WHERE id = ? AND instructor_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        if ($delete_stmt->execute([$course_id, $_SESSION['user_id']])) {
            $message = "Course deleted successfully.";
        } else {
            $error = "Failed to delete course.";
        }
    }
}

// Get instructor's courses with enrollment count
$query = "SELECT c.*, 
          COUNT(e.id) as enrolled_students,
          COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as active_enrollments
          FROM courses c 
          LEFT JOIN enrollments e ON c.id = e.course_id 
          WHERE c.instructor_id = ? 
          GROUP BY c.id
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .course-card {
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

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .course-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .course-code {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .course-meta {
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

        .course-description {
            color: #64748b;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .course-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: #f1f5f9;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .course-actions {
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-archived {
            background: #f1f5f9;
            color: #475569;
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
    </style>
</head>
<body>
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
                                <i class="fas fa-book text-success me-3"></i>My Courses
                            </h1>
                            <p class="text-muted mb-0">Manage all your courses in one place</p>
                        </div>
                        <a href="course_create.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create New Course
                        </a>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($message)): ?>
                    <div class="alert alert-success" data-aos="fade-up">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" data-aos="fade-up">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Courses List -->
                <?php if (empty($courses)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <i class="fas fa-book-open"></i>
                        <h3>No Courses Created Yet</h3>
                        <p>Start building your curriculum by creating your first course.</p>
                        <a href="course_create.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus me-2"></i>Create Your First Course
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($courses as $index => $course): ?>
                            <div class="col-lg-6 col-xl-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                <div class="course-card">
                                    <div class="course-header">
                                        <div class="flex-grow-1">
                                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                            <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                        </div>
                                        <div class="ms-3">
                                            <span class="status-badge status-<?php echo $course['status']; ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="course-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-credit-card"></i>
                                            <span><?php echo $course['credits']; ?> Credits</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo $course['semester'] . ' ' . $course['year']; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo $course['active_enrollments']; ?>/<?php echo $course['max_students']; ?></span>
                                        </div>
                                    </div>

                                    <p class="course-description">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 150)); ?>
                                        <?php if (strlen($course['description']) > 150) echo '...'; ?>
                                    </p>

                                    <div class="course-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $course['active_enrollments']; ?></div>
                                            <div class="stat-label">Students</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo date('M j', strtotime($course['start_date'])); ?></div>
                                            <div class="stat-label">Start Date</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo date('M j', strtotime($course['end_date'])); ?></div>
                                            <div class="stat-label">End Date</div>
                                        </div>
                                    </div>

                                    <div class="course-actions">
                                        <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <a href="course_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        
                                        <!-- Status Toggle -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="new_status" value="<?php echo $course['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $course['status'] == 'active' ? 'warning' : 'success'; ?> btn-sm">
                                                <i class="fas fa-<?php echo $course['status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                                <?php echo $course['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <!-- Delete Button -->
                                        <?php if ($course['active_enrollments'] == 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <input type="hidden" name="delete_course" value="1">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

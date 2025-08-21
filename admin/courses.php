<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle delete course
if (isset($_POST['delete_course'])) {
    $course_id = (int)$_POST['course_id'];
    
    try {
        $query = "DELETE FROM courses WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$course_id]);
        
        $_SESSION['success'] = "Course deleted successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
    }
    
    header('Location: courses.php');
    exit();
}

// Handle create/edit course
if (isset($_POST['save_course'])) {
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $instructor_id = (int)$_POST['instructor_id'];
    $course_code = sanitizeInput($_POST['course_code']);
    $credits = (int)$_POST['credits'];
    $semester = sanitizeInput($_POST['semester']);
    $year = (int)$_POST['year'];
    $max_students = (int)$_POST['max_students'];
    $status = sanitizeInput($_POST['status']);
    
    try {
        if ($course_id) {
            // Update existing course
            $query = "UPDATE courses SET title = ?, description = ?, instructor_id = ?, course_code = ?, credits = ?, semester = ?, year = ?, max_students = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$title, $description, $instructor_id, $course_code, $credits, $semester, $year, $max_students, $status, $course_id]);
            $_SESSION['success'] = "Course updated successfully";
        } else {
            // Create new course
            $query = "INSERT INTO courses (title, description, instructor_id, course_code, credits, semester, year, max_students, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$title, $description, $instructor_id, $course_code, $credits, $semester, $year, $max_students, $status]);
            $_SESSION['success'] = "Course created successfully";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error saving course: " . $e->getMessage();
    }
    
    header('Location: courses.php');
    exit();
}

// Get instructors for dropdown
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'instructor' ORDER BY first_name";
$stmt = $db->prepare($query);
$stmt->execute();
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.course_code LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM courses c 
                JOIN users u ON c.instructor_id = u.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_courses = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_courses / $limit);

// Get courses with instructor info and enrollment count
$query = "SELECT c.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                 COUNT(e.id) as enrollment_count
          FROM courses c 
          JOIN users u ON c.instructor_id = u.id 
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
          $where_clause
          GROUP BY c.id
          ORDER BY c.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --secondary: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%) !important;
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
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 2rem;
            border: none;
        }

        .course-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .course-header {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            padding: 1.5rem;
        }

        .course-body {
            padding: 1.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        .enrollment-badge {
            background: rgba(124, 58, 237, 0.1);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .search-form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .btn-action {
            padding: 0.5rem;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
            margin: 0 2px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem 2rem;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
        }

        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            border: none;
            border-radius: 8px;
            margin: 0 2px;
            color: var(--primary);
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="fw-bold mb-2" style="color: var(--dark);">
                                <i class="fas fa-book text-primary me-3"></i>Course Management
                            </h1>
                            <p class="text-muted mb-0">Manage all courses in the system</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                            <i class="fas fa-plus me-2"></i>Add New Course
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search Form -->
                <div class="search-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Search Courses</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by course name, code, or instructor...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Filter by Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <a href="courses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Courses Grid -->
                <div class="row">
                    <?php if (empty($courses)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-book text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                                <h4 class="text-muted mt-3">No courses found</h4>
                                <p class="text-muted">Start by adding your first course</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="col-xl-4 col-lg-6 mb-4">
                                <div class="course-card">
                                    <div class="course-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <p class="mb-2 opacity-90"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                                <div class="enrollment-badge">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo $course['enrollment_count']; ?>/<?php echo $course['max_students']; ?> students
                                                </div>
                                            </div>
                                            <span class="status-badge status-<?php echo $course['status']; ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="course-body">
                                        <p class="text-muted mb-3">
                                            <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                                        </p>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Instructor</small>
                                                <strong><?php echo htmlspecialchars($course['instructor_name']); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Credits</small>
                                                <strong><?php echo $course['credits']; ?> Credits</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Semester</small>
                                                <strong><?php echo htmlspecialchars($course['semester']); ?> <?php echo $course['year']; ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Created</small>
                                                <strong><?php echo date('M j, Y', strtotime($course['created_at'])); ?></strong>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary btn-action flex-fill" 
                                                    onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-info btn-action" 
                                                    onclick="viewEnrollments(<?php echo $course['id']; ?>)" title="View Enrollments">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" 
                                                    onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')" title="Delete Course">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Course Modal -->
    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="courseModalTitle">
                        <i class="fas fa-plus me-2"></i>Add New Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="course_id" id="course_id">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Course Title</label>
                                <input type="text" class="form-control" name="title" id="title" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Course Code</label>
                                <input type="text" class="form-control" name="course_code" id="course_code" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Instructor</label>
                                <select class="form-select" name="instructor_id" id="instructor_id" required>
                                    <option value="">Select Instructor</option>
                                    <?php foreach ($instructors as $instructor): ?>
                                        <option value="<?php echo $instructor['id']; ?>">
                                            <?php echo htmlspecialchars($instructor['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" name="status" id="status" required>
                                    <option value="">Select Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Credits</label>
                                <input type="number" class="form-control" name="credits" id="credits" min="1" max="6" value="3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Max Students</label>
                                <input type="number" class="form-control" name="max_students" id="max_students" min="1" value="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Year</label>
                                <input type="number" class="form-control" name="year" id="year" min="2020" max="2030" value="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Semester</label>
                                <select class="form-select" name="semester" id="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="Fall">Fall</option>
                                    <option value="Spring">Spring</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_course" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Delete Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this course?</p>
                    <p class="fw-bold text-danger" id="deleteCourseName"></p>
                    <p class="text-muted">This action cannot be undone and will remove all related data including enrollments, assignments, and materials.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="course_id" id="delete_course_id">
                        <button type="submit" name="delete_course" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Course
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCourse(course) {
            document.getElementById('courseModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Course';
            document.getElementById('course_id').value = course.id;
            document.getElementById('title').value = course.title;
            document.getElementById('course_code').value = course.course_code;
            document.getElementById('description').value = course.description;
            document.getElementById('instructor_id').value = course.instructor_id;
            document.getElementById('status').value = course.status;
            document.getElementById('credits').value = course.credits;
            document.getElementById('max_students').value = course.max_students;
            document.getElementById('year').value = course.year;
            document.getElementById('semester').value = course.semester;
            
            new bootstrap.Modal(document.getElementById('courseModal')).show();
        }

        function deleteCourse(courseId, courseName) {
            document.getElementById('delete_course_id').value = courseId;
            document.getElementById('deleteCourseName').textContent = courseName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function viewEnrollments(courseId) {
            window.location.href = 'enrollments.php?course_id=' + courseId;
        }

        // Reset modal when closed
        document.getElementById('courseModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('courseModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Course';
            document.querySelector('#courseModal form').reset();
            document.getElementById('year').value = <?php echo date('Y'); ?>;
            document.getElementById('credits').value = 3;
            document.getElementById('max_students').value = 50;
        });
    </script>
</body>
</html>

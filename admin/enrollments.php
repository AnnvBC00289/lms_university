<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle enrollment actions
if (isset($_POST['action'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'approve':
                $query = "UPDATE enrollments SET status = 'enrolled' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$enrollment_id]);
                $_SESSION['success'] = "Enrollment approved successfully";
                break;
                
            case 'complete':
                $query = "UPDATE enrollments SET status = 'completed' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$enrollment_id]);
                $_SESSION['success'] = "Enrollment marked as completed";
                break;
                
            case 'drop':
                $query = "UPDATE enrollments SET status = 'dropped' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$enrollment_id]);
                $_SESSION['success'] = "Student dropped from course";
                break;
                
            case 'delete':
                $query = "DELETE FROM enrollments WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$enrollment_id]);
                $_SESSION['success'] = "Enrollment deleted successfully";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing enrollment: " . $e->getMessage();
    }
    
    header('Location: enrollments.php' . (isset($_GET['course_id']) ? '?course_id=' . $_GET['course_id'] : ''));
    exit();
}

// Handle manual enrollment
if (isset($_POST['enroll_student'])) {
    $student_id = (int)$_POST['student_id'];
    $course_id = (int)$_POST['course_id'];
    
    try {
        $query = "INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')";
        $stmt = $db->prepare($query);
        $stmt->execute([$student_id, $course_id]);
        $_SESSION['success'] = "Student enrolled successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error enrolling student: " . $e->getMessage();
    }
    
    header('Location: enrollments.php');
    exit();
}

// Get filter parameters
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build where conditions
$where_conditions = [];
$params = [];

if ($course_filter) {
    $where_conditions[] = "e.course_id = ?";
    $params[] = $course_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR c.title LIKE ? OR c.course_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total 
                FROM enrollments e
                JOIN users u ON e.student_id = u.id
                JOIN courses c ON e.course_id = c.id
                $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_enrollments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_enrollments / $limit);

// Get enrollments with student and course info
$query = "SELECT e.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as student_name,
                 u.email as student_email,
                 c.title as course_title,
                 c.course_code,
                 CONCAT(i.first_name, ' ', i.last_name) as instructor_name
          FROM enrollments e
          JOIN users u ON e.student_id = u.id
          JOIN courses c ON e.course_id = c.id
          JOIN users i ON c.instructor_id = i.id
          $where_clause
          ORDER BY e.enrollment_date DESC
          LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses for filter and manual enrollment
$query = "SELECT id, title, course_code FROM courses ORDER BY title";
$stmt = $db->prepare($query);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students for manual enrollment
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'student' ORDER BY first_name";
$stmt = $db->prepare($query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrollment statistics
$query = "SELECT 
            status,
            COUNT(*) as count
          FROM enrollments 
          GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();
$enrollment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['enrolled' => 0, 'completed' => 0, 'dropped' => 0];
foreach ($enrollment_stats as $stat) {
    $stats[$stat['status']] = $stat['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - University LMS</title>
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

        .stats-row {
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--dark);
        }

        .stats-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
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

        .search-form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8fafc;
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem;
            border-color: #f1f5f9;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-enrolled { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-dropped { background: #fee2e2; color: #991b1b; }

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
                                <i class="fas fa-user-plus text-primary me-3"></i>Enrollment Management
                            </h1>
                            <p class="text-muted mb-0">Manage student course enrollments</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollModal">
                            <i class="fas fa-plus me-2"></i>Enroll Student
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

                <!-- Statistics -->
                <div class="stats-row">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-primary"><?php echo number_format($stats['enrolled']); ?></div>
                                <div class="stats-label text-primary">Active Enrollments</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-success"><?php echo number_format($stats['completed']); ?></div>
                                <div class="stats-label text-success">Completed</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-danger"><?php echo number_format($stats['dropped']); ?></div>
                                <div class="stats-label text-danger">Dropped</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-info"><?php echo number_format($total_enrollments); ?></div>
                                <div class="stats-label text-info">Total Enrollments</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Search</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by student name or course...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Filter by Course</label>
                            <select class="form-select" name="course_id">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Filter by Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="enrolled" <?php echo $status_filter === 'enrolled' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="dropped" <?php echo $status_filter === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Enrollments Table -->
                <div class="admin-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-table me-2"></i>All Enrollments (<?php echo $total_enrollments; ?>)
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Status</th>
                                    <th>Enrolled Date</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($enrollments)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-user-plus text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                            <p class="text-muted mt-2">No enrollments found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($enrollment['student_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($enrollment['student_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($enrollment['course_title']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_code']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($enrollment['instructor_name']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $enrollment['status']; ?>">
                                                    <?php echo ucfirst($enrollment['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($enrollment['enrollment_date']); ?></td>
                                            <td>
                                                <?php if ($enrollment['final_grade']): ?>
                                                    <span class="badge bg-success"><?php echo $enrollment['final_grade']; ?>%</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                                            data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($enrollment['status'] === 'enrolled'): ?>
                                                            <li>
                                                                <button class="dropdown-item" 
                                                                        onclick="updateEnrollment(<?php echo $enrollment['id']; ?>, 'complete')">
                                                                    <i class="fas fa-check text-success me-2"></i>Mark Complete
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item" 
                                                                        onclick="updateEnrollment(<?php echo $enrollment['id']; ?>, 'drop')">
                                                                    <i class="fas fa-user-minus text-warning me-2"></i>Drop Student
                                                                </button>
                                                            </li>
                                                        <?php elseif ($enrollment['status'] === 'dropped'): ?>
                                                            <li>
                                                                <button class="dropdown-item" 
                                                                        onclick="updateEnrollment(<?php echo $enrollment['id']; ?>, 'approve')">
                                                                    <i class="fas fa-user-check text-success me-2"></i>Re-enroll
                                                                </button>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" 
                                                                    onclick="deleteEnrollment(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars($enrollment['student_name'] . ' - ' . $enrollment['course_title']); ?>')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&course_id=<?php echo $course_filter; ?>">
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

    <!-- Manual Enrollment Modal -->
    <div class="modal fade" id="enrollModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Enroll Student
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Course</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="enroll_student" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Enroll Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateEnrollment(enrollmentId, action) {
            const actions = {
                'approve': 'approve this enrollment',
                'complete': 'mark this enrollment as completed',
                'drop': 'drop this student from the course'
            };
            
            if (confirm(`Are you sure you want to ${actions[action]}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                    <input type="hidden" name="action" value="${action}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteEnrollment(enrollmentId, enrollmentInfo) {
            if (confirm(`Are you sure you want to delete this enrollment?\n\n${enrollmentInfo}\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                    <input type="hidden" name="action" value="delete">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

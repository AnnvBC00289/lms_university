<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle assignment status toggle
if (isset($_POST['toggle_status']) && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE assignments SET status = ? WHERE id = ? AND instructor_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([$new_status, $assignment_id, $_SESSION['user_id']]);
}

// Handle assignment deletion
if (isset($_POST['delete_assignment']) && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    
    // Check if assignment has submissions
    $check_query = "SELECT COUNT(*) as count FROM assignment_submissions WHERE assignment_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$assignment_id]);
    $submission_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($submission_count > 0) {
        $error = "Cannot delete assignment with submissions. Please archive it instead.";
    } else {
        $delete_query = "DELETE FROM assignments WHERE id = ? AND instructor_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        if ($delete_stmt->execute([$assignment_id, $_SESSION['user_id']])) {
            $message = "Assignment deleted successfully.";
        } else {
            $error = "Failed to delete assignment.";
        }
    }
}

// Get filter parameters
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get instructor's courses for filter
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build assignments query with filters
$where_conditions = ["a.instructor_id = ?"];
$params = [$_SESSION['user_id']];

if ($course_filter) {
    $where_conditions[] = "a.course_id = ?";
    $params[] = $course_filter;
}

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get assignments with submission counts and course info
$query = "SELECT a.*, c.title as course_title, c.course_code,
          COUNT(s.id) as total_submissions,
          COUNT(CASE WHEN s.status = 'submitted' THEN 1 END) as pending_submissions,
          COUNT(CASE WHEN s.grade IS NOT NULL THEN 1 END) as graded_submissions
          FROM assignments a 
          JOIN courses c ON a.course_id = c.id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id 
          WHERE $where_clause
          GROUP BY a.id
          ORDER BY a.due_date DESC, a.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment statistics
$stats_query = "SELECT 
                COUNT(*) as total_assignments,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_assignments,
                COUNT(CASE WHEN due_date > NOW() THEN 1 END) as upcoming_assignments,
                COUNT(CASE WHEN due_date <= NOW() AND status = 'active' THEN 1 END) as overdue_assignments
                FROM assignments 
                WHERE instructor_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
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
        .stat-upcoming { color: #7c3aed; }
        .stat-overdue { color: #dc2626; }

        .filters-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .assignment-card {
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

        .assignment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .assignment-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .assignment-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .assignment-meta {
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

        .assignment-description {
            color: #64748b;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .assignment-stats {
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

        .stat-item-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-item-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .assignment-actions {
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

        .due-date-indicator {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .due-upcoming {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .due-today {
            background: #fef3c7;
            color: #d97706;
        }

        .due-overdue {
            background: #fee2e2;
            color: #dc2626;
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
                                <i class="fas fa-tasks text-success me-3"></i>My Assignments
                            </h1>
                            <p class="text-muted mb-0">Manage and track all your course assignments</p>
                        </div>
                        <a href="assignment_create.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create New Assignment
                        </a>
                    </div>
                </div>

                <!-- Statistics Row -->
                <div class="row stats-row" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-total"><?php echo $stats['total_assignments']; ?></div>
                            <div class="stat-label">Total Assignments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-active"><?php echo $stats['active_assignments']; ?></div>
                            <div class="stat-label">Active Assignments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-upcoming"><?php echo $stats['upcoming_assignments']; ?></div>
                            <div class="stat-label">Upcoming Due</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-overdue"><?php echo $stats['overdue_assignments']; ?></div>
                            <div class="stat-label">Overdue</div>
                        </div>
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
                            <label for="status" class="form-label">Filter by Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="archived" <?php echo ($status_filter == 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-outline-primary me-2">
                                <i class="fas fa-filter me-1"></i>Apply Filters
                            </button>
                            <a href="assignments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Assignments List -->
                <?php if (empty($assignments)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Assignments Found</h3>
                        <p>You haven't created any assignments yet or no assignments match your filter criteria.</p>
                        <a href="assignment_create.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus me-2"></i>Create Your First Assignment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($assignments as $index => $assignment): ?>
                            <div class="col-lg-6 col-xl-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                <div class="assignment-card">
                                    <div class="assignment-header">
                                        <div class="flex-grow-1">
                                            <h3 class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                            <span class="badge bg-secondary"><?php echo ucfirst($assignment['assignment_type']); ?></span>
                                        </div>
                                        <div class="ms-3">
                                            <span class="status-badge status-<?php echo $assignment['status']; ?>">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="assignment-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-book"></i>
                                            <span><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-star"></i>
                                            <span><?php echo $assignment['max_points']; ?> pts</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <?php
                                            $due_date = strtotime($assignment['due_date']);
                                            $now = time();
                                            $diff = $due_date - $now;
                                            
                                            if ($diff < 0) {
                                                echo '<span class="due-date-indicator due-overdue">Overdue</span>';
                                            } elseif ($diff < 86400) { // Less than 24 hours
                                                echo '<span class="due-date-indicator due-today">Due Today</span>';
                                            } else {
                                                echo '<span class="due-date-indicator due-upcoming">Due ' . date('M j', $due_date) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <p class="assignment-description">
                                        <?php echo htmlspecialchars(substr($assignment['description'], 0, 120)); ?>
                                        <?php if (strlen($assignment['description']) > 120) echo '...'; ?>
                                    </p>

                                    <div class="assignment-stats">
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $assignment['total_submissions']; ?></div>
                                            <div class="stat-item-label">Submissions</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $assignment['pending_submissions']; ?></div>
                                            <div class="stat-item-label">Pending</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-item-number"><?php echo $assignment['graded_submissions']; ?></div>
                                            <div class="stat-item-label">Graded</div>
                                        </div>
                                    </div>

                                    <div class="assignment-actions">
                                        <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <a href="assignment_edit.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-clipboard-check me-1"></i>Grade
                                        </a>
                                        
                                        <!-- Status Toggle -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="new_status" value="<?php echo $assignment['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $assignment['status'] == 'active' ? 'warning' : 'success'; ?> btn-sm">
                                                <i class="fas fa-<?php echo $assignment['status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                                <?php echo $assignment['status'] == 'active' ? 'Pause' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <!-- Delete Button -->
                                        <?php if ($assignment['total_submissions'] == 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this assignment? This action cannot be undone.')">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                <input type="hidden" name="delete_assignment" value="1">
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
        document.querySelectorAll('.assignment-card').forEach(card => {
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
    </script>
</body>
</html>

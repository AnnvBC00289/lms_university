<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get assignment ID
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assignment_id) {
    header('Location: assignments.php');
    exit();
}

// Get assignment details with course info
$query = "SELECT a.*, c.title as course_title, c.course_code, u.first_name, u.last_name
          FROM assignments a 
          JOIN courses c ON a.course_id = c.id
          JOIN users u ON a.instructor_id = u.id
          WHERE a.id = ? AND a.instructor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header('Location: assignments.php');
    exit();
}

// Get assignment submissions with student info
$submissions_query = "SELECT s.*, u.first_name, u.last_name, u.email,
                     CASE 
                       WHEN s.submitted_at <= a.due_date THEN 'on_time'
                       WHEN s.submitted_at > a.due_date THEN 'late'
                     END as submission_status
                     FROM assignment_submissions s
                     JOIN users u ON s.student_id = u.id
                     JOIN assignments a ON s.assignment_id = a.id
                     WHERE s.assignment_id = ?
                     ORDER BY s.submitted_at DESC";
$submissions_stmt = $db->prepare($submissions_query);
$submissions_stmt->execute([$assignment_id]);
$submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrolled students count
$enrolled_query = "SELECT COUNT(*) as count FROM enrollments e 
                  JOIN courses c ON e.course_id = c.id
                  WHERE c.id = ? AND e.status = 'enrolled'";
$enrolled_stmt = $db->prepare($enrolled_query);
$enrolled_stmt->execute([$assignment['course_id']]);
$enrolled_count = $enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate statistics
$total_submissions = count($submissions);
$graded_submissions = count(array_filter($submissions, function($s) { return $s['grade'] !== null; }));
$pending_submissions = count(array_filter($submissions, function($s) { return $s['grade'] === null; }));
$on_time_submissions = count(array_filter($submissions, function($s) { return $s['submission_status'] === 'on_time'; }));
$late_submissions = count(array_filter($submissions, function($s) { return $s['submission_status'] === 'late'; }));
$submission_rate = $enrolled_count > 0 ? round(($total_submissions / $enrolled_count) * 100) : 0;

// Calculate average grade
$grades = array_filter(array_column($submissions, 'grade'), function($grade) { return $grade !== null; });
$average_grade = count($grades) > 0 ? round(array_sum($grades) / count($grades), 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($assignment['title']); ?> - Assignment Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        .assignment-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .assignment-hero::before {
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

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
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
            vertical-align: middle;
        }

        .submission-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-on-time {
            background: #dcfce7;
            color: #166534;
        }

        .status-late {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-graded {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .grade-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            min-width: 60px;
            text-align: center;
        }

        .assignment-details {
            background: #f8fafc;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }

        .detail-item {
            display: flex;
            justify-content: between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 150px;
        }

        .detail-value {
            color: #6b7280;
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

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Assignment Hero Section -->
                <div class="assignment-hero" data-aos="fade-down">
                    <div class="row align-items-center">
                        <div class="col-lg-8" style="position: relative; z-index: 2;">
                            <div class="mb-3">
                                <span class="badge bg-white text-primary me-2">
                                    <?php echo htmlspecialchars($assignment['course_code']); ?>
                                </span>
                                <span class="badge bg-white text-success">
                                    <?php echo ucfirst($assignment['assignment_type']); ?>
                                </span>
                                <span class="badge bg-white text-info">
                                    <?php echo ucfirst($assignment['status']); ?>
                                </span>
                            </div>
                            <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                            <p class="mb-3 opacity-90"><?php echo htmlspecialchars($assignment['description']); ?></p>
                            <div class="d-flex gap-4">
                                <div>
                                    <i class="fas fa-star me-2"></i>
                                    <?php echo $assignment['max_points']; ?> Points
                                </div>
                                <div>
                                    <i class="fas fa-calendar me-2"></i>
                                    Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                </div>
                                <div>
                                    <i class="fas fa-users me-2"></i>
                                    <?php echo $total_submissions; ?>/<?php echo $enrolled_count; ?> Submissions
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center" style="position: relative; z-index: 2;">
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <a href="assignment_edit.php?id=<?php echo $assignment['id']; ?>" class="btn btn-light btn-action">
                                    <i class="fas fa-edit"></i>Edit Assignment
                                </a>
                                <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-light btn-action">
                                    <i class="fas fa-clipboard-check"></i>Grade Submissions
                                </a>
                                <a href="assignments.php" class="btn btn-outline-light btn-action">
                                    <i class="fas fa-arrow-left"></i>Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stat-grid" data-aos="fade-up">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $total_submissions; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $graded_submissions; ?></div>
                        <div class="stat-label">Graded</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $pending_submissions; ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $submission_rate; ?>%</div>
                        <div class="stat-label">Submission Rate</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $average_grade; ?></div>
                        <div class="stat-label">Average Grade</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $late_submissions; ?></div>
                        <div class="stat-label">Late Submissions</div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Assignment Details -->
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="info-card">
                            <h5 class="fw-bold text-success mb-3">
                                <i class="fas fa-info-circle me-2"></i>Assignment Details
                            </h5>
                            <div class="assignment-details">
                                <div class="detail-item">
                                    <div class="detail-label">Course:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($assignment['course_title']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Max Points:</div>
                                    <div class="detail-value"><?php echo $assignment['max_points']; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Due Date:</div>
                                    <div class="detail-value"><?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status:</div>
                                    <div class="detail-value">
                                        <span class="submission-status status-<?php echo $assignment['status']; ?>">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Late Submissions:</div>
                                    <div class="detail-value"><?php echo $assignment['allow_late_submission'] ? 'Allowed' : 'Not Allowed'; ?></div>
                                </div>
                                <?php if ($assignment['allow_late_submission'] && $assignment['late_penalty'] > 0): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Late Penalty:</div>
                                    <div class="detail-value"><?php echo $assignment['late_penalty']; ?>% per day</div>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <div class="detail-label">Created:</div>
                                    <div class="detail-value"><?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment Instructions -->
                        <?php if ($assignment['instructions']): ?>
                        <div class="info-card">
                            <h5 class="fw-bold text-success mb-3">
                                <i class="fas fa-list me-2"></i>Instructions
                            </h5>
                            <div class="instructions-content">
                                <?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Submission Analytics Chart -->
                        <div class="info-card">
                            <h5 class="fw-bold text-success mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Submission Analytics
                            </h5>
                            <canvas id="submissionChart" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Submissions List -->
                    <div class="col-lg-8 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-success mb-0">
                                    <i class="fas fa-file-upload me-2"></i>Student Submissions
                                </h5>
                                <div class="d-flex gap-2">
                                    <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-clipboard-check me-1"></i>Grade All
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" onclick="exportSubmissions()">
                                        <i class="fas fa-download me-1"></i>Export
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (empty($submissions)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>No Submissions Yet</h3>
                                    <p>Students haven't submitted their work yet. Check back later or send a reminder.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Submitted</th>
                                                <th>Status</th>
                                                <th>Grade</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $submission): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($submission['email']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                                        <?php if ($submission['submission_status'] === 'late'): ?>
                                                            <br><small class="text-danger"><i class="fas fa-clock me-1"></i>Late Submission</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="submission-status status-<?php echo $submission['submission_status']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $submission['submission_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($submission['grade'] !== null): ?>
                                                            <span class="grade-badge"><?php echo $submission['grade']; ?>/<?php echo $assignment['max_points']; ?></span>
                                                        <?php else: ?>
                                                            <span class="submission-status status-pending">Not Graded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="submission_view.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Submission">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="submission_grade.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-outline-success" title="Grade Submission">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($submission['file_path']): ?>
                                                                <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" class="btn btn-sm btn-outline-info" title="Download File" target="_blank">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                            <?php endif; ?>
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

        // Submission Analytics Chart
        const ctx = document.getElementById('submissionChart').getContext('2d');
        const submissionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Graded', 'Pending Review', 'Not Submitted'],
                datasets: [{
                    data: [
                        <?php echo $graded_submissions; ?>, 
                        <?php echo $pending_submissions; ?>, 
                        <?php echo $enrolled_count - $total_submissions; ?>
                    ],
                    backgroundColor: ['#059669', '#f59e0b', '#e5e7eb'],
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
                cutout: '65%'
            }
        });

        // Export submissions function
        function exportSubmissions() {
            // This would typically generate a CSV or Excel file
            alert('Export functionality would be implemented here to download submission data as CSV/Excel file.');
        }
    </script>
</body>
</html>

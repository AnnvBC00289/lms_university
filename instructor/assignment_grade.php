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

// Get assignment details
$query = "SELECT a.*, c.title as course_title, c.course_code 
          FROM assignments a 
          JOIN courses c ON a.course_id = c.id
          WHERE a.id = ? AND a.instructor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header('Location: assignments.php');
    exit();
}

$message = '';
$error = '';

// Handle bulk grading
if ($_POST && isset($_POST['bulk_grade'])) {
    $grades = $_POST['grades'] ?? [];
    $feedback = $_POST['feedback'] ?? [];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($grades as $submission_id => $grade) {
        $submission_id = (int)$submission_id;
        $grade = is_numeric($grade) ? (float)$grade : null;
        $feedback_text = isset($feedback[$submission_id]) ? trim($feedback[$submission_id]) : '';
        
        if ($grade !== null && $grade >= 0 && $grade <= $assignment['max_points']) {
            $update_query = "UPDATE assignment_submissions 
                           SET grade = ?, feedback = ?, graded_at = NOW(), graded_by = ?
                           WHERE id = ? AND assignment_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$grade, $feedback_text, $_SESSION['user_id'], $submission_id, $assignment_id])) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }
    
    if ($success_count > 0) {
        $message = "Successfully graded {$success_count} submission(s).";
    }
    if ($error_count > 0) {
        $error = "Failed to grade {$error_count} submission(s).";
    }
}

// Get submissions for grading
$submissions_query = "SELECT s.*, u.first_name, u.last_name, u.email, u.student_id,
                     CASE 
                       WHEN s.submitted_at <= a.due_date THEN 'on_time'
                       WHEN s.submitted_at > a.due_date THEN 'late'
                     END as submission_status,
                     DATEDIFF(s.submitted_at, a.due_date) as days_late
                     FROM assignment_submissions s
                     JOIN users u ON s.student_id = u.id
                     JOIN assignments a ON s.assignment_id = a.id
                     WHERE s.assignment_id = ?
                     ORDER BY u.last_name, u.first_name";
$submissions_stmt = $db->prepare($submissions_query);
$submissions_stmt->execute([$assignment_id]);
$submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate grading statistics
$total_submissions = count($submissions);
$graded_count = count(array_filter($submissions, function($s) { return $s['grade'] !== null; }));
$ungraded_count = $total_submissions - $graded_count;
$grades = array_filter(array_column($submissions, 'grade'), function($grade) { return $grade !== null; });
$average_grade = count($grades) > 0 ? round(array_sum($grades) / count($grades), 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignment - <?php echo htmlspecialchars($assignment['title']); ?></title>
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

        .grading-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .grading-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .grading-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .submission-row {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: #fafbfc;
            transition: all 0.3s ease;
        }

        .submission-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .submission-row.graded {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .submission-row.ungraded {
            background: #fffbeb;
            border-color: #fed7aa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .student-details h6 {
            margin: 0;
            font-weight: 700;
            color: #1f2937;
        }

        .student-details small {
            color: #64748b;
        }

        .submission-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .meta-on-time {
            background: #dcfce7;
            color: #166534;
        }

        .meta-late {
            background: #fee2e2;
            color: #991b1b;
        }

        .meta-graded {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .meta-ungraded {
            background: #fef3c7;
            color: #d97706;
        }

        .grading-controls {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 1rem;
            align-items: start;
        }

        .grade-input {
            position: relative;
        }

        .grade-input input {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
        }

        .grade-input input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .grade-input .max-points {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.875rem;
        }

        .feedback-input textarea {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            resize: vertical;
            min-height: 80px;
        }

        .feedback-input textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-quick {
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-quick:hover {
            background: #f3f4f6;
            border-color: var(--primary);
        }

        .bulk-actions {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
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

        @media (max-width: 768px) {
            .grading-controls {
                grid-template-columns: 1fr;
                gap: 1rem;
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
                <!-- Grading Header -->
                <div class="grading-header" data-aos="fade-down">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="fw-bold mb-2">
                                <i class="fas fa-clipboard-check me-3"></i>Grade Assignment
                            </h1>
                            <h4 class="mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h4>
                            <p class="mb-0 opacity-90">
                                <?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_title']); ?>
                                <span class="mx-2">•</span>
                                Max Points: <?php echo $assignment['max_points']; ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-light">
                                    <i class="fas fa-eye me-2"></i>View Assignment
                                </a>
                                <a href="assignments.php" class="btn btn-light">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grading Statistics -->
                <div class="grading-stats" data-aos="fade-up">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $total_submissions; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $graded_count; ?></div>
                        <div class="stat-label">Graded</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $ungraded_count; ?></div>
                        <div class="stat-label">Remaining</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $average_grade; ?></div>
                        <div class="stat-label">Average Grade</div>
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

                <?php if (empty($submissions)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <i class="fas fa-inbox"></i>
                        <h3>No Submissions to Grade</h3>
                        <p>Students haven't submitted their work yet. Check back later when submissions are available.</p>
                        <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-primary">
                            View Assignment Details
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" data-aos="fade-up">
                        <!-- Bulk Actions -->
                        <div class="bulk-actions">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="fw-bold mb-2">
                                        <i class="fas fa-tasks me-2"></i>Grading Progress
                                    </h6>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $total_submissions > 0 ? ($graded_count / $total_submissions) * 100 : 0; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $graded_count; ?> of <?php echo $total_submissions; ?> submissions graded 
                                        (<?php echo $total_submissions > 0 ? round(($graded_count / $total_submissions) * 100) : 0; ?>%)
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="submit" name="bulk_grade" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Save All Grades
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Submissions Grading -->
                        <div class="grading-card">
                            <h5 class="fw-bold text-success mb-4">
                                <i class="fas fa-users me-2"></i>Student Submissions
                            </h5>

                            <?php foreach ($submissions as $index => $submission): ?>
                                <div class="submission-row <?php echo $submission['grade'] !== null ? 'graded' : 'ungraded'; ?>" 
                                     data-aos="fade-up" data-aos-delay="<?php echo $index * 50; ?>">
                                    
                                    <!-- Student Info -->
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($submission['first_name'], 0, 1) . substr($submission['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-details">
                                            <h6><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></h6>
                                            <small><?php echo htmlspecialchars($submission['email']); ?></small>
                                            <?php if ($submission['student_id']): ?>
                                                <br><small>ID: <?php echo htmlspecialchars($submission['student_id']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Submission Meta -->
                                    <div class="submission-meta">
                                        <span class="meta-badge meta-<?php echo $submission['submission_status']; ?>">
                                            <?php if ($submission['submission_status'] === 'late'): ?>
                                                <i class="fas fa-clock me-1"></i>Late 
                                                (<?php echo abs($submission['days_late']); ?> day<?php echo abs($submission['days_late']) != 1 ? 's' : ''; ?>)
                                            <?php else: ?>
                                                <i class="fas fa-check me-1"></i>On Time
                                            <?php endif; ?>
                                        </span>
                                        <span class="meta-badge meta-<?php echo $submission['grade'] !== null ? 'graded' : 'ungraded'; ?>">
                                            <?php if ($submission['grade'] !== null): ?>
                                                <i class="fas fa-clipboard-check me-1"></i>Graded
                                            <?php else: ?>
                                                <i class="fas fa-clock me-1"></i>Ungraded
                                            <?php endif; ?>
                                        </span>
                                        <span class="meta-badge" style="background: #f1f5f9; color: #64748b;">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                        </span>
                                    </div>

                                    <!-- Grading Controls -->
                                    <div class="grading-controls">
                                        <!-- Grade Input -->
                                        <div class="grade-input">
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="grades[<?php echo $submission['id']; ?>]"
                                                   value="<?php echo $submission['grade'] !== null ? $submission['grade'] : ''; ?>"
                                                   min="0" 
                                                   max="<?php echo $assignment['max_points']; ?>" 
                                                   step="0.1"
                                                   placeholder="Grade">
                                            <span class="max-points">/ <?php echo $assignment['max_points']; ?></span>
                                        </div>

                                        <!-- Feedback Input -->
                                        <div class="feedback-input">
                                            <textarea class="form-control" 
                                                      name="feedback[<?php echo $submission['id']; ?>]"
                                                      placeholder="Provide feedback for the student..."><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                                        </div>

                                        <!-- Quick Actions -->
                                        <div class="quick-actions">
                                            <button type="button" class="btn-quick" onclick="setGrade(<?php echo $submission['id']; ?>, <?php echo $assignment['max_points']; ?>)">
                                                Full Points
                                            </button>
                                            <button type="button" class="btn-quick" onclick="setGrade(<?php echo $submission['id']; ?>, <?php echo $assignment['max_points'] * 0.9; ?>)">
                                                90%
                                            </button>
                                            <button type="button" class="btn-quick" onclick="setGrade(<?php echo $submission['id']; ?>, <?php echo $assignment['max_points'] * 0.8; ?>)">
                                                80%
                                            </button>
                                            <button type="button" class="btn-quick" onclick="setGrade(<?php echo $submission['id']; ?>, <?php echo $assignment['max_points'] * 0.7; ?>)">
                                                70%
                                            </button>
                                            <?php if ($submission['file_path']): ?>
                                                <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn-quick text-center">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Final Submit Button -->
                        <div class="text-center mt-4">
                            <button type="submit" name="bulk_grade" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-save me-2"></i>Save All Grades & Feedback
                            </button>
                        </div>
                    </form>
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

        // Set grade using quick action buttons
        function setGrade(submissionId, grade) {
            const input = document.querySelector(`input[name="grades[${submissionId}]"]`);
            if (input) {
                input.value = grade;
                input.focus();
            }
        }

        // Auto-save progress indication
        let saveTimeout;
        function indicateChange() {
            clearTimeout(saveTimeout);
            // Could add visual indication of unsaved changes
        }

        // Add change listeners to all grade inputs
        document.querySelectorAll('input[name^="grades["]').forEach(input => {
            input.addEventListener('input', indicateChange);
        });

        document.querySelectorAll('textarea[name^="feedback["]').forEach(textarea => {
            textarea.addEventListener('input', indicateChange);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[name="bulk_grade"]').click();
            }
        });

        // Grade validation
        document.querySelectorAll('input[name^="grades["]').forEach(input => {
            input.addEventListener('blur', function() {
                const maxPoints = <?php echo $assignment['max_points']; ?>;
                const value = parseFloat(this.value);
                
                if (value > maxPoints) {
                    alert(`Grade cannot exceed maximum points (${maxPoints})`);
                    this.focus();
                }
                if (value < 0) {
                    alert('Grade cannot be negative');
                    this.focus();
                }
            });
        });
    </script>
</body>
</html>

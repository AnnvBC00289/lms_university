<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$assignment_id = (int)($_GET['id'] ?? 0);
if (!$assignment_id) {
    header('Location: assignments.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if student is enrolled in the course and get assignment details
$query = "SELECT a.*, c.title as course_title, c.course_code, c.id as course_id,
          u.first_name as instructor_first, u.last_name as instructor_last,
          e.student_id
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          JOIN users u ON c.instructor_id = u.id
          WHERE a.id = ? AND e.student_id = ? AND e.status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header('Location: assignments.php?error=not_found');
    exit();
}

// Check if already submitted
$query = "SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$existing_submission = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_text = trim($_POST['submission_text'] ?? '');
    $file_uploaded = false;
    $file_path = '';
    
    // Validate input
    if (empty($submission_text) && empty($_FILES['submission_file']['name'])) {
        $error_message = 'Please provide either submission text or upload a file.';
    } else {
        // Handle file upload
        if (!empty($_FILES['submission_file']['name'])) {
            $upload_dir = '../uploads/assignments/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
            
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                $error_message = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
            } elseif ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) { // 10MB limit
                $error_message = 'File size exceeds 10MB limit.';
            } else {
                $filename = 'assignment_' . $assignment_id . '_student_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path)) {
                    $file_uploaded = true;
                } else {
                    $error_message = 'Failed to upload file. Please try again.';
                }
            }
        }
        
        // If no file upload errors, process submission
        if (empty($error_message)) {
            try {
                if ($existing_submission) {
                    // Update existing submission
                    $query = "UPDATE assignment_submissions 
                              SET submission_text = ?, file_path = ?, submitted_at = NOW(), grade = NULL, feedback = NULL, graded_by = NULL, graded_at = NULL
                              WHERE assignment_id = ? AND student_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$submission_text, $file_path, $assignment_id, $_SESSION['user_id']]);
                    $success_message = 'Assignment updated successfully!';
                } else {
                    // Create new submission
                    $query = "INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, submitted_at) 
                              VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$assignment_id, $_SESSION['user_id'], $submission_text, $file_path]);
                    $success_message = 'Assignment submitted successfully!';
                }
                
                // Refresh submission data
                $query = "SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id, $_SESSION['user_id']]);
                $existing_submission = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $error_message = 'Failed to submit assignment. Please try again.';
                
                // Clean up uploaded file if database operation failed
                if ($file_uploaded && file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        } elseif ($file_uploaded && file_exists($file_path)) {
            // Clean up uploaded file if there were validation errors
            unlink($file_path);
        }
    }
}

// Check if assignment is overdue
$is_overdue = strtotime($assignment['due_date']) < time();
$days_until_due = (strtotime($assignment['due_date']) - time()) / (60 * 60 * 24);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment: <?php echo htmlspecialchars($assignment['title']); ?> - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        .assignment-header {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .assignment-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .submission-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .submission-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #6366f1;
            background: #f8faff;
        }

        .file-upload-area.dragover {
            border-color: #6366f1;
            background: #f0f7ff;
        }

        .submission-preview {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6366f1;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #6366f1;
        }

        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 3px #10b981;
        }

        .grading-info {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #0ea5e9;
        }

        .grade-display {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0ea5e9;
        }

        .btn-submit {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-submit:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .assignment-header {
                padding: 1.5rem;
            }
            .info-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Assignment Header -->
                <div class="assignment-header" data-aos="fade-down">
                    <div class="position-relative" style="z-index: 2;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h1 class="display-6 fw-bold mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                                <p class="mb-1 opacity-90">
                                    <span class="badge bg-white text-dark me-2"><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                                    <?php echo htmlspecialchars($assignment['course_title']); ?>
                                </p>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-user me-2"></i>
                                    Instructor: <?php echo htmlspecialchars($assignment['instructor_first'] . ' ' . $assignment['instructor_last']); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <?php if ($existing_submission): ?>
                                    <span class="status-badge bg-success text-white">
                                        <i class="fas fa-check-circle me-2"></i>Submitted
                                    </span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="status-badge bg-danger text-white">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Overdue
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge bg-warning text-dark">
                                        <i class="fas fa-clock me-2"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($assignment['description'])): ?>
                            <div class="mt-3 p-3 bg-white bg-opacity-20 rounded">
                                <h6 class="opacity-90 mb-2">Assignment Description:</h6>
                                <p class="mb-0 opacity-80"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Submission Form -->
                        <div class="submission-card" data-aos="fade-up">
                            <div class="submission-header">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-upload me-2 text-primary"></i>
                                    <?php echo $existing_submission ? 'Update Submission' : 'Submit Assignment'; ?>
                                </h5>
                                <p class="text-muted small mb-0 mt-1">
                                    <?php echo $existing_submission ? 'You can update your submission until the deadline.' : 'Submit your assignment using the form below.'; ?>
                                </p>
                            </div>
                            
                            <div class="p-4">
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" enctype="multipart/form-data" id="submissionForm">
                                    <!-- Submission Text -->
                                    <div class="mb-4">
                                        <label for="submission_text" class="form-label fw-semibold">
                                            <i class="fas fa-edit me-2"></i>Submission Text
                                        </label>
                                        <textarea class="form-control" id="submission_text" name="submission_text" 
                                                  rows="8" placeholder="Enter your submission text here..."><?php echo htmlspecialchars($existing_submission['submission_text'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            You can include your answer, explanation, or any additional notes here.
                                        </div>
                                    </div>

                                    <!-- File Upload -->
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-paperclip me-2"></i>File Upload (Optional)
                                        </label>
                                        
                                        <div class="file-upload-area" id="fileUploadArea">
                                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                            <h6 class="mb-2">Drag and drop your file here</h6>
                                            <p class="text-muted mb-3">or click to browse</p>
                                            <input type="file" id="submission_file" name="submission_file" 
                                                   accept=".pdf,.doc,.docx,.txt,.zip,.rar" hidden>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('submission_file').click()">
                                                <i class="fas fa-folder-open me-2"></i>Choose File
                                            </button>
                                        </div>
                                        
                                        <div class="form-text mt-2">
                                            Accepted formats: PDF, DOC, DOCX, TXT, ZIP, RAR (Max size: 10MB)
                                        </div>
                                        
                                        <div id="selectedFile" class="mt-3" style="display: none;">
                                            <div class="alert alert-info d-flex align-items-center">
                                                <i class="fas fa-file me-3"></i>
                                                <div class="flex-grow-1">
                                                    <strong id="fileName"></strong>
                                                    <div class="small text-muted" id="fileSize"></div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile()">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <?php if ($existing_submission && $existing_submission['file_path']): ?>
                                            <div class="alert alert-success mt-3">
                                                <i class="fas fa-file me-2"></i>
                                                <strong>Current file:</strong> 
                                                <a href="<?php echo htmlspecialchars($existing_submission['file_path']); ?>" 
                                                   target="_blank" class="text-decoration-none">
                                                    <?php echo basename($existing_submission['file_path']); ?>
                                                    <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                                <div class="small text-muted mt-1">
                                                    Uploading a new file will replace the current one.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="assignments.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Assignments
                                        </a>
                                        
                                        <div>
                                            <?php if ($is_overdue && !$existing_submission): ?>
                                                <div class="text-danger small mb-2">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    This assignment is overdue. Late submissions may have penalties.
                                                </div>
                                            <?php endif; ?>
                                            
                                            <button type="submit" class="btn-submit" id="submitBtn">
                                                <i class="fas fa-paper-plane me-2"></i>
                                                <?php echo $existing_submission ? 'Update Submission' : 'Submit Assignment'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Current Submission Preview -->
                        <?php if ($existing_submission): ?>
                            <div class="submission-card mt-4" data-aos="fade-up" data-aos-delay="100">
                                <div class="submission-header">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="fas fa-eye me-2 text-success"></i>Current Submission
                                    </h5>
                                    <p class="text-muted small mb-0 mt-1">
                                        Submitted on <?php echo formatDate($existing_submission['submitted_at']); ?>
                                    </p>
                                </div>
                                
                                <div class="p-4">
                                    <?php if ($existing_submission['submission_text']): ?>
                                        <div class="submission-preview mb-3">
                                            <h6 class="fw-semibold mb-2">Submission Text:</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($existing_submission['submission_text'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($existing_submission['file_path']): ?>
                                        <div class="submission-preview">
                                            <h6 class="fw-semibold mb-2">Attached File:</h6>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-alt text-primary me-2"></i>
                                                <a href="<?php echo htmlspecialchars($existing_submission['file_path']); ?>" 
                                                   target="_blank" class="text-decoration-none">
                                                    <?php echo basename($existing_submission['file_path']); ?>
                                                    <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($existing_submission['grade'] !== null): ?>
                                        <div class="grading-info mt-4">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <div class="grade-display">
                                                        <?php echo $existing_submission['grade']; ?>/<?php echo $assignment['max_points']; ?>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <h6 class="fw-bold mb-1">Assignment Graded</h6>
                                                    <p class="mb-0 small">
                                                        Percentage: <?php echo number_format(($existing_submission['grade'] / $assignment['max_points']) * 100, 1); ?>%
                                                    </p>
                                                    <?php if ($existing_submission['feedback']): ?>
                                                        <div class="mt-3">
                                                            <h6 class="fw-semibold mb-2">Instructor Feedback:</h6>
                                                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($existing_submission['feedback'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <!-- Assignment Info -->
                        <div class="info-card" data-aos="fade-up">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-info-circle me-2 text-primary"></i>Assignment Details
                            </h6>
                            
                            <div class="timeline-item <?php echo $existing_submission ? 'completed' : ''; ?>">
                                <h6 class="fw-semibold mb-1">Due Date</h6>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo formatDate($assignment['due_date']); ?>
                                </p>
                                <?php if (!$is_overdue && !$existing_submission): ?>
                                    <p class="text-info small mb-0">
                                        <?php 
                                        if ($days_until_due > 1) {
                                            echo ceil($days_until_due) . ' days remaining';
                                        } elseif ($days_until_due > 0) {
                                            echo 'Due today!';
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-item">
                                <h6 class="fw-semibold mb-1">Points</h6>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-star me-1"></i>
                                    <?php echo $assignment['max_points']; ?> points
                                </p>
                            </div>
                            
                            <div class="timeline-item">
                                <h6 class="fw-semibold mb-1">Status</h6>
                                <?php if ($existing_submission): ?>
                                    <?php if ($existing_submission['grade'] !== null): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-double me-1"></i>Graded
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-clock me-1"></i>Submitted - Pending Review
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($is_overdue): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-hourglass-half me-1"></i>Not Submitted
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="info-card" data-aos="fade-up" data-aos-delay="100">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-bolt me-2 text-primary"></i>Quick Actions
                            </h6>
                            
                            <div class="d-grid gap-2">
                                <a href="course_view.php?id=<?php echo $assignment['course_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-book me-2"></i>View Course
                                </a>
                                <a href="assignments.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-list me-2"></i>All Assignments
                                </a>
                                <a href="../messages/compose.php?to=<?php echo $assignment['created_by']; ?>" 
                                   class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-envelope me-2"></i>Contact Instructor
                                </a>
                            </div>
                        </div>

                        <!-- Submission Tips -->
                        <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-lightbulb me-2 text-warning"></i>Submission Tips
                            </h6>
                            
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Double-check your work before submitting
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Ensure files are in accepted formats
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Submit before the deadline to avoid penalties
                                </li>
                                <li class="mb-0">
                                    <i class="fas fa-check text-success me-2"></i>
                                    You can update your submission until the due date
                                </li>
                            </ul>
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

        // File upload handling
        const fileInput = document.getElementById('submission_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const selectedFileDiv = document.getElementById('selectedFile');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop
        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', handleDragOver);
        fileUploadArea.addEventListener('dragleave', handleDragLeave);
        fileUploadArea.addEventListener('drop', handleDrop);

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                displayFile(file);
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                displayFile(files[0]);
            }
        }

        function displayFile(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            selectedFileDiv.style.display = 'block';
        }

        function clearFile() {
            fileInput.value = '';
            selectedFileDiv.style.display = 'none';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation
        const form = document.getElementById('submissionForm');
        const submitBtn = document.getElementById('submitBtn');
        const textArea = document.getElementById('submission_text');

        form.addEventListener('submit', function(e) {
            const hasText = textArea.value.trim().length > 0;
            const hasFile = fileInput.files.length > 0;
            
            if (!hasText && !hasFile) {
                e.preventDefault();
                alert('Please provide either submission text or upload a file.');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        });

        // Auto-save draft (optional feature)
        let saveTimer;
        textArea.addEventListener('input', function() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function() {
                // Save draft to localStorage
                localStorage.setItem('assignment_' + <?php echo $assignment_id; ?> + '_draft', textArea.value);
            }, 2000);
        });

        // Load draft on page load
        document.addEventListener('DOMContentLoaded', function() {
            const draft = localStorage.getItem('assignment_' + <?php echo $assignment_id; ?> + '_draft');
            if (draft && !textArea.value) {
                textArea.value = draft;
            }
        });

        // Clear draft after successful submission
        <?php if ($success_message): ?>
        localStorage.removeItem('assignment_' + <?php echo $assignment_id; ?> + '_draft');
        <?php endif; ?>

        // Countdown timer for due date
        <?php if (!$is_overdue && !$existing_submission): ?>
        const dueDate = new Date('<?php echo date('c', strtotime($assignment['due_date'])); ?>');
        
        function updateCountdown() {
            const now = new Date();
            const timeLeft = dueDate - now;
            
            if (timeLeft <= 0) {
                location.reload(); // Refresh page when assignment becomes overdue
                return;
            }
            
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            
            let countdownText = '';
            if (days > 0) {
                countdownText = days + ' day' + (days > 1 ? 's' : '') + ' remaining';
            } else if (hours > 0) {
                countdownText = hours + ' hour' + (hours > 1 ? 's' : '') + ' ' + minutes + ' minute' + (minutes > 1 ? 's' : '') + ' remaining';
            } else {
                countdownText = minutes + ' minute' + (minutes > 1 ? 's' : '') + ' remaining';
            }
            
            // Update countdown display if element exists
            const countdownElement = document.querySelector('.text-info.small');
            if (countdownElement) {
                countdownElement.textContent = countdownText;
            }
        }
        
        // Update countdown every minute
        setInterval(updateCountdown, 60000);
        <?php endif; ?>
    </script>
</body>
</html>

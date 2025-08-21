<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get course_id from URL if provided
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_course = null;

// Get instructor's courses
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? AND status = 'active' ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// If course_id provided, get course details
if ($course_id) {
    $course_query = "SELECT id, title, course_code FROM courses WHERE id = ? AND instructor_id = ?";
    $course_stmt = $db->prepare($course_query);
    $course_stmt->execute([$course_id, $_SESSION['user_id']]);
    $selected_course = $course_stmt->fetch(PDO::FETCH_ASSOC);
}

$message = '';
$error = '';

if ($_POST) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $course_id = (int)$_POST['course_id'];
    $due_date = $_POST['due_date'];
    $max_points = (float)$_POST['max_points'];
    $instructions = trim($_POST['instructions']);
    $assignment_type = $_POST['assignment_type'];
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $late_penalty = (float)($_POST['late_penalty'] ?? 0);

    if (empty($title) || empty($description) || !$course_id || empty($due_date) || $max_points <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Verify course belongs to instructor
        $verify_query = "SELECT id FROM courses WHERE id = ? AND instructor_id = ?";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->execute([$course_id, $_SESSION['user_id']]);
        
        if ($verify_stmt->rowCount() == 0) {
            $error = "Invalid course selection.";
        } else {
            $insert_query = "INSERT INTO assignments (title, description, course_id, due_date, max_points, 
                           instructions, assignment_type, allow_late_submission, late_penalty, 
                           instructor_id, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $insert_stmt = $db->prepare($insert_query);
            if ($insert_stmt->execute([$title, $description, $course_id, $due_date, $max_points, 
                                     $instructions, $assignment_type, $allow_late_submission, 
                                     $late_penalty, $_SESSION['user_id']])) {
                $message = "Assignment created successfully!";
                // Clear form
                $title = $description = $instructions = '';
                $max_points = $late_penalty = 0;
                $due_date = '';
                $allow_late_submission = false;
            } else {
                $error = "Failed to create assignment. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - University LMS</title>
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

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        .settings-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input {
            border-radius: 6px;
            border: 2px solid #e2e8f0;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .assignment-preview {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
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
                <div class="page-header" data-aos="fade-down">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="fw-bold mb-2" style="color: #1f2937;">
                                <i class="fas fa-plus-circle text-success me-3"></i>Create New Assignment
                            </h1>
                            <p class="text-muted mb-0">Create engaging assignments for your students</p>
                            <?php if ($selected_course): ?>
                                <div class="mt-2">
                                    <span class="badge bg-success">
                                        <i class="fas fa-book me-1"></i>
                                        <?php echo htmlspecialchars($selected_course['course_code'] . ' - ' . $selected_course['title']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="assignments.php" class="btn btn-outline-info">
                                <i class="fas fa-list me-2"></i>View All Assignments
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success" data-aos="fade-up">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <div class="mt-2">
                            <a href="assignments.php" class="btn btn-sm btn-outline-success">View All Assignments</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" data-aos="fade-up">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Assignment Creation Form -->
                <div class="form-card" data-aos="fade-up" data-aos-delay="200">
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-12 mb-4">
                                <h5 class="fw-bold text-success mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Assignment Details
                                </h5>
                            </div>
                            
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Assignment Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                                       placeholder="e.g., Web Development Project - E-commerce Site" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="course_id" class="form-label">Select Course *</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Choose Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" 
                                                <?php echo ($selected_course && $course['id'] == $selected_course['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">Short Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Brief description of what students need to accomplish..." required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                            </div>

                            <div class="col-12 mb-4">
                                <label for="instructions" class="form-label">Detailed Instructions</label>
                                <textarea class="form-control" id="instructions" name="instructions" rows="6" 
                                          placeholder="Provide step-by-step instructions, requirements, grading criteria, submission format, etc..."><?php echo isset($instructions) ? htmlspecialchars($instructions) : ''; ?></textarea>
                            </div>

                            <!-- Assignment Settings -->
                            <div class="col-12 mb-4">
                                <h5 class="fw-bold text-success mb-3">
                                    <i class="fas fa-cog me-2"></i>Assignment Configuration
                                </h5>
                            </div>

                            <div class="form-row mb-3">
                                <div class="form-group">
                                    <label for="assignment_type" class="form-label">Assignment Type</label>
                                    <select class="form-select" id="assignment_type" name="assignment_type" required>
                                        <option value="project">Project</option>
                                        <option value="homework">Homework</option>
                                        <option value="essay">Essay</option>
                                        <option value="lab">Lab Exercise</option>
                                        <option value="presentation">Presentation</option>
                                        <option value="quiz">Quiz</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="max_points" class="form-label">Maximum Points *</label>
                                    <input type="number" class="form-control" id="max_points" name="max_points" 
                                           value="<?php echo isset($max_points) ? $max_points : '100'; ?>" 
                                           min="1" max="1000" step="0.1" placeholder="100" required>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="due_date" class="form-label">Due Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo isset($due_date) ? $due_date : ''; ?>" required>
                            </div>

                            <!-- Advanced Settings -->
                            <div class="col-12">
                                <div class="settings-card">
                                    <h6 class="fw-bold text-secondary mb-3">
                                        <i class="fas fa-sliders-h me-2"></i>Advanced Settings
                                    </h6>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="allow_late_submission" 
                                               name="allow_late_submission" value="1" 
                                               <?php echo (isset($allow_late_submission) && $allow_late_submission) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="allow_late_submission">
                                            Allow Late Submissions
                                        </label>
                                        <div class="form-text">Students can submit after the due date with penalty</div>
                                    </div>
                                    
                                    <div class="row" id="late_penalty_section" style="display: none;">
                                        <div class="col-md-4">
                                            <label for="late_penalty" class="form-label">Late Penalty (%)</label>
                                            <input type="number" class="form-control" id="late_penalty" name="late_penalty" 
                                                   value="<?php echo isset($late_penalty) ? $late_penalty : '10'; ?>" 
                                                   min="0" max="100" step="1" placeholder="10">
                                            <div class="form-text">Percentage deducted per day late</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Section -->
                            <div class="col-12 mt-4">
                                <div class="assignment-preview">
                                    <h6 class="fw-bold text-secondary mb-2">
                                        <i class="fas fa-eye me-2"></i>Assignment Preview
                                    </h6>
                                    <p class="text-muted small">This is how your assignment will appear to students once published.</p>
                                    
                                    <div class="preview-content" id="preview-content">
                                        <div class="placeholder-content text-muted fst-italic">
                                            Fill in the form above to see a preview of your assignment
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="col-12 mt-4">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create Assignment
                                    </button>
                                    <a href="assignments.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
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

        // Toggle late penalty section
        document.getElementById('allow_late_submission').addEventListener('change', function() {
            const section = document.getElementById('late_penalty_section');
            if (this.checked) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });

        // Real-time preview
        function updatePreview() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const maxPoints = document.getElementById('max_points').value;
            const dueDate = document.getElementById('due_date').value;
            const assignmentType = document.getElementById('assignment_type').value;
            
            const previewContent = document.getElementById('preview-content');
            
            if (title || description) {
                let preview = '<div class="border-start border-4 border-success ps-3">';
                if (title) {
                    preview += `<h5 class="fw-bold text-dark">${title}</h5>`;
                }
                if (assignmentType) {
                    preview += `<span class="badge bg-info mb-2">${assignmentType.charAt(0).toUpperCase() + assignmentType.slice(1)}</span>`;
                }
                if (description) {
                    preview += `<p class="text-muted">${description}</p>`;
                }
                preview += '<div class="d-flex gap-3 small text-muted mt-2">';
                if (maxPoints) {
                    preview += `<div><i class="fas fa-star me-1"></i>Max Points: ${maxPoints}</div>`;
                }
                if (dueDate) {
                    const date = new Date(dueDate);
                    preview += `<div><i class="fas fa-calendar me-1"></i>Due: ${date.toLocaleDateString()} ${date.toLocaleTimeString()}</div>`;
                }
                preview += '</div></div>';
                previewContent.innerHTML = preview;
            } else {
                previewContent.innerHTML = '<div class="placeholder-content text-muted fst-italic">Fill in the form above to see a preview of your assignment</div>';
            }
        }

        // Add event listeners for preview
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('description').addEventListener('input', updatePreview);
        document.getElementById('max_points').addEventListener('input', updatePreview);
        document.getElementById('due_date').addEventListener('input', updatePreview);
        document.getElementById('assignment_type').addEventListener('change', updatePreview);

        // Initialize late penalty section state
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('allow_late_submission');
            if (checkbox.checked) {
                document.getElementById('late_penalty_section').style.display = 'block';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const dueDate = new Date(document.getElementById('due_date').value);
            const now = new Date();
            
            if (dueDate <= now) {
                const confirm = window.confirm('The due date is in the past. Are you sure you want to create this assignment?');
                if (!confirm) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>

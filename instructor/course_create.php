<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

if ($_POST) {
    $title = trim($_POST['title']);
    $course_code = trim($_POST['course_code']);
    $description = trim($_POST['description']);
    $credits = (int)$_POST['credits'];
    $semester = trim($_POST['semester']);
    $year = (int)$_POST['year'];
    $max_students = (int)$_POST['max_students'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (empty($title) || empty($course_code) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if course code already exists
        $check_query = "SELECT id FROM courses WHERE course_code = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$course_code]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Course code already exists. Please choose a different code.";
        } else {
            $query = "INSERT INTO courses (title, course_code, description, credits, semester, year, 
                     max_students, start_date, end_date, instructor_id, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $stmt = $db->prepare($query);
            if ($stmt->execute([$title, $course_code, $description, $credits, $semester, 
                               $year, $max_students, $start_date, $end_date, $_SESSION['user_id']])) {
                $message = "Course created successfully!";
                // Clear form
                $title = $course_code = $description = $semester = '';
                $credits = $max_students = $year = 0;
                $start_date = $end_date = '';
            } else {
                $error = "Failed to create course. Please try again.";
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
    <title>Create Course - University LMS</title>
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

        .form-control {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
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
                                <i class="fas fa-plus text-success me-3"></i>Create New Course
                            </h1>
                            <p class="text-muted mb-0">Set up a new course for your students</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
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

                <!-- Course Creation Form -->
                <div class="form-card" data-aos="fade-up" data-aos-delay="200">
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-12 mb-4">
                                <h5 class="fw-bold text-success mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Basic Information
                                </h5>
                            </div>
                            
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Course Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                                       placeholder="e.g., Introduction to Web Development" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="course_code" class="form-label">Course Code *</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" 
                                       value="<?php echo isset($course_code) ? htmlspecialchars($course_code) : ''; ?>" 
                                       placeholder="e.g., CS301" required>
                            </div>
                            
                            <div class="col-12 mb-4">
                                <label for="description" class="form-label">Course Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Describe what students will learn in this course..." required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                            </div>

                            <!-- Course Details -->
                            <div class="col-12 mb-4">
                                <h5 class="fw-bold text-success mb-3">
                                    <i class="fas fa-cog me-2"></i>Course Settings
                                </h5>
                            </div>

                            <div class="form-row mb-3">
                                <div class="form-group">
                                    <label for="credits" class="form-label">Credits</label>
                                    <select class="form-control" id="credits" name="credits" required>
                                        <option value="">Select Credits</option>
                                        <option value="1" <?php echo (isset($credits) && $credits == 1) ? 'selected' : ''; ?>>1 Credit</option>
                                        <option value="2" <?php echo (isset($credits) && $credits == 2) ? 'selected' : ''; ?>>2 Credits</option>
                                        <option value="3" <?php echo (isset($credits) && $credits == 3) ? 'selected' : ''; ?>>3 Credits</option>
                                        <option value="4" <?php echo (isset($credits) && $credits == 4) ? 'selected' : ''; ?>>4 Credits</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="max_students" class="form-label">Max Students</label>
                                    <input type="number" class="form-control" id="max_students" name="max_students" 
                                           value="<?php echo isset($max_students) ? $max_students : '30'; ?>" 
                                           min="1" max="200" placeholder="30">
                                </div>
                            </div>

                            <div class="form-row mb-3">
                                <div class="form-group">
                                    <label for="semester" class="form-label">Semester</label>
                                    <select class="form-control" id="semester" name="semester" required>
                                        <option value="">Select Semester</option>
                                        <option value="Fall" <?php echo (isset($semester) && $semester == 'Fall') ? 'selected' : ''; ?>>Fall</option>
                                        <option value="Spring" <?php echo (isset($semester) && $semester == 'Spring') ? 'selected' : ''; ?>>Spring</option>
                                        <option value="Summer" <?php echo (isset($semester) && $semester == 'Summer') ? 'selected' : ''; ?>>Summer</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="year" class="form-label">Year</label>
                                    <input type="number" class="form-control" id="year" name="year" 
                                           value="<?php echo isset($year) ? $year : date('Y'); ?>" 
                                           min="2020" max="2030" placeholder="<?php echo date('Y'); ?>">
                                </div>
                            </div>

                            <!-- Schedule -->
                            <div class="col-12 mb-4">
                                <h5 class="fw-bold text-success mb-3">
                                    <i class="fas fa-calendar me-2"></i>Course Schedule
                                </h5>
                            </div>

                            <div class="form-row mb-4">
                                <div class="form-group">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo isset($start_date) ? $start_date : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo isset($end_date) ? $end_date : ''; ?>" required>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create Course
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (startDate >= endDate) {
                e.preventDefault();
                alert('End date must be after start date.');
            }
        });
    </script>
</body>
</html>

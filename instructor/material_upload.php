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

// Get instructor's courses
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? AND status = 'active' ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course_id from URL if provided
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($_POST) {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($course_id) || empty($title) || !isset($_FILES['material_file'])) {
        $error = "Please fill in all required fields and select a file.";
    } else {
        // Verify course belongs to instructor
        $course_check = "SELECT id FROM courses WHERE id = ? AND instructor_id = ?";
        $course_stmt = $db->prepare($course_check);
        $course_stmt->execute([$course_id, $_SESSION['user_id']]);
        
        if (!$course_stmt->fetch()) {
            $error = "Invalid course selected.";
        } else {
            $file = $_FILES['material_file'];
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "File upload failed. Please try again.";
            } else {
                // Define allowed file types and maximum size
                $allowed_types = [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'txt', 'zip', 'rar', '7z', 
                    'jpg', 'jpeg', 'png', 'gif', 'bmp',
                    'mp4', 'avi', 'mov', 'wmv', 'flv',
                    'mp3', 'wav', 'aac', 'flac'
                ];
                $max_size = 50 * 1024 * 1024; // 50MB
                
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $file_size = $file['size'];
                
                if (!in_array($file_extension, $allowed_types)) {
                    $error = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
                } elseif ($file_size > $max_size) {
                    $error = "File size too large. Maximum size: 50MB";
                } else {
                    // Generate unique filename
                    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
                    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
                    $unique_name = $safe_name . '_' . time() . '.' . $file_extension;
                    
                    // Create upload path
                    $upload_dir = '../uploads/materials/';
                    $file_path = $upload_dir . $unique_name;
                    $db_path = 'uploads/materials/' . $unique_name;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Insert into database
                        $insert_query = "INSERT INTO course_materials (course_id, title, description, file_path, file_type, file_size, uploaded_by) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $db->prepare($insert_query);
                        
                        if ($insert_stmt->execute([
                            $course_id, 
                            $title, 
                            $description, 
                            $db_path, 
                            $file_extension, 
                            $file_size, 
                            $_SESSION['user_id']
                        ])) {
                            $message = "Material uploaded successfully!";
                            
                            // Clear form data
                            $title = '';
                            $description = '';
                            $selected_course_id = 0;
                        } else {
                            $error = "Failed to save material information to database.";
                            // Remove uploaded file if database insert failed
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    } else {
                        $error = "Failed to upload file. Please check permissions.";
                    }
                }
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
    <title>Upload Material - University LMS</title>
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
            --accent: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
        }

        .upload-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .upload-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid var(--gray-200);
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(5, 150, 105, 0.25);
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: var(--gray-100);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(5, 150, 105, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--primary);
            background: rgba(5, 150, 105, 0.1);
        }

        .file-info {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            display: none;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .allowed-types {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .allowed-types h6 {
            color: var(--gray-800);
            margin-bottom: 1rem;
        }

        .type-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin: 0.25rem;
            display: inline-block;
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
                                <i class="fas fa-cloud-upload-alt text-success me-3"></i>Upload Material
                            </h1>
                            <p class="text-muted mb-0">Share learning materials with your students</p>
                        </div>
                        <a href="materials.php" class="btn btn-outline-secondary btn-modern">
                            <i class="fas fa-arrow-left me-2"></i>Back to Materials
                        </a>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success" data-aos="fade-up">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" data-aos="fade-up">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="row justify-content-center">
                    <div class="col-xl-8">
                        <div class="upload-card" data-aos="fade-up" data-aos-delay="200">
                            <div class="upload-header">
                                <h5 class="m-0 fw-bold d-flex align-items-center">
                                    <i class="fas fa-upload me-3"></i>Upload Course Material
                                </h5>
                                <p class="mb-0 opacity-90 small">Share documents, presentations, and other learning resources</p>
                            </div>
                            <div class="card-body p-4">
                                <?php if (empty($courses)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <h5 class="text-muted mt-3">No Active Courses</h5>
                                        <p class="text-muted mb-4">You need to create at least one active course before uploading materials.</p>
                                        <a href="course_create.php" class="btn btn-success btn-modern">
                                            <i class="fas fa-plus me-2"></i>Create Course
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-12 mb-4">
                                                <label for="course_id" class="form-label">
                                                    <i class="fas fa-book me-2"></i>Select Course *
                                                </label>
                                                <select class="form-select" name="course_id" id="course_id" required>
                                                    <option value="">Choose a course...</option>
                                                    <?php foreach ($courses as $course): ?>
                                                        <option value="<?php echo $course['id']; ?>" 
                                                                <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-12 mb-4">
                                                <label for="title" class="form-label">
                                                    <i class="fas fa-heading me-2"></i>Material Title *
                                                </label>
                                                <input type="text" class="form-control" name="title" id="title" 
                                                       value="<?php echo htmlspecialchars($title ?? ''); ?>" 
                                                       placeholder="e.g., Lecture 1 - Introduction to Programming" required>
                                            </div>

                                            <div class="col-md-12 mb-4">
                                                <label for="description" class="form-label">
                                                    <i class="fas fa-align-left me-2"></i>Description
                                                </label>
                                                <textarea class="form-control" name="description" id="description" rows="3" 
                                                          placeholder="Brief description of the material content (optional)"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                            </div>

                                            <div class="col-md-12 mb-4">
                                                <label for="material_file" class="form-label">
                                                    <i class="fas fa-file me-2"></i>Select File *
                                                </label>
                                                <div class="file-upload-area" id="fileUploadArea">
                                                    <i class="fas fa-cloud-upload-alt text-muted mb-3" style="font-size: 3rem;"></i>
                                                    <h6 class="text-muted">Drag and drop your file here</h6>
                                                    <p class="text-muted mb-3">or</p>
                                                    <input type="file" class="form-control" name="material_file" id="material_file" 
                                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z,.jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.flv,.mp3,.wav,.aac,.flac" 
                                                           style="display: none;" required>
                                                    <button type="button" class="btn btn-outline-primary btn-modern" onclick="document.getElementById('material_file').click()">
                                                        <i class="fas fa-folder-open me-2"></i>Choose File
                                                    </button>
                                                </div>
                                                <div class="file-info" id="fileInfo">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-file text-primary me-3" style="font-size: 2rem;"></i>
                                                        <div>
                                                            <h6 class="mb-1" id="fileName"></h6>
                                                            <small class="text-muted" id="fileSize"></small>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearFile()">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="materials.php" class="btn btn-outline-secondary btn-modern">
                                                <i class="fas fa-arrow-left me-2"></i>Cancel
                                            </a>
                                            <button type="submit" class="btn btn-success btn-modern">
                                                <i class="fas fa-cloud-upload-alt me-2"></i>Upload Material
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Allowed File Types -->
                                    <div class="allowed-types mt-4">
                                        <h6><i class="fas fa-info-circle me-2"></i>Allowed File Types & Limits</h6>
                                        <p class="text-muted mb-2">Maximum file size: <strong>50MB</strong></p>
                                        <div class="mb-3">
                                            <div class="type-badge">PDF</div>
                                            <div class="type-badge">DOC</div>
                                            <div class="type-badge">DOCX</div>
                                            <div class="type-badge">XLS</div>
                                            <div class="type-badge">XLSX</div>
                                            <div class="type-badge">PPT</div>
                                            <div class="type-badge">PPTX</div>
                                            <div class="type-badge">TXT</div>
                                            <div class="type-badge">ZIP</div>
                                            <div class="type-badge">RAR</div>
                                            <div class="type-badge">7Z</div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="type-badge">JPG</div>
                                            <div class="type-badge">PNG</div>
                                            <div class="type-badge">GIF</div>
                                            <div class="type-badge">BMP</div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="type-badge">MP4</div>
                                            <div class="type-badge">AVI</div>
                                            <div class="type-badge">MOV</div>
                                            <div class="type-badge">MP3</div>
                                            <div class="type-badge">WAV</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
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
            once: true
        });

        // File upload handling
        const fileInput = document.getElementById('material_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        // File input change
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                showFileInfo(this.files[0]);
            }
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showFileInfo(e.dataTransfer.files[0]);
            }
        });

        function showFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const courseId = document.getElementById('course_id').value;
            const title = document.getElementById('title').value.trim();
            const file = document.getElementById('material_file').files[0];

            if (!courseId || !title || !file) {
                e.preventDefault();
                alert('Please fill in all required fields and select a file.');
                return false;
            }

            // Check file size
            if (file.size > 50 * 1024 * 1024) {
                e.preventDefault();
                alert('File size exceeds 50MB limit.');
                return false;
            }

            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            submitBtn.disabled = true;
        });

        // Auto-fill title from filename
        fileInput.addEventListener('change', function() {
            const titleInput = document.getElementById('title');
            if (!titleInput.value && this.files[0]) {
                const filename = this.files[0].name;
                const nameWithoutExt = filename.substring(0, filename.lastIndexOf('.')) || filename;
                titleInput.value = nameWithoutExt.replace(/[_-]/g, ' ');
            }
        });

        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.6s ease-in-out';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>

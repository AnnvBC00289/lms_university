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

// Handle material deletion
if (isset($_POST['delete_material']) && isset($_POST['material_id'])) {
    $material_id = (int)$_POST['material_id'];
    
    // Get file path before deletion
    $file_query = "SELECT file_path, uploaded_by FROM course_materials WHERE id = ?";
    $file_stmt = $db->prepare($file_query);
    $file_stmt->execute([$material_id]);
    $material = $file_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($material && $material['uploaded_by'] == $_SESSION['user_id']) {
        // Delete file from server
        if ($material['file_path'] && file_exists('../' . $material['file_path'])) {
            unlink('../' . $material['file_path']);
        }
        
        // Delete from database
        $delete_query = "DELETE FROM course_materials WHERE id = ? AND uploaded_by = ?";
        $delete_stmt = $db->prepare($delete_query);
        if ($delete_stmt->execute([$material_id, $_SESSION['user_id']])) {
            $message = "Material deleted successfully.";
        } else {
            $error = "Failed to delete material.";
        }
    } else {
        $error = "Material not found or you don't have permission to delete it.";
    }
}

// Get instructor's courses
$courses_query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? AND status = 'active' ORDER BY title";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by course if selected
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get materials based on filter
if ($selected_course) {
    $materials_query = "SELECT cm.*, c.title as course_title, c.course_code 
                        FROM course_materials cm
                        JOIN courses c ON cm.course_id = c.id
                        WHERE c.instructor_id = ? AND cm.course_id = ?
                        ORDER BY cm.upload_date DESC";
    $materials_stmt = $db->prepare($materials_query);
    $materials_stmt->execute([$_SESSION['user_id'], $selected_course]);
} else {
    $materials_query = "SELECT cm.*, c.title as course_title, c.course_code 
                        FROM course_materials cm
                        JOIN courses c ON cm.course_id = c.id
                        WHERE c.instructor_id = ?
                        ORDER BY cm.upload_date DESC";
    $materials_stmt = $db->prepare($materials_query);
    $materials_stmt->execute([$_SESSION['user_id']]);
}
$materials = $materials_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_materials,
                    SUM(file_size) as total_size
                FROM course_materials cm
                JOIN courses c ON cm.course_id = c.id
                WHERE c.instructor_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($file_type) {
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ppt' => 'fas fa-file-powerpoint text-warning',
        'pptx' => 'fas fa-file-powerpoint text-warning',
        'txt' => 'fas fa-file-alt text-muted',
        'zip' => 'fas fa-file-archive text-info',
        'rar' => 'fas fa-file-archive text-info',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'gif' => 'fas fa-file-image text-success',
        'mp4' => 'fas fa-file-video text-danger',
        'avi' => 'fas fa-file-video text-danger',
        'mp3' => 'fas fa-file-audio text-warning',
        'wav' => 'fas fa-file-audio text-warning'
    ];
    
    return $icons[$file_type] ?? 'fas fa-file text-muted';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - University LMS</title>
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

        .materials-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .materials-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .material-item {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .material-item:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .material-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
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
                                <i class="fas fa-file-alt text-success me-3"></i>Course Materials
                            </h1>
                            <p class="text-muted mb-0">Upload and manage course materials for your students</p>
                        </div>
                        <a href="material_upload.php" class="btn btn-success btn-modern">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Upload Material
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

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="material-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                                    <i class="fas fa-file-alt text-white"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1 fw-bold"><?php echo $stats['total_materials'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Materials</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="material-icon" style="background: linear-gradient(135deg, var(--info), #0e7490);">
                                    <i class="fas fa-hdd text-white"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1 fw-bold"><?php echo formatFileSize($stats['total_size'] ?? 0); ?></h3>
                                    <p class="text-muted mb-0">Total Storage Used</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section" data-aos="fade-up" data-aos-delay="300">
                    <form method="GET" class="d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($selected_course == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selected_course): ?>
                            <a href="materials.php" class="btn btn-outline-secondary btn-modern">
                                <i class="fas fa-times me-1"></i>Clear Filter
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Materials List -->
                <div class="materials-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="materials-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="m-0 fw-bold">
                                    <i class="fas fa-folder-open me-2"></i>
                                    <?php echo $selected_course ? 'Filtered Materials' : 'All Materials'; ?>
                                </h5>
                                <p class="mb-0 opacity-90 small">
                                    <?php echo count($materials); ?> materials found
                                </p>
                            </div>
                            <a href="material_upload.php" class="btn btn-light btn-modern">
                                <i class="fas fa-plus me-2"></i>Add Material
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($materials)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <h5 class="text-muted mb-3">No Materials Found</h5>
                                <p class="text-muted mb-4">
                                    <?php if ($selected_course): ?>
                                        No materials uploaded for the selected course yet.
                                    <?php else: ?>
                                        You haven't uploaded any course materials yet. Start by uploading your first material!
                                    <?php endif; ?>
                                </p>
                                <a href="material_upload.php" class="btn btn-success btn-modern">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>Upload Your First Material
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-item">
                                    <div class="d-flex align-items-center">
                                        <div class="material-icon" style="background: var(--gray-100);">
                                            <i class="<?php echo getFileIcon($material['file_type']); ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($material['title']); ?></h6>
                                            <div class="small text-muted mb-2">
                                                <?php if ($material['description']): ?>
                                                    <p class="mb-1"><?php echo htmlspecialchars($material['description']); ?></p>
                                                <?php endif; ?>
                                                <div class="d-flex gap-3">
                                                    <span>
                                                        <i class="fas fa-book me-1"></i>
                                                        <?php echo htmlspecialchars($material['course_code'] . ' - ' . $material['course_title']); ?>
                                                    </span>
                                                    <span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($material['upload_date'])); ?>
                                                    </span>
                                                    <span>
                                                        <i class="fas fa-weight me-1"></i>
                                                        <?php echo formatFileSize($material['file_size']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <a href="material_download.php?id=<?php echo $material['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary btn-modern" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-modern" 
                                                    title="Delete" onclick="confirmDelete(<?php echo $material['id']; ?>, '<?php echo addslashes($material['title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="materialTitle"></span>"?</p>
                    <p class="text-muted small">This action cannot be undone. The file will be permanently deleted from the server.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="material_id" id="deleteId">
                        <button type="submit" name="delete_material" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
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

        function confirmDelete(id, title) {
            document.getElementById('deleteId').value = id;
            document.getElementById('materialTitle').textContent = title;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

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

<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$course_filter = (int)($_GET['course'] ?? 0);
$type_filter = $_GET['type'] ?? '';

// Build query conditions
$conditions = ['e.student_id = ?'];
$params = [$_SESSION['user_id']];

if ($course_filter > 0) {
    $conditions[] = 'c.id = ?';
    $params[] = $course_filter;
}

if ($type_filter) {
    $conditions[] = 'cm.file_type LIKE ?';
    $params[] = '%' . $type_filter . '%';
}

// Get materials from enrolled courses
$query = "SELECT cm.*, c.title as course_title, c.course_code, c.id as course_id,
          u.first_name, u.last_name
          FROM course_materials cm
          JOIN courses c ON cm.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          JOIN users u ON cm.uploaded_by = u.id
          WHERE " . implode(' AND ', $conditions) . " AND e.status = 'enrolled'
          ORDER BY cm.upload_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course filter options
$query = "SELECT c.id, c.title, c.course_code
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          WHERE e.student_id = ? AND e.status = 'enrolled' AND c.status = 'active'
          ORDER BY c.course_code";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get file type statistics
$file_types = [];
foreach ($materials as $material) {
    $ext = pathinfo($material['file_path'], PATHINFO_EXTENSION);
    $type = strtoupper($ext);
    if (!isset($file_types[$type])) {
        $file_types[$type] = 0;
    }
    $file_types[$type]++;
}

// Calculate total size
$total_size = array_sum(array_column($materials, 'file_size'));
$total_size_mb = round($total_size / (1024 * 1024), 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <style>
        .material-card {
            transition: transform 0.2s ease-in-out;
            border: 1px solid #dee2e6;
        }
        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .file-type-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }
        .download-btn {
            transition: all 0.2s;
        }
        .download-btn:hover {
            transform: scale(1.05);
        }
        .card-body {
            padding: 1rem;
        }
        .stats-card .card-body {
            padding: 1.25rem;
        }
        .material-card .card-body {
            padding: 1rem;
        }
        .card-header {
            padding: 0.75rem 1rem;
        }
        .card-title {
            margin-bottom: 0.5rem;
        }
        .card-text {
            margin-bottom: 0.75rem;
        }
        .mb-3 {
            margin-bottom: 1rem !important;
        }
        .mt-3 {
            margin-top: 1rem !important;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-3">
                    <h1 class="h2">
                        <i class="fas fa-file-alt text-primary me-2"></i>
                        Course Materials
                    </h1>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="exportMaterials()">
                            <i class="fas fa-download me-2"></i>Export List
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printMaterials()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Materials</h6>
                                        <h3 class="mb-0"><?php echo count($materials); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-file-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Size</h6>
                                        <h3 class="mb-0"><?php echo $total_size_mb; ?> MB</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-hdd fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">File Types</h6>
                                        <h3 class="mb-0"><?php echo count($file_types); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-folder fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Courses</h6>
                                        <h3 class="mb-0"><?php echo count($courses); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-book fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="course_filter" class="form-label">Filter by Course</label>
                                <select class="form-select" id="course_filter" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="type_filter" class="form-label">Filter by File Type</label>
                                <select class="form-select" id="type_filter" name="type">
                                    <option value="">All Types</option>
                                    <option value="pdf" <?php echo ($type_filter == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                                    <option value="doc" <?php echo ($type_filter == 'doc') ? 'selected' : ''; ?>>DOC/DOCX</option>
                                    <option value="ppt" <?php echo ($type_filter == 'ppt') ? 'selected' : ''; ?>>PPT/PPTX</option>
                                    <option value="xls" <?php echo ($type_filter == 'xls') ? 'selected' : ''; ?>>XLS/XLSX</option>
                                    <option value="txt" <?php echo ($type_filter == 'txt') ? 'selected' : ''; ?>>TXT</option>
                                    <option value="zip" <?php echo ($type_filter == 'zip') ? 'selected' : ''; ?>>ZIP/RAR</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="materials.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Materials Grid -->
                <div class="row">
                    <?php if (empty($materials)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5>No Materials Found</h5>
                                    <p class="text-muted">No course materials are available for your enrolled courses.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card material-card h-100">
                                    <div class="card-body text-center">
                                        <?php
                                        $ext = strtolower(pathinfo($material['file_path'], PATHINFO_EXTENSION));
                                        $icon_class = 'fa-file';
                                        $color_class = 'text-primary';
                                        
                                        switch ($ext) {
                                            case 'pdf':
                                                $icon_class = 'fa-file-pdf';
                                                $color_class = 'text-danger';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $icon_class = 'fa-file-word';
                                                $color_class = 'text-primary';
                                                break;
                                            case 'ppt':
                                            case 'pptx':
                                                $icon_class = 'fa-file-powerpoint';
                                                $color_class = 'text-warning';
                                                break;
                                            case 'xls':
                                            case 'xlsx':
                                                $icon_class = 'fa-file-excel';
                                                $color_class = 'text-success';
                                                break;
                                            case 'txt':
                                                $icon_class = 'fa-file-alt';
                                                $color_class = 'text-secondary';
                                                break;
                                            case 'zip':
                                            case 'rar':
                                                $icon_class = 'fa-file-archive';
                                                $color_class = 'text-info';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif':
                                                $icon_class = 'fa-file-image';
                                                $color_class = 'text-success';
                                                break;
                                        }
                                        ?>
                                        
                                        <div class="file-icon <?php echo $color_class; ?>">
                                            <i class="fas <?php echo $icon_class; ?>"></i>
                                        </div>
                                        
                                        <h6 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h6>
                                        
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars($material['course_code'] . ' - ' . $material['course_title']); ?>
                                        </p>
                                        
                                        <?php if ($material['description']): ?>
                                            <p class="card-text small">
                                                <?php echo htmlspecialchars(substr($material['description'], 0, 100)) . (strlen($material['description']) > 100 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="mb-2">
                                            <span class="badge bg-secondary file-type-badge">
                                                <?php echo strtoupper($ext); ?>
                                            </span>
                                            <span class="badge bg-light text-dark file-type-badge">
                                                <?php echo round($material['file_size'] / 1024, 1); ?> KB
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($material['first_name'] . ' ' . $material['last_name']); ?>
                                            </small>
                                            <a href="material_download.php?id=<?php echo $material['id']; ?>" 
                                               class="btn btn-primary btn-sm download-btn" 
                                               title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                        
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M j, Y', strtotime($material['upload_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- File Type Distribution -->
                <?php if (!empty($file_types)): ?>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>File Type Distribution
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($file_types as $type => $count): ?>
                                            <div class="col-6 mb-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-primary"><?php echo $type; ?></span>
                                                    <span class="fw-bold"><?php echo $count; ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Quick Info
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i>All materials are from your enrolled courses</li>
                                        <li class="mb-1"><i class="fas fa-download text-primary me-2"></i>Click download button to access files</li>
                                        <li class="mb-1"><i class="fas fa-filter text-info me-2"></i>Use filters to find specific materials</li>
                                        <li class="mb-1"><i class="fas fa-clock text-warning me-2"></i>Materials are updated regularly by instructors</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportMaterials() {
            alert('Export functionality will be implemented soon!');
        }

        function printMaterials() {
            window.print();
        }

        // Auto-submit form when filters change
        document.getElementById('course_filter').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('type_filter').addEventListener('change', function() {
            this.form.submit();
        });

        // Add click handlers for material cards
        document.addEventListener('DOMContentLoaded', function() {
            const materialCards = document.querySelectorAll('.material-card');
            materialCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on download button
                    if (!e.target.closest('.download-btn')) {
                        const downloadBtn = this.querySelector('.download-btn');
                        if (downloadBtn) {
                            downloadBtn.click();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>




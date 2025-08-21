<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle settings update
if (isset($_POST['update_settings'])) {
    $settings = [];
    $settings['site_name'] = sanitizeInput($_POST['site_name']);
    $settings['site_description'] = sanitizeInput($_POST['site_description']);
    $settings['admin_email'] = sanitizeInput($_POST['admin_email']);
    $settings['timezone'] = sanitizeInput($_POST['timezone']);
    $settings['date_format'] = sanitizeInput($_POST['date_format']);
    $settings['max_upload_size'] = (int)$_POST['max_upload_size'];
    $settings['allow_registration'] = isset($_POST['allow_registration']) ? 1 : 0;
    $settings['require_email_verification'] = isset($_POST['require_email_verification']) ? 1 : 0;
    $settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Save to file or database (for this demo, we'll use session to simulate saving)
    $_SESSION['system_settings'] = $settings;
    $_SESSION['success'] = "Settings updated successfully";
    
    header('Location: settings.php');
    exit();
}

// Handle backup creation
if (isset($_POST['create_backup'])) {
    try {
        // This is a simulation - in real implementation, you would use mysqldump
        $backup_filename = 'lms_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $_SESSION['success'] = "Backup created successfully: $backup_filename";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating backup: " . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit();
}

// Get current settings (simulated with default values)
$settings = isset($_SESSION['system_settings']) ? $_SESSION['system_settings'] : [
    'site_name' => 'University LMS',
    'site_description' => 'Advanced Learning Management System',
    'admin_email' => 'admin@university.edu',
    'timezone' => 'America/New_York',
    'date_format' => 'M j, Y',
    'max_upload_size' => 10,
    'allow_registration' => 1,
    'require_email_verification' => 0,
    'maintenance_mode' => 0
];

// Get system information
$system_info = [];
$system_info['php_version'] = phpversion();
$system_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$system_info['upload_max_filesize'] = ini_get('upload_max_filesize');
$system_info['post_max_size'] = ini_get('post_max_size');
$system_info['memory_limit'] = ini_get('memory_limit');

// Get database statistics
try {
    $query = "SELECT 
                COUNT(*) as total_users
              FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $system_info['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    $query = "SELECT 
                COUNT(*) as total_courses
              FROM courses";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $system_info['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_courses'];

    // Get database size (approximate)
    $query = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size_mb
              FROM information_schema.tables 
              WHERE table_schema = DATABASE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $system_info['database_size'] = $result['db_size_mb'] ?? 'Unknown';
} catch (Exception $e) {
    $system_info['total_users'] = 'Unknown';
    $system_info['total_courses'] = 'Unknown';
    $system_info['database_size'] = 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
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

        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .settings-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 2rem;
            border: none;
        }

        .settings-body {
            padding: 2rem;
        }

        .info-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }

        .info-item {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .info-item:last-child {
            margin-bottom: 0;
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

        .form-check-input {
            border-radius: 6px;
            border: 2px solid #e2e8f0;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
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

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 12px;
            margin-right: 0.5rem;
            color: var(--dark);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            background: rgba(124, 58, 237, 0.1);
            color: var(--primary);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
            border: none;
        }

        .backup-card {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .system-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-online {
            background: #d1fae5;
            color: #065f46;
        }

        .status-maintenance {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="dashboard-page">
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
                                <i class="fas fa-cog text-primary me-3"></i>System Settings
                            </h1>
                            <p class="text-muted mb-0">Configure system preferences and maintenance</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="system-status <?php echo $settings['maintenance_mode'] ? 'status-maintenance' : 'status-online'; ?>">
                                <i class="fas fa-circle me-1"></i>
                                <?php echo $settings['maintenance_mode'] ? 'Maintenance' : 'Online'; ?>
                            </span>
                        </div>
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

                <!-- Settings Tabs -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                            <i class="fas fa-sliders-h me-2"></i>General Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                            <i class="fas fa-server me-2"></i>System Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
                            <i class="fas fa-database me-2"></i>Backup & Restore
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-sliders-h me-2"></i>General Configuration
                                </h5>
                            </div>
                            <div class="settings-body">
                                <form method="POST">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Site Name</label>
                                            <input type="text" class="form-control" name="site_name" 
                                                   value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Admin Email</label>
                                            <input type="email" class="form-control" name="admin_email" 
                                                   value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Site Description</label>
                                            <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (US & Canada)</option>
                                                <option value="America/Chicago" <?php echo $settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time (US & Canada)</option>
                                                <option value="America/Denver" <?php echo $settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (US & Canada)</option>
                                                <option value="America/Los_Angeles" <?php echo $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (US & Canada)</option>
                                                <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Date Format</label>
                                            <select class="form-select" name="date_format">
                                                <option value="M j, Y" <?php echo $settings['date_format'] === 'M j, Y' ? 'selected' : ''; ?>>Jan 15, 2024</option>
                                                <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024</option>
                                                <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024</option>
                                                <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Max Upload Size (MB)</label>
                                            <input type="number" class="form-control" name="max_upload_size" min="1" max="100"
                                                   value="<?php echo $settings['max_upload_size']; ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <h6 class="fw-bold mb-3 text-muted">System Permissions</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="allow_registration" 
                                                               <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold">
                                                            Allow User Registration
                                                        </label>
                                                        <div class="form-text">Allow new users to register accounts</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="require_email_verification" 
                                                               <?php echo $settings['require_email_verification'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold">
                                                            Require Email Verification
                                                        </label>
                                                        <div class="form-text">Users must verify email before access</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                                               <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold text-warning">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Maintenance Mode
                                                        </label>
                                                        <div class="form-text text-warning">Only admins can access the system</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 pt-3 border-top">
                                        <button type="submit" name="update_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Settings
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="location.reload()">
                                            <i class="fas fa-undo me-2"></i>Reset Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-server me-2"></i>System Information
                                </h5>
                            </div>
                            <div class="settings-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-code text-primary me-2"></i>Server Environment
                                            </h6>
                                            <div class="info-item">
                                                <div>
                                                    <strong>PHP Version</strong>
                                                    <br><small class="text-muted">Runtime environment</small>
                                                </div>
                                                <span class="badge bg-primary"><?php echo $system_info['php_version']; ?></span>
                                            </div>
                                            <div class="info-item">
                                                <div>
                                                    <strong>Server Software</strong>
                                                    <br><small class="text-muted">Web server</small>
                                                </div>
                                                <span class="badge bg-info"><?php echo $system_info['server_software']; ?></span>
                                            </div>
                                            <div class="info-item">
                                                <div>
                                                    <strong>Memory Limit</strong>
                                                    <br><small class="text-muted">PHP memory allocation</small>
                                                </div>
                                                <span class="badge bg-success"><?php echo $system_info['memory_limit']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-upload text-primary me-2"></i>Upload Configuration
                                            </h6>
                                            <div class="info-item">
                                                <div>
                                                    <strong>Max File Size</strong>
                                                    <br><small class="text-muted">Single file upload limit</small>
                                                </div>
                                                <span class="badge bg-warning"><?php echo $system_info['upload_max_filesize']; ?></span>
                                            </div>
                                            <div class="info-item">
                                                <div>
                                                    <strong>Max POST Size</strong>
                                                    <br><small class="text-muted">Total POST data limit</small>
                                                </div>
                                                <span class="badge bg-warning"><?php echo $system_info['post_max_size']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="info-card">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-database text-primary me-2"></i>Database Statistics
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="info-item">
                                                        <div>
                                                            <strong>Total Users</strong>
                                                            <br><small class="text-muted">Registered accounts</small>
                                                        </div>
                                                        <span class="badge bg-primary"><?php echo number_format($system_info['total_users']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="info-item">
                                                        <div>
                                                            <strong>Total Courses</strong>
                                                            <br><small class="text-muted">Available courses</small>
                                                        </div>
                                                        <span class="badge bg-success"><?php echo number_format($system_info['total_courses']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="info-item">
                                                        <div>
                                                            <strong>Database Size</strong>
                                                            <br><small class="text-muted">Storage usage</small>
                                                        </div>
                                                        <span class="badge bg-info"><?php echo $system_info['database_size']; ?> MB</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup & Restore -->
                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="backup-card">
                            <h5 class="fw-bold mb-2">
                                <i class="fas fa-shield-alt me-2"></i>Database Backup & Restore
                            </h5>
                            <p class="mb-0 opacity-90">Regular backups ensure your data is safe and recoverable</p>
                        </div>
                        
                        <div class="settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-database me-2"></i>Backup Management
                                </h5>
                            </div>
                            <div class="settings-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-download text-success me-2"></i>Create Backup
                                            </h6>
                                            <p class="text-muted mb-3">
                                                Create a complete backup of your database including all user data, courses, and system settings.
                                            </p>
                                            <form method="POST">
                                                <button type="submit" name="create_backup" class="btn btn-success">
                                                    <i class="fas fa-download me-2"></i>Create Backup Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fas fa-upload text-warning me-2"></i>Restore Backup
                                            </h6>
                                            <p class="text-muted mb-3">
                                                Upload and restore a previous backup. This will overwrite current data.
                                            </p>
                                            <div class="mb-3">
                                                <input type="file" class="form-control" accept=".sql">
                                            </div>
                                            <button type="button" class="btn btn-warning" onclick="restoreBackup()">
                                                <i class="fas fa-upload me-2"></i>Restore Backup
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Important:</strong> Always test backups in a staging environment before restoring to production. 
                                            Backup restoration will permanently overwrite existing data.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function restoreBackup() {
            if (confirm('Are you sure you want to restore this backup? This will overwrite all current data and cannot be undone.')) {
                alert('Backup restoration functionality would be implemented here with proper file upload and database restoration.');
            }
        }

        // Add confirmation for maintenance mode toggle
        document.querySelector('input[name="maintenance_mode"]').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('Enable maintenance mode? This will prevent all non-admin users from accessing the system.')) {
                    this.checked = false;
                }
            }
        });
    </script>
</body>
</html>

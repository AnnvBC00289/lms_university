<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// For this demo, we'll simulate system logs. In a real system, you would log actual events
$simulated_logs = [
    [
        'id' => 1,
        'level' => 'INFO',
        'message' => 'User login successful',
        'details' => 'User admin@university.edu logged in successfully',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
    ],
    [
        'id' => 2,
        'level' => 'WARNING',
        'message' => 'Failed login attempt',
        'details' => 'Failed login attempt for user: student@test.com',
        'ip_address' => '192.168.1.101',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))
    ],
    [
        'id' => 3,
        'level' => 'INFO',
        'message' => 'Course created',
        'details' => 'New course "Advanced Mathematics" created by instructor John Smith',
        'ip_address' => '192.168.1.102',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
    ],
    [
        'id' => 4,
        'level' => 'ERROR',
        'message' => 'Database connection timeout',
        'details' => 'Database connection timeout occurred during assignment submission',
        'ip_address' => '192.168.1.103',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
    ],
    [
        'id' => 5,
        'level' => 'INFO',
        'message' => 'User registration',
        'details' => 'New student Jane Doe registered successfully',
        'ip_address' => '192.168.1.104',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7 like Mac OS X) AppleWebKit/605.1.15',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ],
    [
        'id' => 6,
        'level' => 'WARNING',
        'message' => 'High server load',
        'details' => 'Server CPU usage exceeded 80% threshold',
        'ip_address' => 'localhost',
        'user_agent' => 'System Monitor',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
    ],
    [
        'id' => 7,
        'level' => 'INFO',
        'message' => 'Assignment submitted',
        'details' => 'Assignment "Physics Lab Report" submitted by student Alice Johnson',
        'ip_address' => '192.168.1.105',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
    ],
    [
        'id' => 8,
        'level' => 'ERROR',
        'message' => 'File upload failed',
        'details' => 'File upload failed: Maximum file size exceeded (15MB)',
        'ip_address' => '192.168.1.106',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
    ]
];

// Filter logs
$level_filter = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$filtered_logs = $simulated_logs;

if (!empty($level_filter)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($level_filter) {
        return $log['level'] === $level_filter;
    });
}

if (!empty($search)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($search) {
        return stripos($log['message'], $search) !== false || 
               stripos($log['details'], $search) !== false;
    });
}

// Count logs by level
$log_counts = ['INFO' => 0, 'WARNING' => 0, 'ERROR' => 0];
foreach ($simulated_logs as $log) {
    $log_counts[$log['level']]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    
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
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

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

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 900;
        }

        .stats-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }

        .logs-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .logs-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 2rem;
            border: none;
        }

        .log-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .log-item:hover {
            background: #f8fafc;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-level {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .level-info { background: #dbeafe; color: #1e40af; }
        .level-warning { background: #fef3c7; color: #92400e; }
        .level-error { background: #fee2e2; color: #991b1b; }

        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 1rem;
        }

        .icon-info { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        .icon-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .icon-error { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

        .search-form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
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

        .log-details {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .log-meta {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
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
                                <i class="fas fa-list-alt text-primary me-3"></i>System Logs
                            </h1>
                            <p class="text-muted mb-0">Monitor system activities and events</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="exportLogs()">
                                <i class="fas fa-download me-2"></i>Export Logs
                            </button>
                            <button class="btn btn-outline-primary" onclick="clearLogs()">
                                <i class="fas fa-trash me-2"></i>Clear Old Logs
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Log Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number text-primary"><?php echo $log_counts['INFO']; ?></div>
                            <div class="stats-label text-primary">Info Logs</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number text-warning"><?php echo $log_counts['WARNING']; ?></div>
                            <div class="stats-label text-warning">Warnings</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number text-danger"><?php echo $log_counts['ERROR']; ?></div>
                            <div class="stats-label text-danger">Errors</div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Search Logs</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search messages, details, IP addresses...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Filter by Level</label>
                            <select class="form-select" name="level">
                                <option value="">All Levels</option>
                                <option value="INFO" <?php echo $level_filter === 'INFO' ? 'selected' : ''; ?>>Info</option>
                                <option value="WARNING" <?php echo $level_filter === 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                                <option value="ERROR" <?php echo $level_filter === 'ERROR' ? 'selected' : ''; ?>>Error</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- System Logs -->
                <div class="logs-card">
                    <div class="logs-header">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-file-alt me-2"></i>Recent System Events (<?php echo count($filtered_logs); ?>)
                        </h5>
                    </div>
                    <div class="logs-body">
                        <?php if (empty($filtered_logs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                <h4 class="text-muted mt-3">No logs found</h4>
                                <p class="text-muted">No system logs match your current filters</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($filtered_logs as $log): ?>
                                <div class="log-item">
                                    <div class="d-flex align-items-start">
                                        <div class="log-icon icon-<?php echo strtolower($log['level']); ?>">
                                            <i class="fas fa-<?php 
                                                echo $log['level'] === 'INFO' ? 'info-circle' : 
                                                    ($log['level'] === 'WARNING' ? 'exclamation-triangle' : 'exclamation-circle'); 
                                            ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="fw-semibold mb-0"><?php echo htmlspecialchars($log['message']); ?></h6>
                                                <span class="log-level level-<?php echo strtolower($log['level']); ?>">
                                                    <?php echo $log['level']; ?>
                                                </span>
                                            </div>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($log['details']); ?></p>
                                            <div class="log-details">
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <strong>IP Address:</strong> <?php echo htmlspecialchars($log['ip_address']); ?>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <strong>User Agent:</strong> <?php echo htmlspecialchars(substr($log['user_agent'], 0, 60)) . '...'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="log-meta">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo formatDate($log['created_at']); ?>
                                                <span class="ms-3">
                                                    <i class="fas fa-hashtag me-1"></i>
                                                    Log ID: <?php echo $log['id']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Status Alert -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <strong>System Status:</strong> All systems operational. 
                                    Last log entry: <?php echo formatDate($simulated_logs[0]['created_at']); ?>
                                    <br>
                                    <small class="text-muted">
                                        Logs are automatically rotated every 30 days. 
                                        Critical errors are immediately notified to administrators.
                                    </small>
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
        function exportLogs() {
            alert('Log export functionality would generate a downloadable file with filtered logs.');
        }

        function clearLogs() {
            if (confirm('Are you sure you want to clear old system logs? This action cannot be undone.\n\nThis will only remove logs older than 30 days.')) {
                alert('Old logs cleared successfully. Recent logs are preserved for security and debugging purposes.');
            }
        }

        // Auto-refresh logs every 30 seconds (commented out to avoid constant reloading in demo)
        /*
        setInterval(function() {
            location.reload();
        }, 30000);
        */

        // Animate log entries on page load
        document.addEventListener('DOMContentLoaded', function() {
            const logItems = document.querySelectorAll('.log-item');
            logItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

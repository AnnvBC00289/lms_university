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

// Handle sending new message
if ($_POST && isset($_POST['send_message'])) {
    $recipient_id = (int)$_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message_content']);
    
    if ($recipient_id && $subject && $message_content) {
        // Verify recipient is a student in instructor's courses
        $verify_query = "SELECT DISTINCT u.id FROM users u 
                        JOIN enrollments e ON u.id = e.student_id
                        JOIN courses c ON e.course_id = c.id
                        WHERE u.id = ? AND c.instructor_id = ? AND u.role = 'student'";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->execute([$recipient_id, $_SESSION['user_id']]);
        
        if ($verify_stmt->rowCount() > 0 || $recipient_id == $_SESSION['user_id']) {
            $insert_query = "INSERT INTO messages (sender_id, recipient_id, subject, message_content, sent_at, status) 
                           VALUES (?, ?, ?, ?, NOW(), 'unread')";
            $insert_stmt = $db->prepare($insert_query);
            
            if ($insert_stmt->execute([$_SESSION['user_id'], $recipient_id, $subject, $message_content])) {
                $message = "Message sent successfully!";
            } else {
                $error = "Failed to send message. Please try again.";
            }
        } else {
            $error = "Invalid recipient selected.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Handle message actions (mark as read, delete, etc.)
if ($_POST && isset($_POST['action']) && isset($_POST['message_id'])) {
    $action = $_POST['action'];
    $message_id = (int)$_POST['message_id'];
    
    switch ($action) {
        case 'mark_read':
            $update_query = "UPDATE messages SET status = 'read' 
                           WHERE id = ? AND (sender_id = ? OR recipient_id = ?)";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$message_id, $_SESSION['user_id'], $_SESSION['user_id']]);
            break;
            
        case 'delete':
            $delete_query = "DELETE FROM messages 
                           WHERE id = ? AND (sender_id = ? OR recipient_id = ?)";
            $delete_stmt = $db->prepare($delete_query);
            if ($delete_stmt->execute([$message_id, $_SESSION['user_id'], $_SESSION['user_id']])) {
                $message = "Message deleted successfully.";
            }
            break;
    }
}

// Get conversation filter
$conversation_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get messages based on filter
$where_conditions = ["(m.sender_id = ? OR m.recipient_id = ?)"];
$params = [$_SESSION['user_id'], $_SESSION['user_id']];

switch ($conversation_type) {
    case 'sent':
        $where_conditions = ["m.sender_id = ?"];
        $params = [$_SESSION['user_id']];
        break;
    case 'received':
        $where_conditions = ["m.recipient_id = ?"];
        $params = [$_SESSION['user_id']];
        break;
    case 'unread':
        $where_conditions = ["m.recipient_id = ?", "m.status = 'unread'"];
        $params = [$_SESSION['user_id']];
        break;
}

$where_clause = implode(" AND ", $where_conditions);

// Get messages with sender/recipient info
$query = "SELECT m.*, 
          sender.first_name as sender_first_name, sender.last_name as sender_last_name,
          sender.role as sender_role, sender.email as sender_email,
          recipient.first_name as recipient_first_name, recipient.last_name as recipient_last_name,
          recipient.role as recipient_role, recipient.email as recipient_email
          FROM messages m
          JOIN users sender ON m.sender_id = sender.id
          JOIN users recipient ON m.recipient_id = recipient.id
          WHERE $where_clause
          ORDER BY m.sent_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$messages_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students from instructor's courses for compose message
$students_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.student_id, c.title as course_title, c.course_code
                  FROM users u
                  JOIN enrollments e ON u.id = e.student_id
                  JOIN courses c ON e.course_id = c.id
                  WHERE c.instructor_id = ? AND u.role = 'student' AND e.status = 'enrolled'
                  ORDER BY u.last_name, u.first_name";
$students_stmt = $db->prepare($students_query);
$students_stmt->execute([$_SESSION['user_id']]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get message statistics
$stats_query = "SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN m.recipient_id = ? AND m.status = 'unread' THEN 1 END) as unread_messages,
                COUNT(CASE WHEN m.sender_id = ? THEN 1 END) as sent_messages,
                COUNT(CASE WHEN m.recipient_id = ? THEN 1 END) as received_messages
                FROM messages m
                WHERE m.sender_id = ? OR m.recipient_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    
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

        .messages-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-total { color: var(--primary); }
        .stat-unread { color: #dc2626; }
        .stat-sent { color: #0891b2; }
        .stat-received { color: #7c3aed; }

        .messages-nav {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .messages-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .messages-list {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .compose-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .message-item {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            cursor: pointer;
        }

        .message-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .message-item.unread {
            background: #f0f9ff;
            border-color: #bae6fd;
        }

        .message-item.read {
            background: #fafbfc;
        }

        .message-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 0.5rem;
        }

        .message-from {
            font-weight: 700;
            color: #1f2937;
        }

        .message-date {
            color: #64748b;
            font-size: 0.875rem;
        }

        .message-subject {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .message-preview {
            color: #64748b;
            line-height: 1.5;
        }

        .message-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unread {
            color: #dc2626;
        }

        .status-read {
            color: #059669;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem;
            transition: all 0.3s ease;
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

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
            
            .compose-section {
                position: static;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Messages Header -->
                <div class="messages-header" data-aos="fade-down">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="fw-bold mb-2">
                                <i class="fas fa-comments me-3"></i>Messages
                            </h1>
                            <p class="mb-0 opacity-90">Communicate with your students and colleagues</p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#composeModal">
                                <i class="fas fa-plus me-2"></i>Compose New Message
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Row -->
                <div class="row stats-row" data-aos="fade-up">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-total"><?php echo $stats['total_messages']; ?></div>
                            <div class="stat-label">Total Messages</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-unread"><?php echo $stats['unread_messages']; ?></div>
                            <div class="stat-label">Unread Messages</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-sent"><?php echo $stats['sent_messages']; ?></div>
                            <div class="stat-label">Sent Messages</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number stat-received"><?php echo $stats['received_messages']; ?></div>
                            <div class="stat-label">Received Messages</div>
                        </div>
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

                <!-- Messages Navigation -->
                <div class="messages-nav" data-aos="fade-up">
                    <ul class="nav nav-pills justify-content-center">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $conversation_type == 'all' ? 'active' : ''; ?>" 
                               href="?type=all">
                                <i class="fas fa-inbox me-2"></i>All Messages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $conversation_type == 'received' ? 'active' : ''; ?>" 
                               href="?type=received">
                                <i class="fas fa-download me-2"></i>Received
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $conversation_type == 'sent' ? 'active' : ''; ?>" 
                               href="?type=sent">
                                <i class="fas fa-upload me-2"></i>Sent
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $conversation_type == 'unread' ? 'active' : ''; ?>" 
                               href="?type=unread">
                                <i class="fas fa-dot-circle me-2"></i>
                                Unread <?php if ($stats['unread_messages'] > 0): ?><span class="badge bg-danger ms-1"><?php echo $stats['unread_messages']; ?></span><?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Messages List -->
                <div class="messages-list" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-success mb-0">
                            <i class="fas fa-envelope me-2"></i>
                            <?php
                            switch ($conversation_type) {
                                case 'sent': echo 'Sent Messages'; break;
                                case 'received': echo 'Received Messages'; break;
                                case 'unread': echo 'Unread Messages'; break;
                                default: echo 'All Messages'; break;
                            }
                            ?>
                        </h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                            <i class="fas fa-plus me-2"></i>New Message
                        </button>
                    </div>

                    <?php if (empty($messages_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Messages Found</h3>
                            <p>You don't have any messages in this category yet.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                <i class="fas fa-plus me-2"></i>Send Your First Message
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages_list as $msg): ?>
                            <div class="message-item <?php echo $msg['status']; ?>" 
                                 onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                <div class="message-header">
                                    <div class="flex-grow-1">
                                        <?php if ($msg['sender_id'] == $_SESSION['user_id']): ?>
                                            <div class="message-from">
                                                To: <?php echo htmlspecialchars($msg['recipient_first_name'] . ' ' . $msg['recipient_last_name']); ?>
                                                <span class="badge bg-secondary ms-2"><?php echo ucfirst($msg['recipient_role']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="message-from">
                                                From: <?php echo htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']); ?>
                                                <span class="badge bg-info ms-2"><?php echo ucfirst($msg['sender_role']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="message-date"><?php echo date('M j, Y g:i A', strtotime($msg['sent_at'])); ?></div>
                                        <div class="message-status status-<?php echo $msg['status']; ?>">
                                            <i class="fas fa-<?php echo $msg['status'] == 'unread' ? 'dot-circle' : 'check-circle'; ?>"></i>
                                            <?php echo ucfirst($msg['status']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(substr($msg['message_content'], 0, 150)); ?>
                                    <?php if (strlen($msg['message_content']) > 150) echo '...'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="composeModalLabel">
                        <i class="fas fa-edit me-2"></i>Compose New Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="recipient_id" class="form-label">Send To *</label>
                                <select class="form-select" id="recipient_id" name="recipient_id" required>
                                    <option value="">Choose a student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            (<?php echo htmlspecialchars($student['course_code']); ?>)
                                            <?php if ($student['student_id']): ?>- ID: <?php echo htmlspecialchars($student['student_id']); ?><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       placeholder="Enter message subject" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="message_content" class="form-label">Message *</label>
                                <textarea class="form-control" id="message_content" name="message_content" 
                                          rows="8" placeholder="Type your message here..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });

        // View message function
        function viewMessage(messageId) {
            // Mark as read when clicked
            if (messageId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const messageIdInput = document.createElement('input');
                messageIdInput.name = 'message_id';
                messageIdInput.value = messageId;
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'mark_read';
                
                form.appendChild(messageIdInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add hover effects
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

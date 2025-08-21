<?php
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get user's messages
$query = "SELECT m.*, 
          sender.first_name as sender_first_name, sender.last_name as sender_last_name,
          sender.role as sender_role
          FROM messages m
          JOIN users sender ON m.sender_id = sender.id
          WHERE m.recipient_id = ? AND m.is_deleted_by_recipient = FALSE
          ORDER BY m.sent_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark message as read if viewing specific message
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $message_id = (int)$_GET['read'];
    $query = "UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ? AND read_at IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute([$message_id, $_SESSION['user_id']]);
}

// Get unread count
$query = "SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND read_at IS NULL AND is_deleted_by_recipient = FALSE";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <?php 
    if (hasRole('student')) {
        include '../includes/student_navbar.php';
    } elseif (hasRole('instructor')) {
        include '../includes/instructor_navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if (hasRole('student')) {
                include '../includes/student_sidebar.php';
            } elseif (hasRole('instructor')) {
                include '../includes/instructor_sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-envelope"></i> Messages
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="compose.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Compose
                            </a>
                            <a href="sent.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-paper-plane"></i> Sent
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-inbox"></i> Inbox (<?php echo count($messages); ?> messages)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">Your inbox is empty.</p>
                                        <a href="compose.php" class="btn btn-primary">Send your first message</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($messages as $message): ?>
                                            <div class="list-group-item <?php echo is_null($message['read_at']) ? 'bg-light' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <?php if (is_null($message['read_at'])): ?>
                                                                <i class="fas fa-circle text-primary me-2" style="font-size: 0.5rem;"></i>
                                                            <?php endif; ?>
                                                            <h6 class="mb-0">
                                                                <a href="view.php?id=<?php echo $message['id']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?>
                                                                </a>
                                                            </h6>
                                                        </div>
                                                        <p class="mb-1 text-muted small">
                                                            From: <?php echo htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name']); ?>
                                                            <span class="badge bg-<?php echo $message['sender_role'] == 'instructor' ? 'success' : 'primary'; ?> ms-2">
                                                                <?php echo ucfirst($message['sender_role']); ?>
                                                            </span>
                                                        </p>
                                                        <p class="mb-1 text-truncate" style="max-width: 500px;">
                                                            <?php echo htmlspecialchars(substr($message['content'], 0, 100)) . (strlen($message['content']) > 100 ? '...' : ''); ?>
                                                        </p>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo formatDate($message['sent_at']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="reply.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-reply"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
                    <h5 class="modal-title">Delete Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this message? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let messageToDelete = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        function deleteMessage(messageId) {
            messageToDelete = messageId;
            deleteModal.show();
        }

        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (messageToDelete) {
                // Send delete request
                fetch('delete_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ message_id: messageToDelete })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting message');
                });
                
                deleteModal.hide();
                messageToDelete = null;
            }
        });
    </script>
</body>
</html>
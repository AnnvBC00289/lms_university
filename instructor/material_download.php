<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$material_id) {
    header('Location: materials.php');
    exit();
}

// Get material information and verify ownership
$query = "SELECT cm.*, c.title as course_title 
          FROM course_materials cm
          JOIN courses c ON cm.course_id = c.id
          WHERE cm.id = ? AND c.instructor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$material_id, $_SESSION['user_id']]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    header('Location: materials.php?error=' . urlencode('Material not found or you don\'t have permission to access it.'));
    exit();
}

$file_path = '../' . $material['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    header('Location: materials.php?error=' . urlencode('File not found on server.'));
    exit();
}

// Get file info
$file_size = filesize($file_path);
$file_name = basename($file_path);

// Extract original filename from the stored filename (remove timestamp)
$original_name = $material['title'] . '.' . $material['file_type'];

// Set appropriate headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $original_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($file_path);
exit();
?>

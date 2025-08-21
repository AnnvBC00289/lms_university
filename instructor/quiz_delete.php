<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quizzes.php');
    exit();
}

$quiz_id = (int)($_POST['quiz_id'] ?? 0);
if (!$quiz_id) {
    header('Location: quizzes.php?error=invalid_quiz');
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if instructor owns this quiz
    $query = "SELECT q.*, c.instructor_id 
              FROM quizzes q
              JOIN courses c ON q.course_id = c.id
              WHERE q.id = ? AND c.instructor_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: quizzes.php?error=unauthorized');
        exit();
    }

    // Check if quiz has attempts
    $query = "SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE quiz_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$quiz_id]);
    $attempts = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempts['attempt_count'] > 0) {
        header('Location: quizzes.php?error=quiz_has_attempts');
        exit();
    }

    // Delete quiz (cascade will handle related records)
    $query = "DELETE FROM quizzes WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$quiz_id]);

    header('Location: quizzes.php?success=quiz_deleted');
    exit();

} catch (Exception $e) {
    header('Location: quizzes.php?error=delete_failed');
    exit();
}




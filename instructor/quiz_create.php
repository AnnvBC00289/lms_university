<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get instructor's courses
$query = "SELECT id, title, course_code FROM courses WHERE instructor_id = ? AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $time_limit = (int)$_POST['time_limit'];
    $max_attempts = (int)$_POST['max_attempts'];
    $due_date = $_POST['due_date'];
    $questions = $_POST['questions'] ?? [];

    if (empty($title) || empty($course_id) || empty($questions)) {
        $error = 'Please fill in all required fields and add at least one question.';
    } else {
        try {
            $db->beginTransaction();

            // Insert quiz
            $query = "INSERT INTO quizzes (course_id, title, description, time_limit, max_attempts, due_date, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$course_id, $title, $description, $time_limit, $max_attempts, $due_date, $_SESSION['user_id']]);
            $quiz_id = $db->lastInsertId();

            // Insert questions
            foreach ($questions as $index => $question) {
                if (empty($question['text']) || empty($question['type'])) continue;

                $query = "INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, correct_answer, question_order) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $quiz_id,
                    $question['text'],
                    $question['type'],
                    $question['points'] ?? 1.00,
                    $question['correct_answer'] ?? '',
                    $index + 1
                ]);
                $question_id = $db->lastInsertId();

                // Insert options for multiple choice questions
                if ($question['type'] === 'multiple_choice' && !empty($question['options'])) {
                    foreach ($question['options'] as $optIndex => $option) {
                        if (empty($option['text'])) continue;
                        
                        $query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) 
                                  VALUES (?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            $question_id,
                            $option['text'],
                            $option['is_correct'] ?? false,
                            $optIndex + 1
                        ]);
                    }
                }
            }

            $db->commit();
            $success = 'Quiz created successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error creating quiz: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Instructor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/backgrounds.css" rel="stylesheet">
    <style>
        .question-container {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .option-container {
            margin: 10px 0;
        }
        .remove-btn {
            color: #dc3545;
            cursor: pointer;
        }
        .remove-btn:hover {
            color: #c82333;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">
                        <i class="fas fa-plus-circle text-primary me-2"></i>
                        Create New Quiz
                    </h1>
                    <a href="quizzes.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="quizForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Quiz Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="course_id" class="form-label">Course *</label>
                                            <select class="form-select" id="course_id" name="course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course['id']; ?>">
                                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="title" class="form-label">Quiz Title *</label>
                                            <input type="text" class="form-control" id="title" name="title" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                            <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" value="30">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="max_attempts" class="form-label">Max Attempts</label>
                                            <input type="number" class="form-control" id="max_attempts" name="max_attempts" min="1" value="1">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="datetime-local" class="form-control" id="due_date" name="due_date">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-question-circle me-2"></i>Questions
                                    </h5>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="addQuestion()">
                                        <i class="fas fa-plus me-2"></i>Add Question
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="questionsContainer">
                                        <!-- Questions will be added here dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-cog me-2"></i>Quiz Settings
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Quiz Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="quiz_type" id="graded" value="graded" checked>
                                            <label class="form-check-label" for="graded">
                                                Graded Quiz
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="quiz_type" id="practice" value="practice">
                                            <label class="form-check-label" for="practice">
                                                Practice Quiz
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="shuffle_questions" name="shuffle_questions">
                                            <label class="form-check-label" for="shuffle_questions">
                                                Shuffle Questions
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_results" name="show_results" checked>
                                            <label class="form-check-label" for="show_results">
                                                Show Results After Submission
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Create Quiz
                                    </button>
                                    <a href="quizzes.php" class="btn btn-outline-secondary w-100 mt-2">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let questionCounter = 0;

        function addQuestion() {
            questionCounter++;
            const container = document.getElementById('questionsContainer');
            
            const questionHtml = `
                <div class="question-container" id="question_${questionCounter}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Question ${questionCounter}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(${questionCounter})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea class="form-control" name="questions[${questionCounter}][text]" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Question Type *</label>
                            <select class="form-select" name="questions[${questionCounter}][type]" onchange="toggleOptions(${questionCounter}, this.value)" required>
                                <option value="">Select Type</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Points</label>
                            <input type="number" class="form-control" name="questions[${questionCounter}][points]" value="1.00" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div id="options_${questionCounter}" style="display: none;">
                        <label class="form-label">Options</label>
                        <div id="optionsContainer_${questionCounter}">
                            <div class="option-container">
                                <div class="input-group">
                                    <div class="input-group-text">
                                        <input type="radio" name="questions[${questionCounter}][correct_option]" value="0" required>
                                    </div>
                                    <input type="text" class="form-control" name="questions[${questionCounter}][options][0][text]" placeholder="Option 1" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addOption(${questionCounter})">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="correct_answer_${questionCounter}" style="display: none;">
                        <label class="form-label">Correct Answer</label>
                        <input type="text" class="form-control" name="questions[${questionCounter}][correct_answer]">
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHtml);
        }

        function removeQuestion(questionId) {
            const question = document.getElementById(`question_${questionId}`);
            question.remove();
        }

        function toggleOptions(questionId, type) {
            const optionsDiv = document.getElementById(`options_${questionId}`);
            const correctAnswerDiv = document.getElementById(`correct_answer_${questionId}`);
            
            if (type === 'multiple_choice') {
                optionsDiv.style.display = 'block';
                correctAnswerDiv.style.display = 'none';
            } else {
                optionsDiv.style.display = 'none';
                correctAnswerDiv.style.display = 'block';
            }
        }

        function addOption(questionId) {
            const container = document.getElementById(`optionsContainer_${questionId}`);
            const optionCount = container.children.length;
            
            const optionHtml = `
                <div class="option-container">
                    <div class="input-group">
                        <div class="input-group-text">
                            <input type="radio" name="questions[${questionId}][correct_option]" value="${optionCount}" required>
                        </div>
                        <input type="text" class="form-control" name="questions[${questionId}][options][${optionCount}][text]" placeholder="Option ${optionCount + 1}" required>
                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', optionHtml);
        }

        function removeOption(button) {
            button.closest('.option-container').remove();
        }

        // Add first question on page load
        document.addEventListener('DOMContentLoaded', function() {
            addQuestion();
        });
    </script>
</body>
</html>


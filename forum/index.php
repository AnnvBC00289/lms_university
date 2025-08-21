<?php
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get user's enrolled courses for forum access
$query = "SELECT DISTINCT c.id, c.title, c.course_code 
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          WHERE e.student_id = ? AND e.status = 'enrolled'
          UNION
          SELECT c.id, c.title, c.course_code 
          FROM courses c 
          WHERE c.instructor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$accessible_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected course
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
if (!$selected_course_id && !empty($accessible_courses)) {
    $selected_course_id = $accessible_courses[0]['id'];
}

// Get forum categories for selected course
$categories = [];
if ($selected_course_id) {
    $query = "SELECT fc.*, COUNT(ft.id) as topic_count
              FROM forum_categories fc
              LEFT JOIN forum_topics ft ON fc.id = ft.category_id
              WHERE fc.course_id = ?
              GROUP BY fc.id
              ORDER BY fc.name";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_course_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent topics
$recent_topics = [];
if ($selected_course_id) {
    $query = "SELECT ft.*, fc.name as category_name, u.first_name, u.last_name,
              COUNT(fp.id) as post_count, MAX(fp.created_at) as last_post_date
              FROM forum_topics ft
              JOIN forum_categories fc ON ft.category_id = fc.id
              JOIN users u ON ft.created_by = u.id
              LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
              WHERE fc.course_id = ?
              GROUP BY ft.id
              ORDER BY ft.is_pinned DESC, last_post_date DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_course_id]);
    $recent_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Forum - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
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
                        <i class="fas fa-comments"></i> Course Forum
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="topic_create.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> New Topic
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Course Selection -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Select Course</h6>
                                <div class="btn-group" role="group">
                                    <?php foreach ($accessible_courses as $course): ?>
                                        <a href="?course_id=<?php echo $course['id']; ?>" 
                                           class="btn <?php echo $course['id'] == $selected_course_id ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                            <?php echo htmlspecialchars($course['course_code']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($selected_course_id): ?>
                <!-- Forum Categories -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-folder"></i> Forum Categories
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($categories)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">No forum categories available for this course.</p>
                                        <?php if (hasRole('instructor')): ?>
                                            <a href="category_create.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-primary">
                                                Create First Category
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <a href="category_view.php?id=<?php echo $category['id']; ?>" class="text-decoration-none">
                                                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                                                        </a>
                                                    </h6>
                                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($category['description']); ?></p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-primary rounded-pill"><?php echo $category['topic_count']; ?> topics</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Topics -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Recent Topics
                                </h6>
                                <a href="topic_create.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> New Topic
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_topics)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comments fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">No topics yet. Start a discussion!</p>
                                        <a href="topic_create.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-primary">
                                            Create First Topic
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Topic</th>
                                                    <th>Category</th>
                                                    <th>Author</th>
                                                    <th>Posts</th>
                                                    <th>Last Activity</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_topics as $topic): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if ($topic['is_pinned']): ?>
                                                                    <i class="fas fa-thumbtack text-warning me-2"></i>
                                                                <?php endif; ?>
                                                                <?php if ($topic['is_locked']): ?>
                                                                    <i class="fas fa-lock text-danger me-2"></i>
                                                                <?php endif; ?>
                                                                <a href="topic_view.php?id=<?php echo $topic['id']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($topic['category_name']); ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($topic['first_name'] . ' ' . $topic['last_name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $topic['post_count']; ?></span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo $topic['last_post_date'] ? formatDate($topic['last_post_date']) : formatDate($topic['created_at']); ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please select a course to view its forum.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
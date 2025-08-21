<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Background Test - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/backgrounds.css" rel="stylesheet">
    <style>
        .test-section {
            padding: 2rem;
            margin: 1rem 0;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="index-page">
    <div class="container mt-5">
        <div class="test-section">
            <h1 class="text-center mb-4">
                <i class="fas fa-image"></i> Background Images Test
            </h1>
            
            <div class="row">
                <div class="col-md-6">
                    <h3>School Supplies Background</h3>
                    <p>This page uses the school supplies background image (imgi_150_realistic-school-supplies_23-2150588345.jpg)</p>
                    <a href="test_backgrounds.php?bg=alt" class="btn btn-primary">Switch to Education Pattern</a>
                </div>
                <div class="col-md-6">
                    <h3>Current Background</h3>
                    <p>Class: <code>index-page</code></p>
                    <p>Image: School Supplies</p>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row">
                <div class="col-md-4">
                    <h4>Available Backgrounds:</h4>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <strong>index-page</strong> - School Supplies
                        </li>
                        <li class="list-group-item">
                            <strong>alt-background</strong> - Education Pattern
                        </li>
                        <li class="list-group-item">
                            <strong>login-page</strong> - Education Pattern with overlay
                        </li>
                        <li class="list-group-item">
                            <strong>dashboard-page</strong> - School Supplies with overlay
                        </li>
                        <li class="list-group-item">
                            <strong>forum-page</strong> - Education Pattern with overlay
                        </li>
                    </ul>
                </div>
                <div class="col-md-8">
                    <h4>Test Links:</h4>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="test_backgrounds.php" class="btn btn-outline-primary">Index Page Style</a>
                        <a href="test_backgrounds.php?bg=alt" class="btn btn-outline-secondary">Alt Background</a>
                        <a href="test_backgrounds.php?bg=login" class="btn btn-outline-info">Login Style</a>
                        <a href="test_backgrounds.php?bg=dashboard" class="btn btn-outline-success">Dashboard Style</a>
                        <a href="test_backgrounds.php?bg=forum" class="btn btn-outline-warning">Forum Style</a>
                    </div>
                    
                    <hr class="my-3">
                    
                    <h4>Image Information:</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h5>School Supplies Image</h5>
                            <p><strong>File:</strong> imgi_150_realistic-school-supplies_23-2150588345.jpg</p>
                            <p><strong>Size:</strong> 141KB</p>
                            <p><strong>Description:</strong> Realistic school supplies background</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Education Pattern Image</h5>
                            <p><strong>File:</strong> imgi_151_education-pattern-background-doodle-style_660381-2539.jpg</p>
                            <p><strong>Size:</strong> 360KB</p>
                            <p><strong>Description:</strong> Education pattern background with doodle style</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle background switching
        const urlParams = new URLSearchParams(window.location.search);
        const bg = urlParams.get('bg');
        
        if (bg) {
            document.body.className = '';
            switch(bg) {
                case 'alt':
                    document.body.classList.add('alt-background');
                    break;
                case 'login':
                    document.body.classList.add('login-page');
                    break;
                case 'dashboard':
                    document.body.classList.add('dashboard-page');
                    break;
                case 'forum':
                    document.body.classList.add('forum-page');
                    break;
                default:
                    document.body.classList.add('index-page');
            }
        }
    </script>
</body>
</html>



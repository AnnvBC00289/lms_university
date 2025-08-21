<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University LMS - Modern Learning Management System</title>
    <meta name="description" content="Advanced learning management system for university education with comprehensive features for online learning, assignment management, and academic progress tracking.">
    
    <!-- Modern CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="assets/css/backgrounds.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.7;
            color: var(--gray-700);
        }

        .main-container {
            background: white;
            min-height: 100vh;
        }

        /* Navigation */
        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-200);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar-brand-modern {
            font-weight: 800;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link-modern {
            font-weight: 500;
            color: var(--gray-700) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link-modern:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
        }

        .btn-gradient {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .btn-gradient:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4);
            color: white;
        }

        /* Hero Section */
        .hero-modern {
            padding: 120px 0 80px;
            background: linear-gradient(135deg, 
                rgba(99, 102, 241, 0.1) 0%, 
                rgba(236, 72, 153, 0.1) 50%, 
                rgba(16, 185, 129, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-badge {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-block;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--gray-900), var(--gray-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.875rem;
        }

        /* Section Styling */
        .section-modern {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--gray-900), var(--gray-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Modern Cards */
        .card-modern {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
            border: 1px solid var(--gray-200);
        }

        .card-modern:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .card-image {
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .card-meta {
            font-size: 0.875rem;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .card-tag {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .card-tag.pro {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .card-tag.free {
            background: var(--success);
            color: white;
        }

        /* Features Section */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        /* Footer */
        .footer-modern {
            background: var(--gray-900);
            color: white;
            padding: 4rem 0 2rem;
            margin-top: 5rem;
        }

        .footer-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-link:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-800);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-stats {
                justify-content: center;
                gap: 2rem;
            }
        }
    </style>
</head>
<body class="index-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container">
            <a class="navbar-brand navbar-brand-modern" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>
                University LMS
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="#courses">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="forum/index.php">Forum</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="#about">About Us</a>
                    </li>
                </ul>
                
                <div class="d-flex gap-2">
                    <a href="auth/login.php" class="btn btn-outline-primary">Login</a>
                    <a href="auth/login.php" class="btn btn-gradient">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Hero Section -->
        <section class="hero-modern">
            <div class="container">
			<div class="row align-items-center">
                                        <div class="col-lg-6" data-aos="fade-up">
                        <div class="hero-badge">
                            <i class="fas fa-university me-2"></i>
                            Academic Year 2024-2025
					</div>
                        
                        <h1 class="hero-title">
                            University LMS<br>
                            <span class="text-primary">Learning Management System</span>
                        </h1>
                        
                        <p class="hero-subtitle">
                            Comprehensive learning management platform designed for students and instructors, 
                            supporting online education, assignment management, assessments, and academic progress tracking.
                        </p>
                        
                        <div class="d-flex flex-wrap gap-3">
                            <a href="auth/login.php" class="btn btn-gradient btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Login to System
                            </a>
                            <a href="#courses" class="btn btn-outline-dark btn-lg">
                                <i class="fas fa-book me-2"></i>
                                View Courses
                            </a>
                        </div>
                        
                        <div class="hero-stats">
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="100">
                                <span class="stat-number">15K+</span>
                                <span class="stat-label">Students</span>
                            </div>
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="200">
                                <span class="stat-number">120+</span>
                                <span class="stat-label">Courses</span>
                            </div>
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Faculty</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 text-center" data-aos="fade-left" data-aos-delay="400">
                        <div class="display-1 text-primary">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <div class="mt-4">
                            <img src="https://via.placeholder.com/400x300/6366f1/ffffff?text=Learning+Platform" 
                                 class="img-fluid rounded-4 shadow-lg" alt="Learning Platform">
					</div>
				</div>
				</div>
			</div>
		</section>

        <!-- Features Section -->
        <section id="features" class="section-modern bg-light">
            <div class="container">
                <div class="section-header" data-aos="fade-up">
                    <h2 class="section-title">LMS System Features</h2>
                    <p class="section-subtitle">
                        Integrated learning management system with comprehensive tools essential for modern university education
                    </p>
                </div>
                
                <div class="features-grid">
                                                            <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="feature-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h3 class="feature-title">Assignment Management</h3>
                        <p>Online assignment submission system with automatic grading and detailed feedback from instructors.</p>
                    </div>
                    
                    <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="feature-title">Integrated Schedule</h3>
                        <p>Manage class timetables, reminders for classes, exams, and assignment deadlines.</p>
                    </div>
                    
                    <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="feature-title">Grade Tracking</h3>
                        <p>View detailed grades for each course, cumulative GPA, and comprehensive academic progress reports.</p>
                    </div>
                    
                    <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                        <div class="feature-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="feature-title">Discussion Forums</h3>
                        <p>Academic discussion space between students and instructors for effective learning support.</p>
                    </div>
                    
                    <div class="feature-card" data-aos="fade-up" data-aos-delay="500">
                        <div class="feature-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="feature-title">Digital Resources</h3>
                        <p>Rich digital library with textbooks, lecture materials, and reference documents.</p>
				</div>
                    
                    <div class="feature-card" data-aos="fade-up" data-aos-delay="600">
                        <div class="feature-icon">
                            <i class="fas fa-video"></i>
			</div>
                        <h3 class="feature-title">Online Learning</h3>
                        <p>HD quality online classes with lecture recordings and real-time interaction capabilities.</p>
						</div>
					</div>
				</div>
        </section>

        <!-- Courses -->
        <section id="courses" class="section-modern">
            <div class="container">
                <div class="section-header" data-aos="fade-up">
                    <h2 class="section-title">Information Technology Courses</h2>
                    <p class="section-subtitle">
                        Specialized IT courses with content updated according to international training standards
                    </p>
                </div>
                
                <div class="row g-4">
                                                            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-modern">
                            <div class="card-image">
                                <i class="fas fa-code fa-3x"></i>
                            </div>
                            <div class="card-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="card-title">Web Programming</h3>
                                    <span class="card-tag pro">CS301</span>
                                </div>
                                <p class="text-muted mb-3">Learn fundamentals of HTML5, CSS3, JavaScript and modern frameworks for web application development.</p>
                                <div class="card-meta">
                                    <span><i class="fas fa-clock me-1"></i>3 Credits</span>
                                    <span><i class="fas fa-users me-1"></i>120 Students</span>
                                    <span><i class="fas fa-calendar me-1"></i>Fall</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 text-primary mb-0">Prof. Tran Van Nhuom</span>
                                    <a href="auth/login.php" class="btn btn-outline-primary btn-sm">Join Class</a>
                                </div>
						</div>
					</div>
				</div>
                    
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="card-modern">
                            <div class="card-image">
                                <i class="fas fa-database fa-3x"></i>
                            </div>
                            <div class="card-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="card-title">Database Systems</h3>
                                    <span class="card-tag pro">CS401</span>
                                </div>
                                <p class="text-muted mb-3">Design and manage databases, SQL, NoSQL and modern database management systems.</p>
                                <div class="card-meta">
                                    <span><i class="fas fa-clock me-1"></i>3 Credits</span>
                                    <span><i class="fas fa-users me-1"></i>85 Students</span>
                                    <span><i class="fas fa-calendar me-1"></i>Fall</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 text-primary mb-0">Prof. Le Duc Trong</span>
                                    <a href="auth/login.php" class="btn btn-outline-primary btn-sm">Join Class</a>
                                </div>
						</div>
					</div>
				</div>
                    
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                        <div class="card-modern">
                            <div class="card-image">
                                <i class="fas fa-mobile-alt fa-3x"></i>
                            </div>
                            <div class="card-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="card-title">Mobile Development</h3>
                                    <span class="card-tag pro">CS501</span>
                                </div>
                                <p class="text-muted mb-3">Develop cross-platform mobile applications using React Native, Flutter and modern technologies.</p>
                                <div class="card-meta">
                                    <span><i class="fas fa-clock me-1"></i>4 Credits</span>
                                    <span><i class="fas fa-users me-1"></i>95 Students</span>
                                    <span><i class="fas fa-calendar me-1"></i>Spring</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 text-primary mb-0">Prof. Le Nhat Quang</span>
                                    <a href="auth/login.php" class="btn btn-outline-primary btn-sm">Join Class</a>
                                </div>
                            </div>
						</div>
					</div>
				</div>
			</div>
		</section>

        <!-- Basic Courses -->
        <section class="section-modern bg-light">
            <div class="container">
                <div class="section-header" data-aos="fade-up">
                    <h2 class="section-title">Foundation Courses</h2>
                    <p class="section-subtitle">
                        Essential foundation courses that provide a solid base for Information Technology specialization
                    </p>
                </div>
                
                <div class="row g-4">
                    <?php
                    $basic_courses = [
                        ['title' => 'Discrete Mathematics', 'icon' => 'fas fa-calculator', 'code' => 'MT101', 'credits' => '3', 'students' => '250'],
                        ['title' => 'Introduction to IT', 'icon' => 'fas fa-laptop', 'code' => 'CS101', 'credits' => '2', 'students' => '280'],
                        ['title' => 'C++ Programming', 'icon' => 'fas fa-code', 'code' => 'CS102', 'credits' => '4', 'students' => '220'],
                        ['title' => 'Data Structures', 'icon' => 'fas fa-sitemap', 'code' => 'CS201', 'credits' => '3', 'students' => '180'],
                        ['title' => 'Algorithms', 'icon' => 'fas fa-project-diagram', 'code' => 'CS202', 'credits' => '3', 'students' => '165'],
                        ['title' => 'Operating Systems', 'icon' => 'fas fa-desktop', 'code' => 'CS203', 'credits' => '3', 'students' => '190'],
                        ['title' => 'Computer Networks', 'icon' => 'fas fa-network-wired', 'code' => 'CS204', 'credits' => '3', 'students' => '175'],
                        ['title' => 'Probability & Statistics', 'icon' => 'fas fa-chart-pie', 'code' => 'MT201', 'credits' => '3', 'students' => '200']
                    ];
                    
                                        foreach ($basic_courses as $index => $course): ?>
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                        <div class="card-modern">
                            <div class="card-image">
                                <i class="<?php echo $course['icon']; ?> fa-3x"></i>
                            </div>
                            <div class="card-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="card-title"><?php echo $course['title']; ?></h3>
                                    <span class="card-tag free"><?php echo $course['code']; ?></span>
				</div>
                                                                <p class="text-muted mb-3">Essential foundation course in the Information Technology program curriculum.</p>
                                <div class="card-meta">
                                    <span><i class="fas fa-credit-card me-1"></i><?php echo $course['credits']; ?> Credits</span>
                                    <span><i class="fas fa-users me-1"></i><?php echo $course['students']; ?> Students</span>
                                    <span><i class="fas fa-calendar me-1"></i>Open</span>
			</div>
                                <a href="auth/login.php" class="btn btn-success w-100">Enter Class</a>
						</div>
					</div>
				</div>
                    <?php endforeach; ?>
                </div>
			</div>
		</section>

                <!-- CTA Section -->
        <section class="section-modern">
            <div class="container">
                <div class="row align-items-center">
                                        <div class="col-lg-8" data-aos="fade-right">
                        <h2 class="display-5 fw-bold mb-3">Start Your Academic Journey with LMS</h2>
                        <p class="lead text-muted mb-4">
                            Join our modern LMS system with comprehensive features for learning support, grade management, and instructor interaction.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="auth/login.php" class="btn btn-gradient btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Login to System
                            </a>
                            <a href="messages/inbox.php" class="btn btn-outline-dark btn-lg">
                                <i class="fas fa-question-circle me-2"></i>
                                Technical Support
                            </a>
				</div>
			</div>
                    <div class="col-lg-4 text-center" data-aos="fade-left">
                        <div class="display-1 text-primary">
                            <i class="fas fa-university"></i>
						</div>
					</div>
				</div>
			</div>
		</section>
    </div>

		<!-- Footer -->
    <footer class="footer-modern">
        <div class="container">
			<div class="row g-4">
                                                <div class="col-lg-4">
                    <div class="footer-brand">University LMS</div>
                    <p class="text-light mb-4">
                        Modern learning management system for university education, 
                        providing comprehensive support for online teaching and learning processes.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="fas fa-envelope"></i>
                        </a>
                        <a href="#" class="social-link">
                            <i class="fas fa-phone"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <h5 class="text-white mb-3">Learning</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#courses" class="footer-link">IT Courses</a></li>
                        <li class="mb-2"><a href="#courses" class="footer-link">Foundation Courses</a></li>
                        <li class="mb-2"><a href="forum/index.php" class="footer-link">Forum</a></li>
                        <li class="mb-2"><a href="messages/inbox.php" class="footer-link">Messages</a></li>
                    </ul>
					</div>
                
                <div class="col-lg-2 col-md-6">
                    <h5 class="text-white mb-3">Students</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="student/dashboard.php" class="footer-link">Dashboard</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Grades</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Schedule</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Assignments</a></li>
                    </ul>
				</div>
                
                <div class="col-lg-2 col-md-6">
                    <h5 class="text-white mb-3">Faculty</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="instructor/dashboard.php" class="footer-link">Dashboard</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Class Management</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Grading</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Reports</a></li>
					</ul>
				</div>
                
                <div class="col-lg-2 col-md-6">
                    <h5 class="text-white mb-3">Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="footer-link">Help Guide</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">FAQ</a></li>
                        <li class="mb-2"><a href="messages/inbox.php" class="footer-link">IT Support</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Report Issues</a></li>
					</ul>
				</div>
            </div>
            
            <hr class="my-4 border-secondary">
            
                        <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">© <?php echo date('Y'); ?> University LMS. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">Developed with ❤️ in Vietnam</p>
                </div>
            </div>
			</div>
		</footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-modern');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = 'none';
            }
        });

        // Add hover effects to cards
        document.querySelectorAll('.card-modern, .feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animated counter for stats
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target + (target === 15 ? 'K+' : target === 120 ? '+' : '+');
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current) + (target === 15 ? 'K+' : target === 120 ? '+' : '+');
                }
            }, 20);
        }

        // Trigger counter animation when stats come into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach(stat => {
                        const text = stat.textContent;
                        let target;
                        if (text.includes('15K+')) target = 15;
                        else if (text.includes('120+')) target = 120;
                        else if (text.includes('500+')) target = 500;
                        
                        if (target) {
                            animateCounter(stat, target);
                        }
                    });
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.hero-stats');
        if (statsSection) {
            observer.observe(statsSection);
        }
    </script>
</body>
</html> 

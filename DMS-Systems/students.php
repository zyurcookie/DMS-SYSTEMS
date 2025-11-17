<?php
session_start();
include('include/db.php');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Dean', 'Registrar'])) {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle Add/Edit Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_student'])) {
    $user_id = $_POST['user_id'] ?? null;
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $student_number = $_POST['student_number'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $middleName = $_POST['middleName'] ?? '';
    $year_level = $_POST['year_level'];
    $course = $_POST['course'];
    $contactNumber = $_POST['contactNumber'];
    $address = $_POST['address'] ?? '';
    
    if ($user_id) {
        // Update existing student
        $sql = "UPDATE profile SET student_number=?, firstName=?, lastName=?, middleName=?, 
                year_level=?, course=?, contactNumber=?, address=? WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $student_number, $firstName, $lastName, $middleName, 
                         $year_level, $course, $contactNumber, $address, $user_id);
        
        if ($stmt->execute()) {
            $successMessage = "Student updated successfully!";
        } else {
            $errorMessage = "Error updating student.";
        }
    } else {
        // Create new student
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if email exists
        $check = $conn->prepare("SELECT user_id FROM user WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errorMessage = "Email already exists.";
        } else {
            // Insert user
            $sql = "INSERT INTO user (email, password, role, status) VALUES (?, ?, 'Student', 'Active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $password);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                
                // Insert profile
                $sql2 = "INSERT INTO profile (user_id, student_number, firstName, lastName, middleName, 
                        year_level, course, contactNumber, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("issssssss", $new_user_id, $student_number, $firstName, $lastName, 
                                  $middleName, $year_level, $course, $contactNumber, $address);
                
                if ($stmt2->execute()) {
                    $successMessage = "Student created successfully!";
                } else {
                    $errorMessage = "Error creating student profile.";
                }
            } else {
                $errorMessage = "Error creating user account.";
            }
        }
    }
}

// Handle Delete Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student'])) {
    $user_id = $_POST['user_id'];
    $sql = "DELETE FROM user WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $successMessage = "Student deleted successfully!";
    } else {
        $errorMessage = "Error deleting student.";
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year_level'] ?? '';
$course_filter = $_GET['course'] ?? '';

// Build query
$sql = "SELECT u.user_id, u.email, u.status, u.created_at, p.* 
        FROM user u
        LEFT JOIN profile p ON u.user_id = p.user_id
        WHERE u.role = 'Student'";

if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $sql .= " AND (p.firstName LIKE '%$search_safe%' OR p.lastName LIKE '%$search_safe%' OR 
              p.student_number LIKE '%$search_safe%' OR u.email LIKE '%$search_safe%')";
}
if ($year_filter) {
    $year_safe = $conn->real_escape_string($year_filter);
    $sql .= " AND p.year_level = '$year_safe'";
}
if ($course_filter) {
    $course_safe = $conn->real_escape_string($course_filter);
    $sql .= " AND p.course = '$course_safe'";
}

$sql .= " ORDER BY p.lastName, p.firstName";
$students = $conn->query($sql);

// Get unique courses
$courses = $conn->query("SELECT DISTINCT course FROM profile WHERE course IS NOT NULL AND course != '' ORDER BY course");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM user WHERE role='Student'")->fetch_assoc()['count'];
$active_students = $conn->query("SELECT COUNT(*) as count FROM user WHERE role='Student' AND status='Active'")->fetch_assoc()['count'];

$user_name = $_SESSION['user_name'] ?? $_SESSION['role'];
$user_initial = substr($user_name, 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FEU Roosevelt - Students Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #0B6623;
            --primary-dark: #054d1a;
            --primary-light: #0d7a2a;
            --secondary-green: #48bb78;
            --background: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --hover-bg: #f7fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            color: white;
        }
        
        /* Layout */
        .layout-wrapper {
            display: flex;
            min-height: calc(100vh - 73px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--card-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
        }
        
        .nav-section {
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            padding-left: 1rem;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            margin-bottom: 0.375rem;
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9375rem;
        }
        
        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--primary-green);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(11, 102, 35, 0.2);
        }
        
        .nav-item i {
            width: 20px;
            font-size: 1.125rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2.5rem;
            overflow-y: auto;
            background-color: var(--background);
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--text-secondary);
        }
        
        /* Stats Cards */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .stat-mini-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-green);
        }
        
        .stat-mini-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 0.25rem;
        }
        
        .stat-mini-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Toolbar */
        .toolbar {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .toolbar-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 102, 35, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.125rem;
        }
        
        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9375rem;
            background: var(--card-bg);
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-primary);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 102, 35, 0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9375rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 102, 35, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 102, 35, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Content Card */
        .content-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--hover-bg);
        }
        
        th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.9375rem;
        }
        
        tbody tr:hover {
            background: var(--hover-bg);
        }
        
        /* Badges */
        .badge {
            padding: 0.375rem 0.875rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--card-bg);
            padding: 0;
            border-radius: 15px;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: white;
            line-height: 1;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .close-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 102, 35, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .layout-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem 0;
            }
            
            .main-content {
                padding: 1.5rem;
            }
            
            .toolbar-row {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-graduation-cap"></i>
            FEU Roosevelt DMS
        </h1>
        <div class="user-info">
            <div class="user-avatar"><?= $user_initial ?></div>
            <span><?= htmlspecialchars($user_name) ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
    
    <!-- Layout -->
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="students.php" class="nav-item active">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
                <a href="documents.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Documents</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Academic</div>
                <a href="deans_list.php" class="nav-item">
                    <i class="fas fa-star"></i>
                    <span>Dean's List</span>
                </a>
                <a href="scholarships.php" class="nav-item">
                    <i class="fas fa-award"></i>
                    <span>Scholarships</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Students Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Students</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Alerts -->
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-mini">
                <div class="stat-mini-card">
                    <div class="stat-mini-value"><?= number_format($total_students) ?></div>
                    <div class="stat-mini-label">Total Students</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-value"><?= number_format($active_students) ?></div>
                    <div class="stat-mini-label">Active Students</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-value"><?= $students ? $students->num_rows : 0 ?></div>
                    <div class="stat-mini-label">Filtered Results</div>
                </div>
            </div>
            
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name, student number, or email..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select id="yearFilter" class="filter-select">
                        <option value="">All Year Levels</option>
                        <option value="1st Year" <?= $year_filter == '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= $year_filter == '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= $year_filter == '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= $year_filter == '4th Year' ? 'selected' : '' ?>>4th Year</option>
                    </select>
                    <select id="courseFilter" class="filter-select">
                        <option value="">All Courses</option>
                        <?php 
                        if ($courses && $courses->num_rows > 0):
                            while($course = $courses->fetch_assoc()): 
                        ?>
                        <option value="<?= htmlspecialchars($course['course']) ?>" 
                                <?= $course_filter == $course['course'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course']) ?>
                        </option>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                    </select>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>
            </div>
            
            <!-- Students Table -->
            <div class="content-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Year Level</th>
                                <th>Course</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students && $students->num_rows > 0): ?>
                                <?php while($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($student['student_number'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars(($student['lastName'] ?? '') . ', ' . ($student['firstName'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td><?= htmlspecialchars($student['year_level'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['course'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['contactNumber'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $student['status'] == 'Active' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($student['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick='editStudent(<?= json_encode($student) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteStudent(<?= $student['user_id'] ?>, '<?= htmlspecialchars($student['firstName'] ?? 'this student') ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fas fa-user-graduate"></i>
                                        <p>No students found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Student</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="studentForm">
                    <input type="hidden" name="user_id" id="user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Student Number <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="student_number" id="student_number" required placeholder="e.g., 2024-123456">
                        </div>
                        <div class="form-group">
                            <label>Email <span style="color: #ef4444;">*</span></label>
                            <input type="email" name="email" id="email" required placeholder="student@example.com">
                        </div>
                    </div>
                    
                    <div id="passwordField" class="form-group">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Minimum 6 characters">
                        <small style="color: var(--text-secondary); font-size: 0.8125rem;">Leave blank to keep current password when editing</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="firstName" id="firstName" required placeholder="Juan">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="lastName" id="lastName" required placeholder="Dela Cruz">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middleName" id="middleName" placeholder="Optional">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level <span style="color: #ef4444;">*</span></label>
                            <select name="year_level" id="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Course <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="course" id="course" required placeholder="e.g., BSIT">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number <span style="color: #ef4444;">*</span></label>
                        <input type="tel" name="contactNumber" id="contactNumber" required placeholder="09XX-XXX-XXXX">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" id="address" rows="3" placeholder="Complete address (optional)"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_student" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Student
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Add slide out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(-20px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Search and filter
        document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('yearFilter').addEventListener('change', applyFilters);
        document.getElementById('courseFilter').addEventListener('change', applyFilters);
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const year = document.getElementById('yearFilter').value;
            const course = document.getElementById('courseFilter').value;
            window.location.href = `students.php?search=${encodeURIComponent(search)}&year_level=${encodeURIComponent(year)}&course=${encodeURIComponent(course)}`;
        }
        
        // Modal functions
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add New Student';
            document.getElementById('studentForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('password').required = true;
            document.getElementById('email').readOnly = false;
            document.getElementById('studentModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('studentModal').classList.remove('active');
        }
        
        function editStudent(student) {
            document.getElementById('modalTitle').textContent = 'Edit Student';
            document.getElementById('user_id').value = student.user_id;
            document.getElementById('student_number').value = student.student_number || '';
            document.getElementById('email').value = student.email || '';
            document.getElementById('email').readOnly = true;
            document.getElementById('firstName').value = student.firstName || '';
            document.getElementById('lastName').value = student.lastName || '';
            document.getElementById('middleName').value = student.middleName || '';
            document.getElementById('year_level').value = student.year_level || '';
            document.getElementById('course').value = student.course || '';
            document.getElementById('contactNumber').value = student.contactNumber || '';
            document.getElementById('address').value = student.address || '';
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('password').required = false;
            document.getElementById('studentModal').classList.add('active');
        }
        
        function deleteStudent(userId, name) {
            if(confirm(`Are you sure you want to delete ${name}?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="delete_student" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
<?php
session_start();
include('db.php');

// Restrict access to Guidance only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Guidance') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Fetch statistics with error handling
$stats = [];
$stats['pending_review'] = 0;
$stats['under_review'] = 0;
$stats['endorsed'] = 0;
$stats['not_endorsed'] = 0;
$stats['active_scholars'] = 0;
$stats['total_reviewed'] = 0;

// Get stats safely
try {
    // Pending applications waiting for Guidance review (Recommended by Dean)
    $tables_check = $conn->query("SHOW TABLES LIKE 'scholarship_application'");
    if ($tables_check && $tables_check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Recommended'");
        if ($result) $stats['pending_review'] = $result->fetch_assoc()['count'];
        
        // Applications currently under Guidance review
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Under Guidance Review'");
        if ($result) $stats['under_review'] = $result->fetch_assoc()['count'];
        
        // Endorsed applications
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Endorsed by Guidance'");
        if ($result) $stats['endorsed'] = $result->fetch_assoc()['count'];
        
        // Not endorsed applications
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Not Endorsed'");
        if ($result) $stats['not_endorsed'] = $result->fetch_assoc()['count'];
        
        // Active scholarship recipients
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Approved'");
        if ($result) $stats['active_scholars'] = $result->fetch_assoc()['count'];
        
        // Total reviewed by guidance (endorsed + not endorsed)
        $stats['total_reviewed'] = $stats['endorsed'] + $stats['not_endorsed'];
    }

    // Recent scholarship applications pending review
    $recent_applications = $conn->query("
        SELECT sa.*, 
               p.name as student_name,
               p.student_number,
               p.course,
               p.year_level,
               dl.gpa
        FROM scholarship_application sa
        LEFT JOIN profile p ON sa.student_id = p.stud_id
        LEFT JOIN dean_list dl ON sa.student_id = dl.student_id
        WHERE sa.status IN ('Recommended', 'Under Guidance Review', 'On Hold - Guidance Review')
        ORDER BY 
            CASE 
                WHEN sa.status = 'On Hold - Guidance Review' THEN 1
                WHEN sa.status = 'Under Guidance Review' THEN 2
                WHEN sa.status = 'Recommended' THEN 3
            END,
            sa.application_date DESC
        LIMIT 8
    ");

} catch (Exception $e) {
    $errorMessage = "Error loading dashboard data: " . $e->getMessage();
}

// Get user info
$user_name = $_SESSION['user_name'] ?? 'Guidance Counselor';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FEU Roosevelt - Guidance Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #7c3aed;
            --primary-dark: #5b21b6;
            --primary-light: #8b5cf6;
            --secondary-purple: #a78bfa;
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
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
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
            position: sticky;
            top: 73px;
            height: calc(100vh - 73px);
            overflow-y: auto;
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
            position: relative;
        }
        
        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--primary-purple);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.2);
        }
        
        .nav-item i {
            width: 20px;
            font-size: 1.125rem;
        }
        
        .nav-badge {
            position: absolute;
            right: 1rem;
            background: #ef4444;
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
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
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary-purple);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--text-secondary);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.75rem;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.purple { border-left-color: #7c3aed; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.indigo { border-left-color: #6366f1; }
        .stat-card.teal { border-left-color: #14b8a6; }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card.purple .stat-icon { background: #f5f3ff; color: #7c3aed; }
        .stat-card.blue .stat-icon { background: #eff6ff; color: #3b82f6; }
        .stat-card.green .stat-icon { background: #f0fdf4; color: #10b981; }
        .stat-card.orange .stat-icon { background: #fffbeb; color: #f59e0b; }
        .stat-card.red .stat-icon { background: #fef2f2; color: #ef4444; }
        .stat-card.indigo .stat-icon { background: #eef2ff; color: #6366f1; }
        .stat-card.teal .stat-icon { background: #f0fdfa; color: #14b8a6; }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        
        /* Content Cards */
        .content-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: none;
        }
        
        .card-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .card-body {
            padding: 1.75rem;
        }
        
        .p-0 {
            padding: 0 !important;
        }
        
        /* Priority Alerts */
        .priority-alerts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .priority-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 1.25rem;
            border-radius: 12px;
            display: flex;
            align-items: start;
            gap: 1rem;
        }
        
        .priority-alert.urgent {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-left-color: #ef4444;
        }
        
        .priority-alert.info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left-color: #3b82f6;
        }
        
        .priority-alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .priority-alert-content h4 {
            font-size: 0.9375rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
        }
        
        .priority-alert-content p {
            font-size: 0.875rem;
            margin: 0;
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
        
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .student-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .student-details {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }
        
        /* Badges */
        .badge {
            padding: 0.375rem 0.875rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-purple {
            background: #f5f3ff;
            color: #5b21b6;
        }
        
        .badge-orange {
            background: #ffedd5;
            color: #9a3412;
        }
        
        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
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
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-purple);
            border: 2px solid var(--primary-purple);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-purple);
            color: white;
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
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
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
        
        /* Empty State */
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
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }
        
        .quick-action-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quick-action-card:hover {
            border-color: var(--primary-purple);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.15);
        }
        
        .quick-action-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .quick-action-content h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
        }
        
        .quick-action-content p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
        }
        
        @media (max-width: 768px) {
            .layout-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem 0;
                position: relative;
                height: auto;
            }
            
            .main-content {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header h1 {
                font-size: 1.25rem;
            }
            
            .user-info {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-hands-helping"></i>
            FEU Roosevelt DMS
            <span class="role-badge">Guidance & Counseling</span>
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
                <a href="guidance_dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="guidance_review.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Pending Review</span>
                    <?php if ($stats['pending_review'] > 0): ?>
                        <span class="nav-badge"><?= $stats['pending_review'] ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Scholarship Assessment</div>
                <a href="guidance_need_based.php" class="nav-item">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Need-Based Review</span>
                </a>
                <a href="guidance_leadership.php" class="nav-item">
                    <i class="fas fa-medal"></i>
                    <span>Leadership Review</span>
                </a>
                <a href="guidance_assessment.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Student Assessment</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Scholar Monitoring</div>
                <a href="guidance_scholars.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Active Scholars</span>
                </a>
                <a href="guidance_checkins.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Check-ins & Support</span>
                </a>
                <a href="guidance_renewal.php" class="nav-item">
                    <i class="fas fa-sync-alt"></i>
                    <span>Renewal Review</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Reports & Tools</div>
                <a href="guidance_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports & Analytics</span>
                </a>
                <a href="guidance_resources.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Resources</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Guidance Dashboard</h1>
                <p class="page-subtitle">Student Welfare & Scholarship Assessment Overview</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="guidance_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
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
            
            <!-- Priority Alerts -->
            <?php if ($stats['pending_review'] > 5): ?>
            <div class="priority-alerts">
                <div class="priority-alert">
                    <div class="priority-alert-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="priority-alert-content">
                        <h4>High Volume Alert</h4>
                        <p><?= $stats['pending_review'] ?> applications pending your review. Prioritize processing.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_review']) ?></div>
                    <div class="stat-label">Pending Review</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>From Dean</span>
                    </div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['under_review']) ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['endorsed']) ?></div>
                    <div class="stat-label">Endorsed</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Approved for Admin</span>
                    </div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['not_endorsed']) ?></div>
                    <div class="stat-label">Not Endorsed</div>
                </div>
                
                <div class="stat-card teal">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['active_scholars']) ?></div>
                    <div class="stat-label">Active Scholars</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>Monitoring</span>
                    </div>
                </div>
                
                <div class="stat-card indigo">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_reviewed']) ?></div>
                    <div class="stat-label">Total Reviewed</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fas fa-bolt"></i>
                        <span>Quick Actions</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="guidance_review.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #f5f3ff; color: #7c3aed;">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>Review Applications</h3>
                                <p><?= $stats['pending_review'] ?> pending assessments</p>
                            </div>
                        </a>
                        
                        <a href="guidance_scholars.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #f0fdfa; color: #14b8a6;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>Monitor Scholars</h3>
                                <p><?= $stats['active_scholars'] ?> active scholars</p>
                            </div>
                        </a>
                        
                        <a href="guidance_reports.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #eff6ff; color: #3b82f6;">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>View Reports</h3>
                                <p>Analytics & insights</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Applications Requiring Action -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Applications Requiring Action</span>
                        <span class="card-header-badge"><?= $stats['pending_review'] ?> Pending</span>
                    </div>
                    <a href="guidance_review.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: none;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>App ID</th>
                                    <th>Student</th>
                                    <th>Scholarship Type</th>
                                    <th>GPA</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($recent_applications) && $recent_applications && $recent_applications->num_rows > 0): ?>
                                    <?php while($app = $recent_applications->fetch_assoc()): 
                                        $priority = 'Normal';
                                        $priority_class = 'badge-info';
                                        
                                        // Determine priority
                                        if ($app['status'] == 'On Hold - Guidance Review') {
                                            $priority = 'Urgent';
                                            $priority_class = 'badge-danger';
                                        } elseif ($app['scholarship_type'] == 'Need-Based Scholarship') {
                                            $priority = 'High';
                                            $priority_class = 'badge-warning';
                                        }
                                        
                                        $status_class = 'badge-warning';
                                        if ($app['status'] == 'Under Guidance Review') $status_class = 'badge-info';
                                        if ($app['status'] == 'On Hold - Guidance Review') $status_class = 'badge-danger';
                                    ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($app['app_id']) ?></strong></td>
                                        <td>
                                            <div class="student-info">
                                                <span class="student-name"><?= htmlspecialchars($app['student_name'] ?? 'N/A') ?></span>
                                                <span class="student-details">
                                                    <?= htmlspecialchars($app['student_number'] ?? 'N/A') ?> • 
                                                    <?= htmlspecialchars($app['course'] ?? 'N/A') ?> • 
                                                    <?= htmlspecialchars($app['year_level'] ?? 'N/A') ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($app['scholarship_type']) ?></td>
                                        <td><strong><?= number_format($app['gpa'] ?? 0, 2) ?></strong></td>
                                        <td><?= date('M d, Y', strtotime($app['application_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <?= $app['status'] == 'Recommended' ? 'New' : 
                                                    ($app['status'] == 'Under Guidance Review' ? 'Reviewing' : 'Interview') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $priority_class ?>"><?= $priority ?></span>
                                        </td>
                                        <td>
                                            <a href="guidance_review_detail.php?app_id=<?= $app['app_id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                                Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <p>All applications reviewed! Great job!</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (isset($recent_applications) && $recent_applications && $recent_applications->num_rows > 0): ?>
                    <div style="padding: 1.25rem; text-align: right; border-top: 1px solid var(--border-color);">
                        <a href="guidance_review.php" class="btn btn-outline-primary btn-sm">
                            View All Applications <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity Summary -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fas fa-history"></i>
                        <span>Recent Activity Summary</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; font-weight: 700; color: #10b981; margin-bottom: 0.5rem;">
                                <?= $stats['endorsed'] ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Applications Endorsed
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                This semester
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; font-weight: 700; color: #3b82f6; margin-bottom: 0.5rem;">
                                <?= $stats['under_review'] ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Currently Reviewing
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                In progress
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; font-weight: 700; color: #14b8a6; margin-bottom: 0.5rem;">
                                <?= $stats['active_scholars'] ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Active Scholars
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                Under monitoring
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; font-weight: 700; color: #7c3aed; margin-bottom: 0.5rem;">
                                <?= $stats['total_reviewed'] ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Total Reviewed
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                All time
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center;">
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                            <i class="fas fa-info-circle"></i>
                            You're making a difference in students' lives through scholarship support!
                        </p>
                        <a href="guidance_reports.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-chart-bar"></i>
                            View Detailed Reports
                        </a>
                    </div>
                </div>
            </div>
        </main>
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
        
        // Add loading state to action buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-spinner fa-spin';
                    }
                }
            });
        });
    </script>
</body>
</html>
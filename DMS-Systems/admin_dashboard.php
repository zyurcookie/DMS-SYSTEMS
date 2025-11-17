<?php
session_start();
include('include/db.php');

// Restrict access to Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Fetch statistics with error handling
$stats = [];
$stats['total_students'] = 0;
$stats['total_documents'] = 0;
$stats['pending_documents'] = 0;
$stats['approved_documents'] = 0;
$stats['deans_list_verified'] = 0;
$stats['scholarship_approved'] = 0;
$stats['pending_verifications'] = 0;

// Get stats safely
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM user WHERE role='Student'");
    if ($result) $stats['total_students'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM document");
    if ($result) $stats['total_documents'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM document WHERE status='Pending'");
    if ($result) $stats['pending_documents'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM document WHERE status='Approved'");
    if ($result) $stats['approved_documents'] = $result->fetch_assoc()['count'];

    // Check if tables exist before querying
    $tables_check = $conn->query("SHOW TABLES LIKE 'dean_list'");
    if ($tables_check && $tables_check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM dean_list WHERE status='Verified'");
        if ($result) $stats['deans_list_verified'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM dean_list WHERE status='Pending'");
        if ($result) $stats['pending_verifications'] = $result->fetch_assoc()['count'];
    }

    $tables_check = $conn->query("SHOW TABLES LIKE 'scholarship_application'");
    if ($tables_check && $tables_check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Approved'");
        if ($result) $stats['scholarship_approved'] = $result->fetch_assoc()['count'];
    }

    // Recent documents
    $recent_docs = $conn->query("SELECT d.*, p.name as student_name 
                                 FROM document d 
                                 LEFT JOIN profile p ON d.stud_id = p.stud_id 
                                 ORDER BY d.upload_date DESC 
                                 LIMIT 5");
} catch (Exception $e) {
    $errorMessage = "Error loading dashboard data: " . $e->getMessage();
}

// Get user info
$user_name = $_SESSION['user_name'] ?? 'Admin';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FEU Roosevelt - Admin Dashboard</title>
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
            min-height: 100vh;
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
        
        .stat-card.green { border-left-color: #48bb78; }
        .stat-card.blue { border-left-color: #4299e1; }
        .stat-card.yellow { border-left-color: #ecc94b; }
        .stat-card.purple { border-left-color: #9f7aea; }
        .stat-card.red { border-left-color: #f56565; }
        .stat-card.indigo { border-left-color: #667eea; }
        
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
        
        .stat-card.green .stat-icon { background: #f0fff4; color: #48bb78; }
        .stat-card.blue .stat-icon { background: #ebf8ff; color: #4299e1; }
        .stat-card.yellow .stat-icon { background: #fffff0; color: #ecc94b; }
        .stat-card.purple .stat-icon { background: #faf5ff; color: #9f7aea; }
        .stat-card.red .stat-icon { background: #fff5f5; color: #f56565; }
        .stat-card.indigo .stat-icon { background: #ebf4ff; color: #667eea; }
        
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
        
        /* Content Cards */
        .content-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
        }
        
        .card-body {
            padding: 1.75rem;
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
            border-color: var(--primary-green);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(11, 102, 35, 0.15);
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
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 102, 35, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 102, 35, 0.3);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-green);
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
        
        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
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
                <a href="admin_dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="students.php" class="nav-item">
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
                <h1>Dashboard Overview</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Home</a></li>
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
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_students']) ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_documents']) ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
                
                <div class="stat-card yellow">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_documents']) ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['approved_documents']) ?></div>
                    <div class="stat-label">Approved Documents</div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['deans_list_verified']) ?></div>
                    <div class="stat-label">Dean's List Verified</div>
                </div>
                
                <div class="stat-card indigo">
                    <div class="stat-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['scholarship_approved']) ?></div>
                    <div class="stat-label">Scholarships Approved</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="deans_list.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #f0fff4; color: #48bb78;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>Manage Dean's List</h3>
                                <p><?= $stats['pending_verifications'] ?> pending verifications</p>
                            </div>
                        </a>
                        
                        <a href="scholarships.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #fffff0; color: #ecc94b;">
                                <i class="fas fa-award"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>Manage Scholarships</h3>
                                <p><?= $stats['scholarship_approved'] ?> approved scholarships</p>
                            </div>
                        </a>
                        
                        <a href="users.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #ebf8ff; color: #4299e1;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>User Management</h3>
                                <p><?= $stats['total_students'] ?> registered students</p>
                            </div>
                        </a>
                        
                        <a href="reports.php" class="quick-action-card">
                            <div class="quick-action-icon" style="background: #faf5ff; color: #9f7aea;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="quick-action-content">
                                <h3>View Reports</h3>
                                <p>Analytics & insights</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-file-alt"></i>
                    Recent Documents
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Upload Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($recent_docs) && $recent_docs && $recent_docs->num_rows > 0): ?>
                                    <?php while($doc = $recent_docs->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($doc['doc_id']) ?></strong></td>
                                        <td><?= htmlspecialchars($doc['student_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($doc['doc_type']) ?></td>
                                        <td><?= date('M d, Y', strtotime($doc['upload_date'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= 
                                                $doc['status'] == 'Approved' ? 'success' : 
                                                ($doc['status'] == 'Pending' ? 'warning' : 'danger') 
                                            ?>">
                                                <?= htmlspecialchars($doc['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="document-detail.php?doc_id=<?= $doc['doc_id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No recent documents</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding: 1.25rem; text-align: right; border-top: 1px solid var(--border-color);">
                        <a href="documents.php" class="btn btn-outline-primary btn-sm">
                            View All Documents <i class="fas fa-arrow-right"></i>
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
    </script>
</body>
</html>
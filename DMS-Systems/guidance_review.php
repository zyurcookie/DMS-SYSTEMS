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
$filterStatus = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $app_id = $_POST['app_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $guidance_notes = $_POST['guidance_notes'] ?? '';
    $guidance_recommendation = $_POST['guidance_recommendation'] ?? '';
    
    if ($app_id && $action) {
        try {
            $user_id = $_SESSION['user_id'];
            $reviewer_name = $_SESSION['user_name'];
            
            if ($action == 'start_review') {
                // Start reviewing the application
                $stmt = $conn->prepare("UPDATE scholarship_application SET 
                    status = 'Under Guidance Review',
                    guidance_reviewer_id = ?,
                    guidance_review_date = NOW()
                    WHERE app_id = ?");
                $stmt->bind_param("ii", $user_id, $app_id);
                
                if ($stmt->execute()) {
                    // Log activity
                    $activity = "Started guidance review";
                    $log_stmt = $conn->prepare("INSERT INTO application_logs (app_id, user_id, user_role, action, notes) VALUES (?, ?, 'Guidance', ?, ?)");
                    $log_stmt->bind_param("iiss", $app_id, $user_id, $activity, $guidance_notes);
                    $log_stmt->execute();
                    
                    $successMessage = "Application review started successfully!";
                }
                $stmt->close();
                
            } elseif ($action == 'endorse') {
                // Endorse the application
                $stmt = $conn->prepare("UPDATE scholarship_application SET 
                    status = 'Endorsed by Guidance',
                    guidance_decision = 'Endorsed',
                    guidance_notes = ?,
                    guidance_recommendation = ?,
                    guidance_decision_date = NOW()
                    WHERE app_id = ?");
                $stmt->bind_param("ssi", $guidance_notes, $guidance_recommendation, $app_id);
                
                if ($stmt->execute()) {
                    // Log activity
                    $activity = "Endorsed application";
                    $log_stmt = $conn->prepare("INSERT INTO application_logs (app_id, user_id, user_role, action, notes) VALUES (?, ?, 'Guidance', ?, ?)");
                    $log_stmt->bind_param("iiss", $app_id, $user_id, $activity, $guidance_notes);
                    $log_stmt->execute();
                    
                    // Create notification for admin
                    $notif_message = "Application #$app_id has been endorsed by Guidance";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_role, message, app_id, created_at) VALUES ('Admin', ?, ?, NOW())");
                    $notif_stmt->bind_param("si", $notif_message, $app_id);
                    $notif_stmt->execute();
                    
                    $successMessage = "Application endorsed successfully and forwarded to Admin!";
                }
                $stmt->close();
                
            } elseif ($action == 'not_endorse') {
                // Not endorse the application
                $stmt = $conn->prepare("UPDATE scholarship_application SET 
                    status = 'Not Endorsed',
                    guidance_decision = 'Not Endorsed',
                    guidance_notes = ?,
                    guidance_recommendation = ?,
                    guidance_decision_date = NOW()
                    WHERE app_id = ?");
                $stmt->bind_param("ssi", $guidance_notes, $guidance_recommendation, $app_id);
                
                if ($stmt->execute()) {
                    // Log activity
                    $activity = "Did not endorse application";
                    $log_stmt = $conn->prepare("INSERT INTO application_logs (app_id, user_id, user_role, action, notes) VALUES (?, ?, 'Guidance', ?, ?)");
                    $log_stmt->bind_param("iiss", $app_id, $user_id, $activity, $guidance_notes);
                    $log_stmt->execute();
                    
                    $successMessage = "Application marked as not endorsed.";
                }
                $stmt->close();
                
            } elseif ($action == 'hold') {
                // Put on hold for further review
                $stmt = $conn->prepare("UPDATE scholarship_application SET 
                    status = 'On Hold - Guidance Review',
                    guidance_notes = ?
                    WHERE app_id = ?");
                $stmt->bind_param("si", $guidance_notes, $app_id);
                
                if ($stmt->execute()) {
                    // Log activity
                    $activity = "Put application on hold";
                    $log_stmt = $conn->prepare("INSERT INTO application_logs (app_id, user_id, user_role, action, notes) VALUES (?, ?, 'Guidance', ?, ?)");
                    $log_stmt->bind_param("iiss", $app_id, $user_id, $activity, $guidance_notes);
                    $log_stmt->execute();
                    
                    $successMessage = "Application put on hold for further review.";
                }
                $stmt->close();
            }
            
        } catch (Exception $e) {
            $errorMessage = "Error processing action: " . $e->getMessage();
        }
    }
}

// Build query based on filters
$whereConditions = ["sa.status IN ('Recommended', 'Under Guidance Review', 'On Hold - Guidance Review')"];
$params = [];
$types = "";

if ($filterStatus != 'all') {
    $whereConditions[] = "sa.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(p.name LIKE ? OR p.student_number LIKE ? OR sa.scholarship_type LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get applications
$query = "
    SELECT sa.*, 
           p.name as student_name,
           p.student_number,
           p.course,
           p.year_level,
           p.email,
           p.contact_number,
           dl.gpa,
           u.name as reviewer_name
    FROM scholarship_application sa
    LEFT JOIN profile p ON sa.student_id = p.stud_id
    LEFT JOIN dean_list dl ON sa.student_id = dl.student_id
    LEFT JOIN users u ON sa.guidance_reviewer_id = u.user_id
    WHERE $whereClause
    ORDER BY 
        CASE 
            WHEN sa.status = 'On Hold - Guidance Review' THEN 1
            WHEN sa.status = 'Under Guidance Review' THEN 2
            WHEN sa.status = 'Recommended' THEN 3
        END,
        sa.application_date DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Recommended'");
$stats['pending'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Under Guidance Review'");
$stats['under_review'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='On Hold - Guidance Review'");
$stats['on_hold'] = $result->fetch_assoc()['count'];

$user_name = $_SESSION['user_name'] ?? 'Guidance Counselor';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Applications - Guidance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #7c3aed;
            --primary-dark: #5b21b6;
            --primary-light: #8b5cf6;
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
        
        .back-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }
        
        .container-fluid {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .stat-card.purple { border-left-color: #7c3aed; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.orange { border-left-color: #f59e0b; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .filter-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        .form-control, .form-select {
            padding: 0.625rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
            outline: none;
        }
        
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
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .content-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            gap: 0.75rem;
        }
        
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
        
        .badge {
            padding: 0.375rem 0.875rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-primary);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
            width: 30px;
            height: 30px;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .form-group textarea {
            width: 100%;
            min-height: 120px;
            resize: vertical;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
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
            <span class="role-badge">Guidance</span>
        </h1>
        <div class="user-info">
            <div class="user-avatar"><?= $user_initial ?></div>
            <span><?= htmlspecialchars($user_name) ?></span>
            <a href="guidance_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Dashboard
            </a>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h2>Review Applications</h2>
            <p class="page-subtitle">Assess scholarship applications and provide guidance recommendations</p>
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
        <div class="stats-row">
            <div class="stat-card purple">
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-value"><?= $stats['under_review'] ?></div>
                <div class="stat-label">Under Review</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-value"><?= $stats['on_hold'] ?></div>
                <div class="stat-label">On Hold</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Status Filter</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $filterStatus == 'all' ? 'selected' : '' ?>>All Applications</option>
                            <option value="Recommended" <?= $filterStatus == 'Recommended' ? 'selected' : '' ?>>New / Pending</option>
                            <option value="Under Guidance Review" <?= $filterStatus == 'Under Guidance Review' ? 'selected' : '' ?>>Under Review</option>
                            <option value="On Hold - Guidance Review" <?= $filterStatus == 'On Hold - Guidance Review' ? 'selected' : '' ?>>On Hold</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, student number..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Applications Table -->
        <div class="content-card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <span>Applications for Review (<?= $applications->num_rows ?>)</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>App ID</th>
                            <th>Student Information</th>
                            <th>Scholarship Type</th>
                            <th>GPA</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Reviewer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications->num_rows > 0): ?>
                            <?php while($app = $applications->fetch_assoc()): 
                                $status_class = 'badge-warning';
                                if ($app['status'] == 'Under Guidance Review') $status_class = 'badge-info';
                                if ($app['status'] == 'On Hold - Guidance Review') $status_class = 'badge-danger';
                            ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($app['app_id']) ?></strong></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?= htmlspecialchars($app['student_name']) ?></span>
                                        <span class="student-details">
                                            <?= htmlspecialchars($app['student_number']) ?> • 
                                            <?= htmlspecialchars($app['course']) ?> • 
                                            <?= htmlspecialchars($app['year_level']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($app['scholarship_type']) ?></td>
                                <td><strong><?= number_format($app['gpa'], 2) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($app['application_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= $app['status'] == 'Recommended' ? 'New' : ($app['status'] == 'Under Guidance Review' ? 'Reviewing' : 'On Hold') ?>
                                    </span>
                                </td>
                                <td><?= $app['reviewer_name'] ? htmlspecialchars($app['reviewer_name']) : '-' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($app['status'] == 'Recommended'): ?>
                                            <button class="btn btn-primary btn-sm" onclick="startReview(<?= $app['app_id'] ?>)">
                                                <i class="fas fa-play"></i>
                                                Start Review
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm" onclick="openEndorseModal(<?= $app['app_id'] ?>)">
                                                <i class="fas fa-thumbs-up"></i>
                                                Endorse
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="openNotEndorseModal(<?= $app['app_id'] ?>)">
                                                <i class="fas fa-thumbs-down"></i>
                                                Not Endorse
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="openHoldModal(<?= $app['app_id'] ?>)">
                                                <i class="fas fa-pause"></i>
                                                Hold
                                            </button>
                                        <?php endif; ?>
                                        <a href="guidance_review_detail.php?app_id=<?= $app['app_id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No applications found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Start Review Modal -->
    <div id="startReviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-play-circle"></i> Start Review</h3>
                <button class="modal-close" onclick="closeModal('startReviewModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="app_id" id="startReviewAppId">
                    <input type="hidden" name="action" value="start_review">
                    
                    <div class="form-group">
                        <label>Initial Notes (Optional)</label>
                        <textarea name="guidance_notes" class="form-control" placeholder="Add any initial observations or notes about this application..."></textarea>
                    </div>
                    
                    <div style="background: #f0f9ff; padding: 1rem; border-radius: 8px; border-left: 4px solid #3b82f6;">
                        <p style="margin: 0; font-size: 0.875rem; color: #1e40af;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Starting the review will mark this application as "Under Guidance Review" and assign it to you.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('startReviewModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i>
                        Start Review
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Endorse Modal -->
    <div id="endorseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-thumbs-up"></i> Endorse Application</h3>
                <button class="modal-close" onclick="closeModal('endorseModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="app_id" id="endorseAppId">
                    <input type="hidden" name="action" value="endorse">
                    
                    <div class="form-group">
                        <label>Guidance Assessment Notes <span style="color: #ef4444;">*</span></label>
                        <textarea name="guidance_notes" class="form-control" required placeholder="Provide your assessment of the student's eligibility, character, and suitability for the scholarship..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Recommendation for Admin <span style="color: #ef4444;">*</span></label>
                        <textarea name="guidance_recommendation" class="form-control" required placeholder="Your recommendation to the Admin office regarding this application..."></textarea>
                    </div>
                    
                    <div style="background: #f0fdf4; padding: 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                        <p style="margin: 0; font-size: 0.875rem; color: #065f46;">
                            <i class="fas fa-check-circle"></i>
                            <strong>Endorsing this application will:</strong>
                        </p>
                        <ul style="margin: 0.5rem 0 0 1.5rem; font-size: 0.875rem; color: #065f46;">
                            <li>Forward it to the Admin office for final approval</li>
                            <li>Include your assessment and recommendation</li>
                            <li>Notify relevant stakeholders</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('endorseModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-thumbs-up"></i>
                        Endorse Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Not Endorse Modal -->
    <div id="notEndorseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-thumbs-down"></i> Not Endorse Application</h3>
                <button class="modal-close" onclick="closeModal('notEndorseModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="app_id" id="notEndorseAppId">
                    <input type="hidden" name="action" value="not_endorse">
                    
                    <div class="form-group">
                        <label>Reason for Non-Endorsement <span style="color: #ef4444;">*</span></label>
                        <textarea name="guidance_notes" class="form-control" required placeholder="Clearly explain why this application is not being endorsed. Be specific and professional..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Feedback & Recommendations <span style="color: #ef4444;">*</span></label>
                        <textarea name="guidance_recommendation" class="form-control" required placeholder="Provide constructive feedback for the student and any recommendations for improvement..."></textarea>
                    </div>
                    
                    <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; border-left: 4px solid #ef4444;">
                        <p style="margin: 0; font-size: 0.875rem; color: #991b1b;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> Not endorsing will reject this application. The student will be notified with your feedback. Please ensure your assessment is thorough and fair.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('notEndorseModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-thumbs-down"></i>
                        Not Endorse
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Hold Modal -->
    <div id="holdModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pause-circle"></i> Put Application On Hold</h3>
                <button class="modal-close" onclick="closeModal('holdModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="app_id" id="holdAppId">
                    <input type="hidden" name="action" value="hold">
                    
                    <div class="form-group">
                        <label>Reason for Hold <span style="color: #ef4444;">*</span></label>
                        <textarea name="guidance_notes" class="form-control" required placeholder="Explain why this application needs to be put on hold (e.g., pending additional documentation, need to discuss with Dean/Registrar, require student clarification, etc.)"></textarea>
                    </div>
                    
                    <div style="background: #fffbeb; padding: 1rem; border-radius: 8px; border-left: 4px solid #f59e0b;">
                        <p style="margin: 0; font-size: 0.875rem; color: #92400e;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Applications on hold will be flagged for follow-up. You can resume review once the pending issues are resolved.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('holdModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-pause"></i>
                        Put On Hold
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function startReview(appId) {
            document.getElementById('startReviewAppId').value = appId;
            openModal('startReviewModal');
        }
        
        function openEndorseModal(appId) {
            document.getElementById('endorseAppId').value = appId;
            openModal('endorseModal');
        }
        
        function openNotEndorseModal(appId) {
            document.getElementById('notEndorseAppId').value = appId;
            openModal('notEndorseModal');
        }
        
        function openHoldModal(appId) {
            document.getElementById('holdAppId').value = appId;
            openModal('holdModal');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const textareas = this.querySelectorAll('textarea[required]');
                let isValid = true;
                
                textareas.forEach(textarea => {
                    if (textarea.value.trim() === '') {
                        isValid = false;
                        textarea.style.borderColor = '#ef4444';
                    } else {
                        textarea.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>
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

// Get application ID
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

if ($app_id <= 0) {
    header('Location: guidance_review.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $guidance_user_id = $_SESSION['user_id'];
    
    if ($action == 'start_review') {
        // Start reviewing the application
        $stmt = $conn->prepare("UPDATE scholarship_application SET status = 'Under Guidance Review', guidance_reviewed_by = ? WHERE app_id = ?");
        $stmt->bind_param("ii", $guidance_user_id, $app_id);
        
        if ($stmt->execute()) {
            $successMessage = "Application review started successfully!";
        } else {
            $errorMessage = "Error starting review: " . $conn->error;
        }
        $stmt->close();
        
    } elseif ($action == 'endorse') {
        // Endorse the application
        $remarks = trim($_POST['guidance_remarks'] ?? '');
        
        $stmt = $conn->prepare("UPDATE scholarship_application SET 
            status = 'Endorsed by Guidance', 
            guidance_reviewed_by = ?, 
            guidance_review_date = NOW(),
            guidance_remarks = ?,
            guidance_recommendation = 'Endorsed'
            WHERE app_id = ?");
        $stmt->bind_param("isi", $guidance_user_id, $remarks, $app_id);
        
        if ($stmt->execute()) {
            $successMessage = "Application endorsed successfully! It will be forwarded to Admin.";
            header("Refresh: 2; url=guidance_review.php");
        } else {
            $errorMessage = "Error endorsing application: " . $conn->error;
        }
        $stmt->close();
        
    } elseif ($action == 'not_endorse') {
        // Do not endorse the application
        $remarks = trim($_POST['guidance_remarks'] ?? '');
        
        if (empty($remarks)) {
            $errorMessage = "Please provide remarks explaining why the application is not endorsed.";
        } else {
            $stmt = $conn->prepare("UPDATE scholarship_application SET 
                status = 'Not Endorsed', 
                guidance_reviewed_by = ?, 
                guidance_review_date = NOW(),
                guidance_remarks = ?,
                guidance_recommendation = 'Not Endorsed'
                WHERE app_id = ?");
            $stmt->bind_param("isi", $guidance_user_id, $remarks, $app_id);
            
            if ($stmt->execute()) {
                $successMessage = "Application marked as not endorsed.";
                header("Refresh: 2; url=guidance_review.php");
            } else {
                $errorMessage = "Error updating application: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch application details
$query = "
    SELECT sa.*, 
           p.name as student_name,
           p.student_number,
           p.firstName,
           p.lastName,
           p.course,
           p.year_level,
           p.contactNumber,
           p.address,
           u.email as student_email,
           s.qpa,
           s.min_grade,
           s.is_regular_student,
           s.eligible,
           s.eligibility_status,
           dl.gpa,
           dl.qpa as dean_qpa,
           dl.status as dean_list_status,
           dl.remarks as dean_remarks,
           dean_user.user_name as dean_name,
           guidance_user.user_name as guidance_reviewer_name
    FROM scholarship_application sa
    LEFT JOIN profile p ON sa.stud_id = p.stud_id
    LEFT JOIN user u ON sa.student_id = u.user_id
    LEFT JOIN student s ON sa.stud_id = s.stud_id
    LEFT JOIN dean_list dl ON sa.student_id = dl.student_id 
        AND sa.academic_year = dl.academic_year 
        AND sa.semester = dl.semester
    LEFT JOIN user dean_user ON sa.reviewed_by = dean_user.user_id
    LEFT JOIN user guidance_user ON sa.guidance_reviewed_by = guidance_user.user_id
    WHERE sa.app_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: guidance_review.php');
    exit();
}

$application = $result->fetch_assoc();
$stmt->close();

// Fetch student's documents
$docs_query = "
    SELECT d.*, dt.tag_name
    FROM document d
    LEFT JOIN document_tags dtags ON d.doc_id = dtags.doc_id
    LEFT JOIN tags dt ON dtags.tag_id = dt.tag_id
    WHERE d.student_id = ? AND d.related_type = 'scholarship'
    ORDER BY d.upload_date DESC
";
$docs_stmt = $conn->prepare($docs_query);
$docs_stmt->bind_param("i", $application['student_id']);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();
$docs_stmt->close();

// Fetch guidance assessment if exists
$assessment_query = "SELECT * FROM guidance_assessment WHERE app_id = ?";
$assessment_stmt = $conn->prepare($assessment_query);
$assessment_stmt->bind_param("i", $app_id);
$assessment_stmt->execute();
$assessment = $assessment_stmt->get_result()->fetch_assoc();
$assessment_stmt->close();

$user_name = $_SESSION['user_name'] ?? 'Guidance Counselor';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Application #<?= $app_id ?> - FEU Roosevelt</title>
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
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
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
        
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: start;
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
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0 0 1rem 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary-purple);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--text-secondary);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
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
            gap: 0.75rem;
            border: none;
        }
        
        .card-body {
            padding: 1.75rem;
        }
        
        .info-row {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 180px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: var(--text-primary);
            flex: 1;
        }
        
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
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-purple {
            background: #f5f3ff;
            color: #5b21b6;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
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
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
            color: white;
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-purple);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .document-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--hover-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .document-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--primary-purple);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .document-details h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }
        
        .document-details p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .score-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .score-bar {
            width: 100px;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .score-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-purple), var(--primary-light));
            border-radius: 4px;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1.5rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-row {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .info-label {
                width: 100%;
            }
        }
    </style>
</head>
<body>
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
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="guidance_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="guidance_review.php">Review Applications</a></li>
                        <li class="breadcrumb-item active">Application #<?= $app_id ?></li>
                    </ol>
                </nav>
                <h1>Review Application #<?= $app_id ?></h1>
                <p class="page-subtitle">
                    <?= htmlspecialchars($application['student_name']) ?> • 
                    <?= htmlspecialchars($application['scholarship_type']) ?>
                </p>
            </div>
            <div>
                <a href="guidance_review.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to List
                </a>
            </div>
        </div>
        
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
        
        <?php if ($application['status'] == 'Recommended'): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>This application has been recommended by the Dean and is waiting for your review.</span>
        </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Student Information -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        Student Information
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Full Name:</div>
                            <div class="info-value"><strong><?= htmlspecialchars($application['student_name']) ?></strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Student Number:</div>
                            <div class="info-value"><?= htmlspecialchars($application['student_number']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?= htmlspecialchars($application['student_email']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Contact Number:</div>
                            <div class="info-value"><?= htmlspecialchars($application['contactNumber'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Course:</div>
                            <div class="info-value"><?= htmlspecialchars($application['course']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Year Level:</div>
                            <div class="info-value"><?= htmlspecialchars($application['year_level']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value"><?= htmlspecialchars($application['address'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Academic Performance -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-graduation-cap"></i>
                        Academic Performance
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">GPA:</div>
                            <div class="info-value">
                                <div class="score-display">
                                    <span style="color: var(--success);"><?= number_format($application['gpa'] ?? 0, 2) ?></span>
                                    <div class="score-bar">
                                        <div class="score-bar-fill" style="width: <?= ($application['gpa'] ?? 0) * 25 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">QPA:</div>
                            <div class="info-value"><?= number_format($application['qpa'] ?? 0, 2) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Minimum Grade:</div>
                            <div class="info-value"><?= number_format($application['min_grade'] ?? 0, 2) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Regular Student:</div>
                            <div class="info-value">
                                <?php if ($application['is_regular_student']): ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Eligibility:</div>
                            <div class="info-value">
                                <?php if ($application['eligible']): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($application['eligibility_status']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><?= htmlspecialchars($application['eligibility_status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Scholarship Details -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-award"></i>
                        Scholarship Application Details
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Scholarship Type:</div>
                            <div class="info-value"><strong><?= htmlspecialchars($application['scholarship_type']) ?></strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Academic Year:</div>
                            <div class="info-value"><?= htmlspecialchars($application['academic_year']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Semester:</div>
                            <div class="info-value"><?= htmlspecialchars($application['semester']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Application Date:</div>
                            <div class="info-value"><?= date('F d, Y', strtotime($application['application_date'])) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Current Status:</div>
                            <div class="info-value">
                                <?php
                                $status_class = 'badge-warning';
                                if ($application['status'] == 'Under Guidance Review') {
                                    $status_class = 'badge-info';
                                } elseif ($application['status'] == 'On Hold - Guidance Review') {
                                    $status_class = 'badge-danger';
                                } elseif ($application['status'] == 'Endorsed by Guidance') {
                                    $status_class = 'badge-success';
                                }
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <?= htmlspecialchars($application['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($application['dean_name'])): ?>
                        <div class="info-row">
                            <div class="info-label">Reviewed by Dean:</div>
                            <div class="info-value"><?= htmlspecialchars($application['dean_name']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($application['remarks'])): ?>
                        <div class="info-row">
                            <div class="info-label">Dean's Remarks:</div>
                            <div class="info-value">
                                <div style="background: var(--hover-bg); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-purple);">
                                    <?= nl2br(htmlspecialchars($application['remarks'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Guidance Review Section -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-check"></i>
                        Guidance Review & Recommendation
                    </div>
                    <div class="card-body">
                        <?php if ($application['status'] == 'Recommended'): ?>
                        <!-- Start Review Form -->
                        <form method="POST" action="">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Click "Start Review" to begin evaluating this application.</span>
                            </div>
                            <input type="hidden" name="action" value="start_review">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play"></i>
                                Start Review
                            </button>
                        </form>
                        
                        <?php elseif ($application['status'] == 'Under Guidance Review' || $application['status'] == 'On Hold - Guidance Review'): ?>
                        <!-- Review Form -->
                        <form method="POST" action="" id="reviewForm">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-comment-dots"></i>
                                    Your Assessment & Remarks
                                </label>
                                <textarea name="guidance_remarks" class="form-control" rows="6" placeholder="Provide your professional assessment of the student's eligibility, character, financial need, or any other relevant observations..."><?= htmlspecialchars($application['guidance_remarks'] ?? '') ?></textarea>
                                <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                    Consider: Financial need, personal circumstances, character references, leadership qualities, and overall suitability for the scholarship.
                                </small>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" name="action" value="endorse" class="btn btn-success" onclick="return confirm('Are you sure you want to ENDORSE this application? It will be forwarded to Admin for final approval.')">
                                    <i class="fas fa-thumbs-up"></i>
                                    Endorse Application
                                </button>
                                
                                <button type="submit" name="action" value="not_endorse" class="btn btn-danger" onclick="return confirmNotEndorse()">
                                    <i class="fas fa-times-circle"></i>
                                    Do Not Endorse
                                </button>
                            </div>
                        </form>
                        
                        <?php else: ?>
                        <!-- Already Reviewed -->
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span>This application has already been reviewed by the Guidance office.</span>
                        </div>
                        
                        <?php if (!empty($application['guidance_reviewed_by'])): ?>
                        <div class="info-row">
                            <div class="info-label">Reviewed By:</div>
                            <div class="info-value"><?= htmlspecialchars($application['guidance_reviewer_name'] ?? 'N/A') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['guidance_review_date'])): ?>
                        <div class="info-row">
                            <div class="info-label">Review Date:</div>
                            <div class="info-value"><?= date('F d, Y g:i A', strtotime($application['guidance_review_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['guidance_recommendation'])): ?>
                        <div class="info-row">
                            <div class="info-label">Recommendation:</div>
                            <div class="info-value">
                                <span class="badge <?= $application['guidance_recommendation'] == 'Endorsed' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars($application['guidance_recommendation']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['guidance_remarks'])): ?>
                        <div class="info-row">
                            <div class="info-label">Guidance Remarks:</div>
                            <div class="info-value">
                                <div style="background: var(--hover-bg); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--success);">
                                    <?= nl2br(htmlspecialchars($application['guidance_remarks'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Application Summary -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        Application Summary
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 1.5rem 0;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">
                                <?php
                                $status_icon = 'fa-clock';
                                $status_color = '#f59e0b';
                                if ($application['status'] == 'Under Guidance Review') {
                                    $status_icon = 'fa-search';
                                    $status_color = '#3b82f6';
                                } elseif ($application['status'] == 'Endorsed by Guidance') {
                                    $status_icon = 'fa-check-circle';
                                    $status_color = '#10b981';
                                } elseif ($application['status'] == 'Not Endorsed') {
                                    $status_icon = 'fa-times-circle';
                                    $status_color = '#ef4444';
                                }
                                ?>
                                <i class="fas <?= $status_icon ?>" style="color: <?= $status_color ?>;"></i>
                            </div>
                            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($application['status']) ?>
                            </h3>
                            <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                Current Application Status
                            </p>
                        </div>
                        
                        <div style="border-top: 2px solid var(--border-color); margin: 1.5rem 0; padding-top: 1.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin: 0 0 1rem 0; text-transform: uppercase;">
                                Quick Info
                            </h4>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-secondary);">Application ID:</span>
                                    <strong>#<?= $app_id ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-secondary);">Student ID:</span>
                                    <strong><?= htmlspecialchars($application['student_number']) ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-secondary);">GPA:</span>
                                    <strong style="color: var(--success);"><?= number_format($application['gpa'] ?? 0, 2) ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--text-secondary);">Applied:</span>
                                    <strong><?= date('M d, Y', strtotime($application['application_date'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Guidance Assessment (if exists) -->
                <?php if ($assessment): ?>
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-file-medical"></i>
                        Assessment Scores
                    </div>
                    <div class="card-body">
                        <?php if (!empty($assessment['financial_need_score'])): ?>
                        <div style="margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; font-size: 0.875rem;">Financial Need</span>
                                <span style="font-weight: 700; color: var(--primary-purple);"><?= $assessment['financial_need_score'] ?>/10</span>
                            </div>
                            <div class="score-bar">
                                <div class="score-bar-fill" style="width: <?= $assessment['financial_need_score'] * 10 ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($assessment['character_score'])): ?>
                        <div style="margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; font-size: 0.875rem;">Character</span>
                                <span style="font-weight: 700; color: var(--primary-purple);"><?= $assessment['character_score'] ?>/10</span>
                            </div>
                            <div class="score-bar">
                                <div class="score-bar-fill" style="width: <?= $assessment['character_score'] * 10 ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($assessment['leadership_score'])): ?>
                        <div style="margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; font-size: 0.875rem;">Leadership</span>
                                <span style="font-weight: 700; color: var(--primary-purple);"><?= $assessment['leadership_score'] ?>/10</span>
                            </div>
                            <div class="score-bar">
                                <div class="score-bar-fill" style="width: <?= $assessment['leadership_score'] * 10 ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($assessment['overall_recommendation'])): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; font-size: 0.875rem;">Overall:</span>
                                <span class="badge badge-<?= $assessment['overall_recommendation'] == 'Strongly Recommended' ? 'success' : 'info' ?>">
                                    <?= htmlspecialchars($assessment['overall_recommendation']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Submitted Documents -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-file-alt"></i>
                        Submitted Documents
                    </div>
                    <div class="card-body">
                        <?php if ($documents && $documents->num_rows > 0): ?>
                        <div class="document-list">
                            <?php 
                            $docs_array = [];
                            while($doc = $documents->fetch_assoc()) {
                                $docs_array[$doc['doc_id']] = $doc;
                            }
                            
                            foreach($docs_array as $doc): 
                                $status_class = 'badge-warning';
                                if ($doc['status'] == 'Approved') $status_class = 'badge-success';
                                if ($doc['status'] == 'Rejected') $status_class = 'badge-danger';
                            ?>
                            <div class="document-item">
                                <div class="document-info">
                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="document-details">
                                        <h4><?= htmlspecialchars($doc['doc_name']) ?></h4>
                                        <p>
                                            <?= htmlspecialchars($doc['doc_type']) ?> • 
                                            <?= date('M d, Y', strtotime($doc['upload_date'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="badge <?= $status_class ?>">
                                    <?= htmlspecialchars($doc['status']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No documents submitted yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="guidance_assessment.php?app_id=<?= $app_id ?>" class="btn btn-outline-primary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-file-medical"></i>
                                Create Assessment
                            </a>
                            <a href="mailto:<?= htmlspecialchars($application['student_email']) ?>" class="btn btn-outline-primary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-envelope"></i>
                                Email Student
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmNotEndorse() {
            const remarks = document.querySelector('textarea[name="guidance_remarks"]').value.trim();
            if (remarks.length < 20) {
                alert('Please provide detailed remarks (at least 20 characters) explaining why this application is not endorsed.');
                return false;
            }
            return confirm('Are you sure you want to NOT ENDORSE this application? This action will reject the scholarship application.');
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-info')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            alert.remove();
                        }, 300);
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>
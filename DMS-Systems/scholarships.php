<?php
session_start();
include('include/db.php');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Dean', 'Registrar'])) {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle Update Application Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $app_id = $_POST['app_id'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        $sql = "UPDATE scholarship_application SET status=?, remarks=?, reviewed_by=?, review_date=NOW() WHERE app_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $app_id);
        
        if ($stmt->execute()) {
            $successMessage = "Application status updated successfully!";
            
            // Log audit
            $audit = "INSERT INTO audit_trail (user_id, action, table_name, record_id, timestamp) VALUES (?, 'Updated Scholarship Status', 'scholarship_application', ?, NOW())";
            $audit_stmt = $conn->prepare($audit);
            $audit_stmt->bind_param("ii", $_SESSION['user_id'], $app_id);
            $audit_stmt->execute();
        } else {
            $errorMessage = "Error updating application status.";
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get filters
$year_filter = $_GET['academic_year'] ?? '';
$semester_filter = $_GET['semester'] ?? '';
$type_filter = $_GET['scholarship_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with prepared statements
$sql = "SELECT sa.*, p.firstName, p.lastName, p.student_number, p.course, p.year_level, u.email,
        dl.gpa, dl.status as deans_status,
        r.email as reviewer_email
        FROM scholarship_application sa
        INNER JOIN user u ON sa.student_id = u.user_id
        INNER JOIN profile p ON u.user_id = p.user_id
        LEFT JOIN dean_list dl ON sa.student_id = dl.student_id AND sa.academic_year = dl.academic_year AND sa.semester = dl.semester
        LEFT JOIN user r ON sa.reviewed_by = r.user_id
        WHERE 1=1";

$params = [];
$types = "";

if ($year_filter) {
    $sql .= " AND sa.academic_year = ?";
    $params[] = $year_filter;
    $types .= "s";
}
if ($semester_filter) {
    $sql .= " AND sa.semester = ?";
    $params[] = $semester_filter;
    $types .= "s";
}
if ($type_filter) {
    $sql .= " AND sa.scholarship_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($status_filter) {
    $sql .= " AND sa.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($search) {
    $sql .= " AND (p.firstName LIKE ? OR p.lastName LIKE ? OR p.student_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY sa.application_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status='Under Review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected
    FROM scholarship_application";
if ($year_filter) {
    $stats_sql .= " WHERE academic_year = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("s", $year_filter);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
} else {
    $stats = $conn->query($stats_sql)->fetch_assoc();
}

$user_name = $_SESSION['user_name'] ?? $_SESSION['role'];
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Scholarships Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      min-height: 100vh;
      color: var(--text-primary);
    }
    
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
    
    .container {
      display: flex;
      min-height: calc(100vh - 73px);
    }
    
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
      margin-bottom: 0.5rem;
      border-radius: 10px;
      color: var(--text-primary);
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
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
    
    .main-content {
      flex: 1;
      padding: 2.5rem;
      overflow-y: auto;
    }
    
    .page-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .alert {
      padding: 1rem 1.5rem;
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
    
    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border-left: 4px solid #ef4444;
    }
    
    @keyframes slideIn {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: var(--card-bg);
      padding: 1.75rem;
      border-radius: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      border-left: 4px solid var(--primary-green);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card h3 {
      font-size: 0.875rem;
      color: var(--text-secondary);
      text-transform: uppercase;
      margin-bottom: 0.75rem;
      font-weight: 600;
    }
    
    .stat-value {
      font-size: 2.25rem;
      font-weight: 700;
      color: var(--primary-green);
    }
    
    .toolbar {
      background: var(--card-bg);
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 1.5rem;
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .search-box {
      flex: 1;
      min-width: 200px;
      position: relative;
    }
    
    .search-box input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.875rem;
      transition: border-color 0.3s;
    }
    
    .search-box input:focus {
      outline: none;
      border-color: var(--primary-green);
    }
    
    .search-box i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-secondary);
    }
    
    select {
      padding: 0.75rem 1rem;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.875rem;
      background: white;
      cursor: pointer;
      transition: border-color 0.3s;
    }
    
    select:focus {
      outline: none;
      border-color: var(--primary-green);
    }
    
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
      font-size: 0.875rem;
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
    
    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.8rem;
    }
    
    .content-card {
      background: var(--card-bg);
      border-radius: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    
    .table-responsive {
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th {
      background: var(--hover-bg);
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      color: var(--text-primary);
      font-size: 0.875rem;
      text-transform: uppercase;
      border-bottom: 2px solid var(--border-color);
    }
    
    td {
      padding: 1rem;
      border-bottom: 1px solid var(--border-color);
      color: var(--text-primary);
    }
    
    tr:hover {
      background: var(--hover-bg);
    }
    
    .badge {
      display: inline-block;
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .badge-submitted {
      background: #dbeafe;
      color: #1e40af;
    }
    
    .badge-underreview {
      background: #fef3c7;
      color: #92400e;
    }
    
    .badge-approved {
      background: #d1fae5;
      color: #065f46;
    }
    
    .badge-rejected {
      background: #fee2e2;
      color: #991b1b;
    }
    
    .badge-onhold {
      background: #e0e7ff;
      color: #3730a3;
    }
    
    .badge-verified {
      background: #d1fae5;
      color: #065f46;
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
      animation: fadeIn 0.3s;
    }
    
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      max-width: 700px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      animation: slideUp 0.3s;
    }
    
    @keyframes slideUp {
      from { transform: translateY(50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .modal-header h2 {
      font-size: 1.5rem;
      color: var(--text-primary);
    }
    
    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-secondary);
      transition: color 0.3s;
    }
    
    .close-btn:hover {
      color: var(--text-primary);
    }
    
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border-color);
    }
    
    .info-label {
      font-weight: 600;
      color: var(--text-secondary);
    }
    
    .info-value {
      color: var(--text-primary);
      font-weight: 500;
      text-align: right;
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
    
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.875rem;
      transition: border-color 0.3s;
    }
    
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-green);
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
    
    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      
      .sidebar {
        width: 100%;
        position: relative;
        height: auto;
      }
      
      .main-content {
        padding: 1.5rem;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      
      .search-box {
        min-width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-graduation-cap"></i> FEU Roosevelt DMS</h1>
    <div class="user-info">
      <div class="user-avatar"><?= $user_initial ?></div>
      <span><?= htmlspecialchars($user_name) ?></span>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
  
  <div class="container">
    <aside class="sidebar">
      <div class="nav-section">
        <div class="nav-section-title">Main Menu</div>
        <a href="admin_dashboard.php" class="nav-item">
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
        <a href="scholarships.php" class="nav-item active">
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
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-award"></i> Scholarships Management</h1>
      
      <?php if($successMessage): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($successMessage) ?></span>
      </div>
      <?php endif; ?>
      
      <?php if($errorMessage): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($errorMessage) ?></span>
      </div>
      <?php endif; ?>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Applications</h3>
          <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Submitted</h3>
          <div class="stat-value"><?= number_format($stats['submitted']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Under Review</h3>
          <div class="stat-value"><?= number_format($stats['under_review']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Approved</h3>
          <div class="stat-value"><?= number_format($stats['approved']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Rejected</h3>
          <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
      </div>
      
      <div class="toolbar">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select id="yearFilter">
          <option value="">All Academic Years</option>
          <option value="2024-2025" <?= $year_filter == '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
          <option value="2025-2026" <?= $year_filter == '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
        </select>
        <select id="semesterFilter">
          <option value="">All Semesters</option>
          <option value="1st Semester" <?= $semester_filter == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
          <option value="2nd Semester" <?= $semester_filter == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
        </select>
        <select id="typeFilter">
          <option value="">All Types</option>
          <option value="Academic Excellence" <?= $type_filter == 'Academic Excellence' ? 'selected' : '' ?>>Academic Excellence</option>
          <option value="Dean's List" <?= $type_filter == "Dean's List" ? 'selected' : '' ?>>Dean's List</option>
          <option value="Merit-based" <?= $type_filter == 'Merit-based' ? 'selected' : '' ?>>Merit-based</option>
          <option value="Need-based" <?= $type_filter == 'Need-based' ? 'selected' : '' ?>>Need-based</option>
        </select>
        <select id="statusFilter">
          <option value="">All Status</option>
          <option value="Submitted" <?= $status_filter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
          <option value="Under Review" <?= $status_filter == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
          <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="On Hold" <?= $status_filter == 'On Hold' ? 'selected' : '' ?>>On Hold</option>
        </select>
      </div>
      
      <div class="content-card">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Student Number</th>
                <th>Name</th>
                <th>Year Level</th>
                <th>Academic Year</th>
                <th>Scholarship Type</th>
                <th>GPA</th>
                <th>Status</th>
                <th>Application Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if($applications && $applications->num_rows > 0): ?>
                <?php while($app = $applications->fetch_assoc()): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($app['student_number']) ?></strong></td>
                  <td><?= htmlspecialchars($app['lastName'] . ', ' . $app['firstName']) ?></td>
                  <td><?= htmlspecialchars($app['year_level']) ?></td>
                  <td><?= htmlspecialchars($app['academic_year'] . ' ' . $app['semester']) ?></td>
                  <td><?= htmlspecialchars($app['scholarship_type']) ?></td>
                  <td><strong><?= $app['gpa'] ? number_format($app['gpa'], 2) : 'N/A' ?></strong></td>
                  <td>
                    <span class="badge badge-<?= str_replace(' ', '', strtolower($app['status'])) ?>">
                      <?= htmlspecialchars($app['status']) ?>
                    </span>
                  </td>
                  <td><?= date('M d, Y', strtotime($app['application_date'])) ?></td>
                  <td>
                    <button class="btn btn-primary btn-sm" onclick='viewApplication(<?= json_encode($app) ?>)'>
                      <i class="fas fa-eye"></i> View
                    </button>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No scholarship applications found</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
  
  <!-- View Application Modal -->
  <div id="appModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Scholarship Application Details</h2>
        <button class="close-btn" onclick="closeModal()">&times;</button>
      </div>
      <div id="appDetails"></div>
      <form method="POST" style="margin-top: 2rem;">
        <input type="hidden" name="app_id" id="app_id">
        <div class="form-group">
          <label>Update Status</label>
          <select name="status" id="status_select" required>
            <option value="Submitted">Submitted</option>
            <option value="Under Review">Under Review</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
            <option value="On Hold">On Hold</option>
          </select>
        </div>
        <div class="form-group">
          <label>Remarks</label>
          <textarea name="remarks" id="remarks" rows="4" placeholder="Enter remarks or feedback..."></textarea>
        </div>
        <div style="display: flex; gap: 1rem;">
          <button type="submit" name="update_status" class="btn btn-success">
            <i class="fas fa-save"></i> Update Status
          </button>
          <button type="button" class="btn btn-warning" onclick="closeModal()">
            <i class="fas fa-times"></i> Close
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('yearFilter').addEventListener('change', applyFilters);
    document.getElementById('semesterFilter').addEventListener('change', applyFilters);
    document.getElementById('typeFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    
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
      const semester = document.getElementById('semesterFilter').value;
      const type = document.getElementById('typeFilter').value;
      const status = document.getElementById('statusFilter').value;
      window.location.href = `scholarships.php?search=${encodeURIComponent(search)}&academic_year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}&scholarship_type=${encodeURIComponent(type)}&status=${encodeURIComponent(status)}`;
    }
    
    function viewApplication(app) {
      const deansStatus = app.deans_status ? `<span class="badge badge-${app.deans_status.toLowerCase()}">${app.deans_status}</span>` : 'Not on Dean\'s List';
      
      document.getElementById('appDetails').innerHTML = `
        <div class="info-row">
          <span class="info-label">Student Number:</span>
          <span class="info-value">${escapeHtml(app.student_number)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Name:</span>
          <span class="info-value">${escapeHtml(app.lastName)}, ${escapeHtml(app.firstName)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Email:</span>
          <span class="info-value">${escapeHtml(app.email)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Year Level:</span>
          <span class="info-value">${escapeHtml(app.year_level)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Course:</span>
          <span class="info-value">${escapeHtml(app.course)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Academic Year:</span>
          <span class="info-value">${escapeHtml(app.academic_year)} ${escapeHtml(app.semester)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Scholarship Type:</span>
          <span class="info-value">${escapeHtml(app.scholarship_type)}</span>
        </div>
        <div class="info-row">
          <span class="info-label">GPA:</span>
          <span class="info-value">${app.gpa ? parseFloat(app.gpa).toFixed(2) : 'N/A'}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Dean's List Status:</span>
          <span class="info-value">${deansStatus}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Application Date:</span>
          <span class="info-value">${new Date(app.application_date).toLocaleDateString()}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Current Status:</span>
          <span class="info-value"><span class="badge badge-${app.status.replace(' ', '').toLowerCase()}">${escapeHtml(app.status)}</span></span>
        </div>
        <div class="info-row">
          <span class="info-label">Reviewed By:</span>
          <span class="info-value">${app.reviewer_email ? escapeHtml(app.reviewer_email) : 'Not reviewed yet'}</span>
        </div>
        ${app.remarks ? `<div class="info-row">
          <span class="info-label">Previous Remarks:</span>
          <span class="info-value">${escapeHtml(app.remarks)}</span>
        </div>` : ''}
      `;
      
      document.getElementById('app_id').value = app.app_id;
      document.getElementById('status_select').value = app.status;
      document.getElementById('remarks').value = app.remarks || '';
      document.getElementById('appModal').classList.add('active');
    }
    
    function closeModal() {
      document.getElementById('appModal').classList.remove('active');
    }
    
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.opacity = '0';
          alert.style.transform = 'translateY(-20px)';
          setTimeout(() => alert.remove(), 300);
        }, 5000);
      });
    });
    
    // Close modal when clicking outside
    document.getElementById('appModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  </script>
</body>
</html>
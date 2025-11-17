<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Dean') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle bulk approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_approve'])) {
    $selected_entries = $_POST['selected_entries'] ?? [];
    
    if (count($selected_entries) > 0) {
        $approved = 0;
        foreach ($selected_entries as $list_id) {
            $sql = "UPDATE dean_list SET status='Verified', verified_by=?, verified_date=NOW() WHERE list_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $list_id);
            if ($stmt->execute()) {
                $approved++;
            }
        }
        $successMessage = "$approved Dean's List entries approved successfully!";
    } else {
        $errorMessage = "Please select at least one entry to approve.";
    }
}

// Handle bulk rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_reject'])) {
    $selected_entries = $_POST['selected_entries'] ?? [];
    $bulk_remarks = $_POST['bulk_remarks'] ?? '';
    
    if (count($selected_entries) > 0) {
        $rejected = 0;
        foreach ($selected_entries as $list_id) {
            $sql = "UPDATE dean_list SET status='Rejected', remarks=?, verified_by=?, verified_date=NOW() WHERE list_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $bulk_remarks, $_SESSION['user_id'], $list_id);
            if ($stmt->execute()) {
                $rejected++;
            }
        }
        $successMessage = "$rejected Dean's List entries rejected.";
    } else {
        $errorMessage = "Please select at least one entry to reject.";
    }
}

// Handle individual approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_deans_list'])) {
    $list_id = $_POST['list_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $remarks = $_POST['remarks'] ?? '';
    
    $status = ($action == 'approve') ? 'Verified' : 'Rejected';
    
    $sql = "UPDATE dean_list SET status=?, remarks=?, verified_by=?, verified_date=NOW() WHERE list_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $list_id);
    
    if ($stmt->execute()) {
        $successMessage = "Dean's List entry " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
    } else {
        $errorMessage = "Error updating status.";
    }
}

// Get filters
$academic_year = $_GET['academic_year'] ?? '2025-2026';
$semester = $_GET['semester'] ?? '1st Semester';
$status_filter = $_GET['status'] ?? 'Under Review';

// Get Dean's List entries
$sql = "SELECT dl.*, p.firstName, p.lastName, p.student_number, p.course, p.year_level,
        e.status as enrollment_status,
        v.firstName as verified_by_name
        FROM dean_list dl
        INNER JOIN profile p ON dl.student_id = p.user_id
        LEFT JOIN student_enrollment e ON dl.student_id = e.student_id 
            AND e.academic_year = dl.academic_year 
            AND e.semester = dl.semester
        LEFT JOIN users v ON dl.verified_by = v.user_id
        WHERE dl.academic_year = '$academic_year' 
        AND dl.semester = '$semester'";

if ($status_filter) $sql .= " AND dl.status = '$status_filter'";

$sql .= " ORDER BY dl.gpa DESC, p.lastName, p.firstName";
$entries = $conn->query($sql);

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='Under Review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status='Verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected,
    AVG(gpa) as avg_gpa,
    MAX(gpa) as max_gpa,
    MIN(gpa) as min_gpa
    FROM dean_list
    WHERE academic_year = '$academic_year' AND semester = '$semester'";
$stats = $conn->query($stats_sql)->fetch_assoc();

$user_name = $_SESSION['user_name'] ?? 'Dean';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Dean's List Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      min-height: 100vh;
      color: #2d3748;
    }
    .header {
      background: white;
      padding: 1rem 2rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header h1 {
      font-size: 1.5rem;
      color: #1e40af;
      font-weight: 700;
    }
    .user-info {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
    }
    .container {
      display: flex;
      min-height: calc(100vh - 72px);
    }
    .sidebar {
      width: 260px;
      background: white;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      padding: 1.5rem;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.875rem 1rem;
      margin-bottom: 0.5rem;
      border-radius: 10px;
      color: #4a5568;
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
    }
    .nav-item:hover {
      background: #f7fafc;
      color: #1e40af;
      transform: translateX(5px);
    }
    .nav-item.active {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      color: white;
    }
    .main-content {
      flex: 1;
      padding: 2rem;
      overflow-y: auto;
    }
    .page-title {
      color: white;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 2rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .stat-card:hover {
      transform: translateY(-5px);
    }
    .stat-card h3 {
      font-size: 0.875rem;
      color: #718096;
      text-transform: uppercase;
      margin-bottom: 0.75rem;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .stat-label {
      font-size: 0.75rem;
      color: #a0aec0;
      margin-top: 0.5rem;
    }
    .action-bar {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .filter-group {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }
    select {
      padding: 0.75rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
      background: white;
      cursor: pointer;
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
    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .btn-primary {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      color: white;
    }
    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(30, 64, 175, 0.4);
    }
    .btn-success {
      background: #10b981;
      color: white;
    }
    .btn-success:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
    }
    .btn-danger {
      background: #ef4444;
      color: white;
    }
    .btn-danger:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
    }
    .btn-secondary {
      background: #6b7280;
      color: white;
    }
    .btn-secondary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
    }
    .content-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .card-header {
      padding: 1.5rem;
      border-bottom: 2px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .card-header h2 {
      font-size: 1.25rem;
      color: #2d3748;
      font-weight: 700;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th {
      background: #f7fafc;
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      color: #4a5568;
      font-size: 0.875rem;
      text-transform: uppercase;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    td {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
      color: #4a5568;
      font-size: 0.875rem;
    }
    tr:hover {
      background: #f7fafc;
    }
    .badge {
      display: inline-block;
      padding: 0.25rem 0.625rem;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }
    .badge-underreview {
      background: #dbeafe;
      color: #1e40af;
    }
    .badge-verified {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-rejected {
      background: #fee2e2;
      color: #991b1b;
    }
    .badge-enrolled {
      background: #d1fae5;
      color: #065f46;
    }
    .checkbox-cell {
      text-align: center;
    }
    .checkbox-cell input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
    }
    .action-btns {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
    }
    .btn-sm {
      padding: 0.375rem 0.75rem;
      font-size: 0.75rem;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    .workflow-info {
      background: #e0f2fe;
      border-left: 4px solid #0284c7;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }
    .workflow-info h3 {
      color: #0c4a6e;
      margin-bottom: 0.5rem;
      font-size: 1rem;
    }
    .workflow-info p {
      color: #075985;
      font-size: 0.875rem;
      line-height: 1.6;
    }
    .bulk-actions-bar {
      background: #f7fafc;
      padding: 1rem 1.5rem;
      border-bottom: 2px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    .selected-count {
      font-weight: 600;
      color: #1e40af;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    .modal.active {
      display: flex;
    }
    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .modal-header {
      margin-bottom: 1.5rem;
    }
    .modal-header h3 {
      font-size: 1.25rem;
      color: #2d3748;
    }
    .form-group {
      margin-bottom: 1.5rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: #4a5568;
    }
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-family: inherit;
      resize: vertical;
      min-height: 100px;
    }
    .modal-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
    }
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: #a0aec0;
    }
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      display: block;
      opacity: 0.5;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-graduation-cap"></i> FEU Roosevelt DMS - Dean/Admin</h1>
    <div class="user-info">
      <div class="user-avatar"><?= $user_initial ?></div>
      <span><?= htmlspecialchars($user_name) ?></span>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
  
  <div class="container">
    <aside class="sidebar">
      <nav>
        <a href="dean_dashboard.php" class="nav-item">
          <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="deans_list.php" class="nav-item active">
          <i class="fas fa-star"></i> Dean's List
        </a>
        <a href="scholarship_approval.php" class="nav-item">
          <i class="fas fa-award"></i> Scholarships
        </a>
        <a href="dean_reports.php" class="nav-item">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="dean_announcements.php" class="nav-item">
          <i class="fas fa-bullhorn"></i> Announcements
        </a>
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-star"></i> Dean's List Management</h1>
      
      <div class="workflow-info">
        <h3><i class="fas fa-info-circle"></i> Approval Workflow</h3>
        <p>
          Review and approve Dean's List nominations submitted by the Registrar. 
          You can approve/reject individual entries or use bulk actions for multiple entries at once.
          Approved students will be eligible for certificate generation.
        </p>
      </div>
      
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
          <h3>Total Entries</h3>
          <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending Review</h3>
          <div class="stat-value"><?= number_format($stats['pending'] + $stats['under_review']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Verified</h3>
          <div class="stat-value"><?= number_format($stats['verified']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Rejected</h3>
          <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Average GPA</h3>
          <div class="stat-value"><?= number_format($stats['avg_gpa'], 2) ?></div>
          <div class="stat-label">Range: <?= number_format($stats['min_gpa'], 2) ?> - <?= number_format($stats['max_gpa'], 2) ?></div>
        </div>
      </div>
      
      <div class="action-bar">
        <div class="filter-group">
          <select id="academicYearFilter" onchange="applyFilters()">
            <option value="2024-2025" <?= $academic_year == '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
            <option value="2025-2026" <?= $academic_year == '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
          </select>
          <select id="semesterFilter" onchange="applyFilters()">
            <option value="1st Semester" <?= $semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
            <option value="2nd Semester" <?= $semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
            <option value="Summer" <?= $semester == 'Summer' ? 'selected' : '' ?>>Summer</option>
          </select>
          <select id="statusFilter" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Under Review" <?= $status_filter == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
            <option value="Verified" <?= $status_filter == 'Verified' ? 'selected' : '' ?>>Verified</option>
            <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button class="btn btn-secondary" onclick="exportList()">
            <i class="fas fa-download"></i> Export
          </button>
          <button class="btn btn-primary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>
      
      <div class="content-card">
        <div class="card-header">
          <h2>Dean's List Entries</h2>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
            <span style="font-size: 0.875rem; font-weight: 500;">Select All</span>
          </label>
        </div>
        
        <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
          <div>
            <span class="selected-count"><span id="selectedCount">0</span> selected</span>
          </div>
          <div style="display: flex; gap: 0.75rem;">
            <button class="btn btn-success btn-sm" onclick="bulkApprove()">
              <i class="fas fa-check"></i> Approve Selected
            </button>
            <button class="btn btn-danger btn-sm" onclick="openBulkRejectModal()">
              <i class="fas fa-times"></i> Reject Selected
            </button>
          </div>
        </div>
        
        <form method="POST" id="bulkForm">
          <table>
            <thead>
              <tr>
                <th class="checkbox-cell">
                  <input type="checkbox" id="selectAllTable" onchange="toggleSelectAll(this)">
                </th>
                <th>Student #</th>
                <th>Name</th>
                <th>Year</th>
                <th>Course</th>
                <th>GPA</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if($entries && $entries->num_rows > 0): ?>
                <?php while($entry = $entries->fetch_assoc()): ?>
                <tr>
                  <td class="checkbox-cell">
                    <?php if($entry['status'] == 'Pending' || $entry['status'] == 'Under Review'): ?>
                    <input type="checkbox" name="selected_entries[]" value="<?= $entry['list_id'] ?>" class="entry-checkbox">
                    <?php endif; ?>
                  </td>
                  <td><strong><?= htmlspecialchars($entry['student_number']) ?></strong></td>
                  <td><?= htmlspecialchars($entry['lastName'] . ', ' . $entry['firstName']) ?></td>
                  <td><?= htmlspecialchars($entry['year_level']) ?></td>
                  <td><?= htmlspecialchars(substr($entry['course'], 0, 25)) ?></td>
                  <td><strong style="color: #059669;"><?= number_format($entry['gpa'], 2) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= strtolower(str_replace(' ', '', $entry['status'])) ?>">
                      <?= htmlspecialchars($entry['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if($entry['submitted_date']): ?>
                      <?= date('M d, Y', strtotime($entry['submitted_date'])) ?>
                    <?php else: ?>
                      <span style="color: #a0aec0;">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if($entry['status'] == 'Pending' || $entry['status'] == 'Under Review'): ?>
                    <div class="action-btns">
                      <button type="button" class="btn btn-success btn-sm" onclick="approveEntry(<?= $entry['list_id'] ?>)">
                        <i class="fas fa-check"></i> Approve
                      </button>
                      <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $entry['list_id'] ?>)">
                        <i class="fas fa-times"></i> Reject
                      </button>
                    </div>
                    <?php elseif($entry['status'] == 'Verified'): ?>
                      <span style="color: #10b981; font-size: 0.75rem;">
                        <i class="fas fa-check-circle"></i> Approved
                      </span>
                    <?php elseif($entry['status'] == 'Rejected'): ?>
                      <span style="color: #ef4444; font-size: 0.75rem;">
                        <i class="fas fa-times-circle"></i> Rejected
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9">
                    <div class="empty-state">
                      <i class="fas fa-inbox"></i>
                      <p>No Dean's List entries found for the selected filters.</p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </form>
      </div>
    </main>
  </div>
  
  <!-- Reject Modal -->
  <div id="rejectModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Entry</h3>
      </div>
      <form method="POST" id="rejectForm">
        <input type="hidden" name="approve_deans_list" value="1">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="list_id" id="rejectListId">
        
        <div class="form-group">
          <label>Reason for Rejection</label>
          <textarea name="remarks" placeholder="Enter reason for rejection..." required></textarea>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times"></i> Reject
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Bulk Reject Modal -->
  <div id="bulkRejectModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-times-circle" style="color: #ef4444;"></i> Bulk Reject Entries</h3>
      </div>
      <form method="POST" id="bulkRejectForm">
        <input type="hidden" name="bulk_reject" value="1">
        
        <div class="form-group">
          <label>Reason for Rejection (applies to all selected entries)</label>
          <textarea name="bulk_remarks" placeholder="Enter reason for rejection..." required></textarea>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeBulkRejectModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times"></i> Reject All
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    // Update selected count and show/hide bulk actions bar
    function updateSelectedCount() {
      const checkboxes = document.querySelectorAll('.entry-checkbox:checked');
      const count = checkboxes.length;
      document.getElementById('selectedCount').textContent = count;
      document.getElementById('bulkActionsBar').style.display = count > 0 ? 'flex' : 'none';
    }
    
    // Add event listeners to all checkboxes
    document.querySelectorAll('.entry-checkbox').forEach(cb => {
      cb.addEventListener('change', updateSelectedCount);
    });
    
    function applyFilters() {
      const year = document.getElementById('academicYearFilter').value;
      const sem = document.getElementById('semesterFilter').value;
      const status = document.getElementById('statusFilter').value;
      window.location.href = `deans_list.php?academic_year=${year}&semester=${sem}&status=${status}`;
    }
    
    function toggleSelectAll(checkbox) {
      const checkboxes = document.querySelectorAll('.entry-checkbox');
      checkboxes.forEach(cb => cb.checked = checkbox.checked);
      updateSelectedCount();
    }
    
    function approveEntry(listId) {
      if (confirm('Approve this Dean\'s List entry?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="approve_deans_list" value="1">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="list_id" value="${listId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
    
    function openRejectModal(listId) {
      document.getElementById('rejectListId').value = listId;
      document.getElementById('rejectModal').classList.add('active');
    }
    
    function closeRejectModal() {
      document.getElementById('rejectModal').classList.remove('active');
      document.getElementById('rejectForm').reset();
    }
    
    function bulkApprove() {
      const selected = document.querySelectorAll('.entry-checkbox:checked');
      
      if (selected.length === 0) {
        alert('Please select at least one entry to approve.');
        return;
      }
      
      if (confirm(`Approve ${selected.length} Dean's List entries?`)) {
        const form = document.getElementById('bulkForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_approve';
        input.value = '1';
        form.appendChild(input);
        form.submit();
      }
    }
    
    function openBulkRejectModal() {
      const selected = document.querySelectorAll('.entry-checkbox:checked');
      
      if (selected.length === 0) {
        alert('Please select at least one entry to reject.');
        return;
      }
      
      // Copy selected checkboxes to bulk reject form
      const bulkForm = document.getElementById('bulkRejectForm');
      document.querySelectorAll('#bulkRejectForm input[name="selected_entries[]"]').forEach(el => el.remove());
      
      selected.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_entries[]';
        input.value = cb.value;
        bulkForm.appendChild(input);
      });
      
      document.getElementById('bulkRejectModal').classList.add('active');
    }
    
    function closeBulkRejectModal() {
      document.getElementById('bulkRejectModal').classList.remove('active');
      document.getElementById('bulkRejectForm').reset();
    }
    
    function exportList() {
      const year = document.getElementById('academicYearFilter').value;
      const sem = document.getElementById('semesterFilter').value;
      const status = document.getElementById('statusFilter').value;
      
      // Create export URL
      let exportUrl = `export_deans_list.php?academic_year=${year}&semester=${sem}`;
      if (status) exportUrl += `&status=${status}`;
      
      // Open in new tab or trigger download
      window.open(exportUrl, '_blank');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
      }
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Escape to close modals
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
          modal.classList.remove('active');
        });
      }
    });
    
    // Sync both select all checkboxes
    document.getElementById('selectAll').addEventListener('change', function() {
      document.getElementById('selectAllTable').checked = this.checked;
      toggleSelectAll(this);
    });
    
    document.getElementById('selectAllTable').addEventListener('change', function() {
      document.getElementById('selectAll').checked = this.checked;
      toggleSelectAll(this);
    });
  </script>
</body>
</html>
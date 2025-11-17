<?php
session_start();
include('include/db.php');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Dean', 'Registrar'])) {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle Add to Dean's List
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_deans_list'])) {
    $student_id = $_POST['student_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $gpa = $_POST['gpa'];
    $year_level = $_POST['year_level'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';
    
    $list_id = $_POST['list_id'] ?? null;
    
    try {
        if ($list_id) {
            // Update existing
            $sql = "UPDATE dean_list SET academic_year=?, semester=?, gpa=?, year_level=?, status=?, remarks=?, verified_by=?, verified_date=NOW() WHERE list_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssiii", $academic_year, $semester, $gpa, $year_level, $status, $remarks, $_SESSION['user_id'], $list_id);
        } else {
            // Insert new
            $sql = "INSERT INTO dean_list (student_id, academic_year, semester, gpa, year_level, status, remarks, verified_by, verified_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsssi", $student_id, $academic_year, $semester, $gpa, $year_level, $status, $remarks, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $successMessage = $list_id ? "Dean's list entry updated successfully!" : "Student added to Dean's list successfully!";
            
            // Log audit trail
            $action = $list_id ? 'Updated Dean\'s List Entry' : 'Added to Dean\'s List';
            $audit = "INSERT INTO audit_trail (user_id, action, table_name, record_id, timestamp) VALUES (?, ?, 'dean_list', ?, NOW())";
            $audit_stmt = $conn->prepare($audit);
            $record_id = $list_id ?? $conn->insert_id;
            $audit_stmt->bind_param("isi", $_SESSION['user_id'], $action, $record_id);
            $audit_stmt->execute();
        } else {
            $errorMessage = "Error saving Dean's list entry.";
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_entry'])) {
    $list_id = $_POST['list_id'];
    $sql = "DELETE FROM dean_list WHERE list_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $list_id);
    
    if ($stmt->execute()) {
        $successMessage = "Entry removed successfully!";
    } else {
        $errorMessage = "Error removing entry.";
    }
}

// Get filters
$year_filter = $_GET['academic_year'] ?? '';
$semester_filter = $_GET['semester'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with proper prepared statements
$sql = "SELECT dl.*, p.firstName, p.lastName, p.student_number, p.course, u.email,
        v.email as verifier_email
        FROM dean_list dl
        INNER JOIN user u ON dl.student_id = u.user_id
        INNER JOIN profile p ON u.user_id = p.user_id
        LEFT JOIN user v ON dl.verified_by = v.user_id
        WHERE 1=1";

$params = [];
$types = "";

if ($year_filter) {
    $sql .= " AND dl.academic_year = ?";
    $params[] = $year_filter;
    $types .= "s";
}
if ($semester_filter) {
    $sql .= " AND dl.semester = ?";
    $params[] = $semester_filter;
    $types .= "s";
}
if ($status_filter) {
    $sql .= " AND dl.status = ?";
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

$sql .= " ORDER BY dl.academic_year DESC, dl.semester DESC, dl.gpa DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$deans_list = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    AVG(gpa) as avg_gpa
    FROM dean_list";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get all students for dropdown
$students = $conn->query("SELECT u.user_id, p.student_number, p.firstName, p.lastName, p.year_level 
                          FROM user u 
                          INNER JOIN profile p ON u.user_id = p.user_id 
                          WHERE u.role = 'Student' 
                          ORDER BY p.lastName, p.firstName");

$user_name = $_SESSION['user_name'] ?? $_SESSION['role'];
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
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
    
    .btn-danger {
      background: #ef4444;
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
    
    .badge-verified {
      background: #d1fae5;
      color: #065f46;
    }
    
    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }
    
    .badge-rejected {
      background: #fee2e2;
      color: #991b1b;
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
      max-width: 600px;
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
      padding: 0.75rem;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.875rem;
      transition: border-color 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-green);
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
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
      
      .form-row {
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
        <a href="deans_list.php" class="nav-item active">
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
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-star"></i> Dean's List Management</h1>
      
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
          <h3>Verified</h3>
          <div class="stat-value"><?= number_format($stats['verified']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending</h3>
          <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Average GPA</h3>
          <div class="stat-value"><?= number_format($stats['avg_gpa'], 2) ?></div>
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
        <select id="statusFilter">
          <option value="">All Status</option>
          <option value="Verified" <?= $status_filter == 'Verified' ? 'selected' : '' ?>>Verified</option>
          <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
          <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button class="btn btn-primary" onclick="openModal()">
          <i class="fas fa-plus"></i> Add to Dean's List
        </button>
      </div>
      
      <div class="content-card">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Student Number</th>
                <th>Name</th>
                <th>Academic Year</th>
                <th>Semester</th>
                <th>Year Level</th>
                <th>GPA</th>
                <th>Status</th>
                <th>Verified By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if($deans_list && $deans_list->num_rows > 0): ?>
                <?php while($entry = $deans_list->fetch_assoc()): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($entry['student_number']) ?></strong></td>
                  <td><?= htmlspecialchars($entry['lastName'] . ', ' . $entry['firstName']) ?></td>
                  <td><?= htmlspecialchars($entry['academic_year']) ?></td>
                  <td><?= htmlspecialchars($entry['semester']) ?></td>
                  <td><?= htmlspecialchars($entry['year_level']) ?></td>
                  <td><strong><?= number_format($entry['gpa'], 2) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= strtolower($entry['status']) ?>">
                      <?= htmlspecialchars($entry['status']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($entry['verifier_email'] ?? 'N/A') ?></td>
                  <td>
                    <button class="btn btn-warning btn-sm" onclick='editEntry(<?= json_encode($entry) ?>)'>
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteEntry(<?= $entry['list_id'] ?>, '<?= htmlspecialchars($entry['firstName']) ?>')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No entries found</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Modal -->
  <div id="deansModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Add to Dean's List</h2>
        <button class="close-btn" onclick="closeModal()">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="list_id" id="list_id">
        
        <div class="form-group" id="studentField">
          <label>Student *</label>
          <select name="student_id" id="student_id" required>
            <option value="">Select Student</option>
            <?php if($students): while($student = $students->fetch_assoc()): ?>
            <option value="<?= $student['user_id'] ?>" data-year="<?= htmlspecialchars($student['year_level']) ?>">
              <?= htmlspecialchars($student['student_number'] . ' - ' . $student['lastName'] . ', ' . $student['firstName']) ?>
            </option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Academic Year *</label>
            <select name="academic_year" id="academic_year" required>
              <option value="">Select Year</option>
              <option value="2024-2025">2024-2025</option>
              <option value="2025-2026">2025-2026</option>
            </select>
          </div>
          <div class="form-group">
            <label>Semester *</label>
            <select name="semester" id="semester" required>
              <option value="">Select Semester</option>
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
            </select>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Year Level *</label>
            <select name="year_level" id="year_level" required>
              <option value="">Select Year Level</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
            </select>
          </div>
          <div class="form-group">
            <label>GPA *</label>
            <input type="number" name="gpa" id="gpa" step="0.01" min="1.00" max="4.00" required>
          </div>
        </div>
        
        <div class="form-group">
          <label>Status *</label>
          <select name="status" id="status" required>
            <option value="Pending">Pending</option>
            <option value="Verified">Verified</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Remarks</label>
          <textarea name="remarks" id="remarks" rows="3"></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
          <button type="submit" name="add_deans_list" class="btn btn-success">
            <i class="fas fa-save"></i> Save Entry
          </button>
          <button type="button" class="btn btn-danger" onclick="closeModal()">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('yearFilter').addEventListener('change', applyFilters);
    document.getElementById('semesterFilter').addEventListener('change', applyFilters);
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
      const status = document.getElementById('statusFilter').value;
      window.location.href = `deans_list.php?search=${encodeURIComponent(search)}&academic_year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}&status=${encodeURIComponent(status)}`;
    }
    
    function openModal() {
      document.getElementById('modalTitle').textContent = 'Add to Dean\'s List';
      document.querySelector('form').reset();
      document.getElementById('list_id').value = '';
      document.getElementById('studentField').style.display = 'block';
      document.getElementById('deansModal').classList.add('active');
    }
    
    function closeModal() {
      document.getElementById('deansModal').classList.remove('active');
    }
    
    function editEntry(entry) {
      document.getElementById('modalTitle').textContent = 'Edit Dean\'s List Entry';
      document.getElementById('list_id').value = entry.list_id;
      document.getElementById('studentField').style.display = 'none';
      document.getElementById('academic_year').value = entry.academic_year;
      document.getElementById('semester').value = entry.semester;
      document.getElementById('year_level').value = entry.year_level;
      document.getElementById('gpa').value = entry.gpa;
      document.getElementById('status').value = entry.status;
      document.getElementById('remarks').value = entry.remarks || '';
      document.getElementById('deansModal').classList.add('active');
    }
    
    function deleteEntry(listId, name) {
      if(confirm(`Remove ${name} from Dean's List?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="list_id" value="${listId}">
          <input type="hidden" name="delete_entry" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
    
    // Auto-fill year level when student is selected
    const studentSelect = document.getElementById('student_id');
    if (studentSelect) {
      studentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const yearLevel = selectedOption.getAttribute('data-year');
        if(yearLevel) {
          document.getElementById('year_level').value = yearLevel;
        }
      });
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
    document.getElementById('deansModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  </script>
</body>
</html>
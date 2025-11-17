<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $user_id = $_POST['user_id'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $middleName = $_POST['middleName'] ?? '';
    $contactNumber = $_POST['contactNumber'];
    $address = $_POST['address'];
    
    $sql = "UPDATE profile SET firstName=?, lastName=?, middleName=?, contactNumber=?, address=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $firstName, $lastName, $middleName, $contactNumber, $address, $user_id);
    
    if ($stmt->execute()) {
        $successMessage = "Student record updated successfully!";
    } else {
        $errorMessage = "Error updating student record.";
    }
}

// Get filters
$year_level_filter = $_GET['year_level'] ?? '';
$course_filter = $_GET['course'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT u.user_id, u.email, u.status, u.created_at, u.last_login, p.*,
        (SELECT COUNT(*) FROM student_enrollment WHERE student_id=u.user_id AND status='Enrolled') as enrollment_count,
        (SELECT COUNT(*) FROM dean_list WHERE student_id=u.user_id AND status='Verified') as deans_list_count,
        (SELECT COUNT(*) FROM scholarship_application WHERE student_id=u.user_id) as scholarship_count
        FROM user u
        INNER JOIN profile p ON u.user_id = p.user_id
        WHERE u.role = 'Student'";

if ($search) $sql .= " AND (p.firstName LIKE '%$search%' OR p.lastName LIKE '%$search%' OR p.student_number LIKE '%$search%' OR u.email LIKE '%$search%')";
if ($year_level_filter) $sql .= " AND p.year_level = '$year_level_filter'";
if ($course_filter) $sql .= " AND p.course LIKE '%$course_filter%'";
if ($status_filter) $sql .= " AND u.status = '$status_filter'";

$sql .= " ORDER BY p.lastName, p.firstName";
$students = $conn->query($sql);

// Get courses for filter
$courses = $conn->query("SELECT DISTINCT course FROM profile WHERE course IS NOT NULL ORDER BY course");

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN u.status='Active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN p.year_level='1st Year' THEN 1 ELSE 0 END) as first_year,
    SUM(CASE WHEN p.year_level='4th Year' THEN 1 ELSE 0 END) as fourth_year
    FROM user u
    INNER JOIN profile p ON u.user_id = p.user_id
    WHERE u.role='Student'";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Student Records</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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
      color: #059669;
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
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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
      color: #059669;
      transform: translateX(5px);
    }
    .nav-item.active {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .toolbar {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
    }
    .search-box input:focus {
      outline: none;
      border-color: #059669;
    }
    .search-box i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #a0aec0;
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
    .btn-primary {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: white;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
    }
    .btn-info {
      background: #3b82f6;
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
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      overflow: hidden;
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
      margin-right: 0.25rem;
    }
    .badge-active {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-inactive {
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
      z-index: 1000;
    }
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      max-width: 900px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e2e8f0;
    }
    .modal-header h2 {
      font-size: 1.5rem;
      color: #2d3748;
    }
    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #a0aec0;
    }
    .student-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }
    .detail-section {
      background: #f7fafc;
      padding: 1.5rem;
      border-radius: 10px;
    }
    .detail-section h3 {
      font-size: 1rem;
      color: #059669;
      margin-bottom: 1rem;
      font-weight: 700;
    }
    .detail-row {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      border-bottom: 1px solid #e2e8f0;
    }
    .detail-row:last-child {
      border-bottom: none;
    }
    .detail-label {
      font-weight: 600;
      color: #718096;
      font-size: 0.875rem;
    }
    .detail-value {
      color: #2d3748;
      font-weight: 500;
      font-size: 0.875rem;
    }
    .form-group {
      margin-bottom: 1.25rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: #4a5568;
      font-size: 0.875rem;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #059669;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1rem;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-graduation-cap"></i> FEU Roosevelt DMS - Registrar</h1>
    <div class="user-info">
      <div class="user-avatar">R</div>
      <span>Registrar</span>
      <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
  
   <div class="container">
    <aside class="sidebar">
      <nav>
        <a href="registrar_dashboard.php" class="nav-item ">
          <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="registrar_students.php" class="nav-item active">
          <i class="fas fa-user-graduate"></i> Student Records
        </a>

        <a href="registrar_deans_list.php" class="nav-item">
          <i class="fas fa-star"></i> Dean's List Prep
        </a>
        <a href="registrar_transcripts.php" class="nav-item">
          <i class="fas fa-scroll"></i> TOR & Certificates
        </a>
        <a href="registrar_verification.php" class="nav-item">
          <i class="fas fa-check-circle"></i> Scholarship Verify
        </a>
        <a href="registrar_reports.php" class="nav-item">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="registrar_documents.php" class="nav-item">
          <i class="fas fa-folder-open"></i> Documents
        </a>
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-user-graduate"></i> Student Records Management</h1>
      
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
          <h3>Total Students</h3>
          <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Active Students</h3>
          <div class="stat-value"><?= number_format($stats['active']) ?></div>
        </div>
        <div class="stat-card">
          <h3>First Year</h3>
          <div class="stat-value"><?= number_format($stats['first_year']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Fourth Year</h3>
          <div class="stat-value"><?= number_format($stats['fourth_year']) ?></div>
        </div>
      </div>
      
      <div class="toolbar">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select id="yearLevelFilter">
          <option value="">All Year Levels</option>
          <option value="1st Year" <?= $year_level_filter == '1st Year' ? 'selected' : '' ?>>1st Year</option>
          <option value="2nd Year" <?= $year_level_filter == '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
          <option value="3rd Year" <?= $year_level_filter == '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
          <option value="4th Year" <?= $year_level_filter == '4th Year' ? 'selected' : '' ?>>4th Year</option>
        </select>
        <select id="courseFilter">
          <option value="">All Courses</option>
          <?php while($course = $courses->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($course['course']) ?>" <?= $course_filter == $course['course'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($course['course']) ?>
          </option>
          <?php endwhile; ?>
        </select>
        <select id="statusFilter">
          <option value="">All Status</option>
          <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="Suspended" <?= $status_filter == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      
      <div class="content-card">
        <table>
          <thead>
            <tr>
              <th>Student #</th>
              <th>Name</th>
              <th>Year Level</th>
              <th>Course</th>
              <th>Contact</th>
              <th>Enrollments</th>
              <th>Dean's List</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($student = $students->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($student['student_number']) ?></strong></td>
              <td><?= htmlspecialchars($student['lastName'] . ', ' . $student['firstName']) ?></td>
              <td><?= htmlspecialchars($student['year_level'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars(substr($student['course'] ?? 'N/A', 0, 25)) ?></td>
              <td><?= htmlspecialchars($student['contactNumber'] ?? 'N/A') ?></td>
              <td><span class="badge badge-active"><?= $student['enrollment_count'] ?></span></td>
              <td><span class="badge badge-active"><?= $student['deans_list_count'] ?></span></td>
              <td>
                <span class="badge badge-<?= strtolower($student['status']) ?>">
                  <?= htmlspecialchars($student['status']) ?>
                </span>
              </td>
              <td>
                <button class="btn btn-info btn-sm" onclick='viewStudent(<?= json_encode($student) ?>)'>
                  <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-warning btn-sm" onclick='editStudent(<?= json_encode($student) ?>)'>
                  <i class="fas fa-edit"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
  
  <!-- View Student Modal -->
  <div id="viewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-user-circle"></i> Student Details</h2>
        <button class="close-btn" onclick="closeViewModal()">×</button>
      </div>
      <div id="studentDetails"></div>
    </div>
  </div>
  
  <!-- Edit Student Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-edit"></i> Edit Student Information</h2>
        <button class="close-btn" onclick="closeEditModal()">×</button>
      </div>
      <form method="POST">
        <input type="hidden" name="user_id" id="edit_user_id">
        
        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="firstName" id="edit_firstName" required>
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middleName" id="edit_middleName">
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="lastName" id="edit_lastName" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Contact Number *</label>
            <input type="tel" name="contactNumber" id="edit_contactNumber" required>
          </div>
        </div>
        
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" id="edit_address" rows="3"></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
          <button type="submit" name="update_student" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
          <button type="button" class="btn btn-warning" onclick="closeEditModal()">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('yearLevelFilter').addEventListener('change', applyFilters);
    document.getElementById('courseFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    
    function applyFilters() {
      const search = document.getElementById('searchInput').value;
      const yearLevel = document.getElementById('yearLevelFilter').value;
      const course = document.getElementById('courseFilter').value;
      const status = document.getElementById('statusFilter').value;
      window.location.href = `registrar_students.php?search=${search}&year_level=${yearLevel}&course=${course}&status=${status}`;
    }
    
    function viewStudent(student) {
      document.getElementById('studentDetails').innerHTML = `
        <div class="student-details">
          <div class="detail-section">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <div class="detail-row">
              <span class="detail-label">Student Number:</span>
              <span class="detail-value">${student.student_number}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Full Name:</span>
              <span class="detail-value">${student.firstName} ${student.middleName || ''} ${student.lastName}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Email:</span>
              <span class="detail-value">${student.email}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Contact:</span>
              <span class="detail-value">${student.contactNumber || 'N/A'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Address:</span>
              <span class="detail-value">${student.address || 'N/A'}</span>
            </div>
          </div>
          
          <div class="detail-section">
            <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            <div class="detail-row">
              <span class="detail-label">Year Level:</span>
              <span class="detail-value">${student.year_level || 'N/A'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Course:</span>
              <span class="detail-value">${student.course || 'N/A'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Total Enrollments:</span>
              <span class="detail-value">${student.enrollment_count}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Dean's List Count:</span>
              <span class="detail-value">${student.deans_list_count}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Scholarships:</span>
              <span class="detail-value">${student.scholarship_count}</span>
            </div>
          </div>
          
          <div class="detail-section">
            <h3><i class="fas fa-info-circle"></i> Account Information</h3>
            <div class="detail-row">
              <span class="detail-label">Status:</span>
              <span class="detail-value"><span class="badge badge-${student.status.toLowerCase()}">${student.status}</span></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Created:</span>
              <span class="detail-value">${new Date(student.created_at).toLocaleDateString()}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Last Login:</span>
              <span class="detail-value">${student.last_login ? new Date(student.last_login).toLocaleDateString() : 'Never'}</span>
            </div>
          </div>
          
          <div class="detail-section">
            <h3><i class="fas fa-tasks"></i> Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
              <a href="registrar_enrollments.php?student=${student.user_id}" class="btn btn-primary btn-sm">
                <i class="fas fa-clipboard-list"></i> View Enrollments
              </a>
              <a href="registrar_transcripts.php?student=${student.user_id}" class="btn btn-info btn-sm">
                <i class="fas fa-scroll"></i> Generate Transcript
              </a>
              <button onclick='editStudent(${JSON.stringify(student)})' class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit Information
              </button>
            </div>
          </div>
        </div>
      `;
      document.getElementById('viewModal').classList.add('active');
    }
    
    function closeViewModal() {
      document.getElementById('viewModal').classList.remove('active');
    }
    
    function editStudent(student) {
      closeViewModal();
      closeViewModal();
      document.getElementById('edit_user_id').value = student.user_id;
      document.getElementById('edit_firstName').value = student.firstName;
      document.getElementById('edit_middleName').value = student.middleName || '';
      document.getElementById('edit_lastName').value = student.lastName;
      document.getElementById('edit_contactNumber').value = student.contactNumber || '';
      document.getElementById('edit_address').value = student.address || '';
      document.getElementById('editModal').classList.add('active');
    }
    
    function closeEditModal() {
      document.getElementById('editModal').classList.remove('active');
    }
  </script>
</body>
</html>
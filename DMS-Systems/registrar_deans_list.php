<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle generate eligibility list
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_eligibility'])) {
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $gpa_threshold = $_POST['gpa_threshold'];
    
    // Logic to generate eligibility list
    // This would typically:
    // 1. Query students with GPA >= threshold
    // 2. Check enrollment status
    // 3. Create pending Dean's List entries
    
    $successMessage = "Eligibility list generated successfully!";
}

// Handle submit to Admin/Dean
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_to_dean'])) {
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (count($selected_students) > 0) {
        // Update status to submitted for review
        foreach ($selected_students as $list_id) {
            $sql = "UPDATE dean_list SET status='Under Review', submitted_by=?, submitted_date=NOW() WHERE list_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $list_id);
            $stmt->execute();
        }
        $successMessage = count($selected_students) . " nominations submitted to Admin/Dean for review!";
    } else {
        $errorMessage = "Please select at least one student to submit.";
    }
}

// Get filters
$academic_year = $_GET['academic_year'] ?? '2025-2026';
$semester = $_GET['semester'] ?? '1st Semester';
$status_filter = $_GET['status'] ?? 'Pending';

// Get eligible students
$sql = "SELECT dl.*, p.firstName, p.lastName, p.student_number, p.course, p.year_level,
        e.status as enrollment_status
        FROM dean_list dl
        INNER JOIN profile p ON dl.student_id = p.user_id
        LEFT JOIN student_enrollment e ON dl.student_id = e.student_id 
            AND e.academic_year = dl.academic_year 
            AND e.semester = dl.semester
        WHERE dl.academic_year = '$academic_year' 
        AND dl.semester = '$semester'";

if ($status_filter) $sql .= " AND dl.status = '$status_filter'";

$sql .= " ORDER BY dl.gpa DESC, p.lastName, p.firstName";
$eligible_students = $conn->query($sql);

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total_eligible,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='Under Review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status='Verified' THEN 1 ELSE 0 END) as verified,
    AVG(gpa) as avg_gpa
    FROM dean_list
    WHERE academic_year = '$academic_year' AND semester = '$semester'";
$stats = $conn->query($stats_sql)->fetch_assoc();

// GPA threshold configuration
$gpa_threshold = 3.5; // This should be configurable

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Dean's List Preparation</title>
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
    }
    select {
      padding: 0.75rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
      background: white;
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
    .btn-success {
      background: #10b981;
      color: white;
    }
    .btn-warning {
      background: #f59e0b;
      color: white;
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
    .badge-review {
      background: #dbeafe;
      color: #1e40af;
    }
    .badge-verified {
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
        <a href="registrar_dashboard.php" class="nav-item active">
          <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="registrar_students.php" class="nav-item">
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
        <a href="registrar_bulk_import.php" class="nav-item">
          <i class="fas fa-file-import"></i> Bulk Import
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-star"></i> Dean's List Preparation</h1>
      
      <div class="workflow-info">
        <h3><i class="fas fa-info-circle"></i> Workflow</h3>
        <p>
          <strong>Step 1:</strong> Generate eligibility list based on GPA threshold →
          <strong>Step 2:</strong> Review and select qualified students →
          <strong>Step 3:</strong> Submit nominations to Admin/Dean for approval →
          <strong>Step 4:</strong> After approval, issue certificates
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
          <h3>Total Eligible</h3>
          <div class="stat-value"><?= number_format($stats['total_eligible']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending</h3>
          <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Under Review</h3>
          <div class="stat-value"><?= number_format($stats['under_review']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Verified</h3>
          <div class="stat-value"><?= number_format($stats['verified']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Average GPA</h3>
          <div class="stat-value"><?= number_format($stats['avg_gpa'], 2) ?></div>
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
          </select>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button class="btn btn-warning" onclick="openGenerateModal()">
            <i class="fas fa-magic"></i> Generate Eligibility
          </button>
          <button class="btn btn-success" onclick="submitSelected()">
            <i class="fas fa-paper-plane"></i> Submit to Dean
          </button>
        </div>
      </div>
      
      <div class="content-card">
        <div class="card-header">
          <h2>Eligible Students (GPA ≥ <?= $gpa_threshold ?>)</h2>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
            <span style="font-size: 0.875rem; font-weight: 500;">Select All</span>
          </label>
        </div>
        
        <form method="POST" id="submissionForm">
          <table>
            <thead>
              <tr>
                <th class="checkbox-cell">Select</th>
                <th>Student #</th>
                <th>Name</th>
                <th>Year Level</th>
                <th>Course</th>
                <th>GPA</th>
                <th>Status</th>
                <th>Enrollment</th>
              </tr>
            </thead>
            <tbody>
              <?php if($eligible_students && $eligible_students->num_rows > 0): ?>
                <?php while($student = $eligible_students->fetch_assoc()): ?>
                <tr>
                  <td class="checkbox-cell">
                    <?php if($student['status'] == 'Pending'): ?>
                    <input type="checkbox" name="selected_students[]" value="<?= $student['list_id'] ?>" class="student-checkbox">
                    <?php endif; ?>
                  </td>
                  <td><strong><?= htmlspecialchars($student['student_number']) ?></strong></td>
                  <td><?= htmlspecialchars($student['lastName'] . ', ' . $student['firstName']) ?></td>
                  <td><?= htmlspecialchars($student['year_level']) ?></td>
                  <td><?= htmlspecialchars(substr($student['course'], 0, 30)) ?></td>
                  <td><strong><?= number_format($student['gpa'], 2) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= strtolower(str_replace(' ', '', $student['status'])) ?>">
                      <?= htmlspecialchars($student['status']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-<?= strtolower($student['enrollment_status']) ?>">
                      <?= htmlspecialchars($student['enrollment_status'] ?? 'N/A') ?>
                    </span>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" style="text-align: center; padding: 2rem; color: #a0aec0;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    No eligible students found. Click "Generate Eligibility" to create the list.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </form>
      </div>
    </main>
  </div>
  
  <script>
    function applyFilters() {
      const year = document.getElementById('academicYearFilter').value;
      const sem = document.getElementById('semesterFilter').value;
      const status = document.getElementById('statusFilter').value;
      window.location.href = `registrar_deans_list.php?academic_year=${year}&semester=${sem}&status=${status}`;
    }
    
    function toggleSelectAll(checkbox) {
      const checkboxes = document.querySelectorAll('.student-checkbox');
      checkboxes.forEach(cb => cb.checked = checkbox.checked);
    }
    
    function submitSelected() {
      const form = document.getElementById('submissionForm');
      const selected = document.querySelectorAll('.student-checkbox:checked');
      
      if (selected.length === 0) {
        alert('Please select at least one student to submit.');
        return;
      }
      
      if (confirm(`Submit ${selected.length} student(s) to Admin/Dean for review?`)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'submit_to_dean';
        input.value = '1';
        form.appendChild(input);
        form.submit();
      }
    }
    
    function openGenerateModal() {
      const year = document.getElementById('academicYearFilter').value;
      const sem = document.getElementById('semesterFilter').value;
      
      if (confirm(`Generate Dean's List eligibility for ${year} ${sem}?\n\nThis will automatically identify students with GPA ≥ <?= $gpa_threshold ?>.`)) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="generate_eligibility" value="1">
          <input type="hidden" name="academic_year" value="${year}">
          <input type="hidden" name="semester" value="${sem}">
          <input type="hidden" name="gpa_threshold" value="<?= $gpa_threshold ?>">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>

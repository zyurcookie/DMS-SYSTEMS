<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

// Selected filters
$selected_year = $_GET['year'] ?? '2025-2026';
$selected_semester = $_GET['semester'] ?? '1st Semester';

// 1. Enrollment Statistics
$enrollment_stats = $conn->query("SELECT 
    year_level,
    COUNT(*) as count,
    SUM(CASE WHEN status='Enrolled' THEN 1 ELSE 0 END) as enrolled,
    SUM(CASE WHEN status='Withdrawn' THEN 1 ELSE 0 END) as withdrawn
    FROM student_enrollment
    WHERE academic_year='$selected_year' AND semester='$selected_semester'
    GROUP BY year_level");

// 2. Course Distribution
$course_stats = $conn->query("SELECT 
    course,
    COUNT(*) as count
    FROM student_enrollment
    WHERE academic_year='$selected_year' AND semester='$selected_semester' AND status='Enrolled'
    GROUP BY course
    ORDER BY count DESC
    LIMIT 10");

// 3. Gender Distribution
$gender_stats = $conn->query("SELECT 
    'Male' as gender,
    COUNT(*) as count
    FROM student_enrollment e
    INNER JOIN profile p ON e.student_id = p.user_id
    WHERE e.academic_year='$selected_year' AND e.semester='$selected_semester' AND e.status='Enrolled'
    UNION
    SELECT 'Female', COUNT(*)
    FROM student_enrollment e
    INNER JOIN profile p ON e.student_id = p.user_id
    WHERE e.academic_year='$selected_year' AND e.semester='$selected_semester' AND e.status='Enrolled'");

// 4. Dean's List Statistics
$deans_stats = $conn->query("SELECT 
    year_level,
    COUNT(*) as count,
    AVG(gpa) as avg_gpa
    FROM dean_list
    WHERE academic_year='$selected_year' AND semester='$selected_semester' AND status='Verified'
    GROUP BY year_level");

// NEW: Dean's List Application Statistics
$dl_query = $conn->query("SELECT 
    COUNT(*) as total_qualifiers,
    SUM(CASE WHEN status='Verified' THEN 1 ELSE 0 END) as approved_dl,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected_dl,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending_dl
    FROM dean_list
    WHERE academic_year='$selected_year' AND semester='$selected_semester'");

if ($dl_query) {
    $dl_stats = $dl_query->fetch_assoc();
} else {
    $dl_stats = [
        'total_qualifiers' => 0,
        'approved_dl' => 0,
        'rejected_dl' => 0,
        'pending_dl' => 0
    ];
}

// NEW: Scholarship Application Statistics
// Check if scholarship_applications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'scholarship_applications'");
$scholarship_table_exists = ($table_check && $table_check->num_rows > 0);

if ($scholarship_table_exists) {
    $scholarship_query = $conn->query("SELECT 
        COUNT(DISTINCT s.student_id) as total_applied,
        SUM(CASE WHEN s.status='Approved' THEN 1 ELSE 0 END) as approved_scholarship,
        SUM(CASE WHEN s.status='Rejected' THEN 1 ELSE 0 END) as rejected_scholarship,
        SUM(CASE WHEN s.status='Pending' THEN 1 ELSE 0 END) as pending_scholarship
        FROM scholarship_applications s
        INNER JOIN dean_list dl ON s.student_id = dl.student_id 
            AND dl.academic_year='$selected_year' AND dl.semester='$selected_semester'
            AND dl.status='Verified'
        WHERE s.academic_year='$selected_year' AND s.semester='$selected_semester'");
    
    if ($scholarship_query) {
        $scholarship_stats = $scholarship_query->fetch_assoc();
        if (!$scholarship_stats['total_applied']) {
            $scholarship_stats['total_applied'] = 0;
        }
    } else {
        $scholarship_stats = [
            'total_applied' => 0,
            'approved_scholarship' => 0,
            'rejected_scholarship' => 0,
            'pending_scholarship' => 0
        ];
    }
} else {
    // Table doesn't exist, use default values
    $scholarship_stats = [
        'total_applied' => 0,
        'approved_scholarship' => 0,
        'rejected_scholarship' => 0,
        'pending_scholarship' => 0
    ];
}

// 5. Overall Summary
$summary = $conn->query("SELECT 
    (SELECT COUNT(DISTINCT student_id) FROM student_enrollment WHERE academic_year='$selected_year' AND semester='$selected_semester' AND status='Enrolled') as total_enrolled,
    (SELECT COUNT(*) FROM dean_list WHERE academic_year='$selected_year' AND semester='$selected_semester' AND status='Verified') as total_deans,
    (SELECT AVG(gpa) FROM dean_list WHERE academic_year='$selected_year' AND semester='$selected_semester' AND status='Verified') as avg_gpa,
    (SELECT COUNT(*) FROM document WHERE status='Pending') as pending_docs")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Registrar Reports</title>
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
    .filter-bar {
      background: white;
      padding: 1.5rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
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
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .summary-card {
      background: white;
      padding: 1.75rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .summary-card h3 {
      font-size: 0.875rem;
      color: #718096;
      text-transform: uppercase;
      margin-bottom: 0.75rem;
    }
    .summary-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #059669;
    }
    .summary-label {
      font-size: 0.875rem;
      color: #a0aec0;
      margin-top: 0.5rem;
    }
    .report-section {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .report-section h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
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
      border-bottom: 2px solid #e2e8f0;
    }
    td {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
      color: #4a5568;
    }
    .chart-bar {
      display: flex;
      align-items: center;
      margin-bottom: 1rem;
    }
    .chart-label {
      width: 150px;
      font-size: 0.875rem;
      font-weight: 600;
      color: #4a5568;
    }
    .chart-bar-bg {
      flex: 1;
      height: 36px;
      background: #f7fafc;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }
    .chart-bar-fill {
      height: 100%;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding-right: 0.75rem;
      color: white;
      font-weight: 600;
      font-size: 0.875rem;
      transition: width 1s ease;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }
    .stat-box {
      background: #f7fafc;
      padding: 1.25rem;
      border-radius: 10px;
      border-left: 4px solid #059669;
    }
    .stat-box.success {
      border-left-color: #10b981;
    }
    .stat-box.danger {
      border-left-color: #ef4444;
    }
    .stat-box.warning {
      border-left-color: #f59e0b;
    }
    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: #2d3748;
    }
    .stat-label {
      font-size: 0.875rem;
      color: #718096;
      margin-top: 0.25rem;
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
        <a href="registrar_reports.php" class="nav-item active">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="registrar_documents.php" class="nav-item">
          <i class="fas fa-folder-open"></i> Documents
        </a>
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-chart-bar"></i> Comprehensive Reports</h1>
      
      <div class="filter-bar">
        <label style="font-weight: 600; color: #4a5568;">
          <i class="fas fa-filter"></i> Filter by:
        </label>
        <select id="yearSelect">
          <option value="2024-2025" <?= $selected_year == '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
          <option value="2025-2026" <?= $selected_year == '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
        </select>
        <select id="semesterSelect">
          <option value="1st Semester" <?= $selected_semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
          <option value="2nd Semester" <?= $selected_semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
          <option value="Summer" <?= $selected_semester == 'Summer' ? 'selected' : '' ?>>Summer</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">
          <i class="fas fa-sync"></i> Apply
        </button>
        <button class="btn btn-primary" onclick="window.print()">
          <i class="fas fa-print"></i> Print
        </button>
      </div>
      
      <div class="summary-grid">
        <div class="summary-card">
          <h3>Dean's List</h3>
          <div class="summary-value"><?= number_format($summary['total_deans']) ?></div>
          <div class="summary-label">Verified Students</div>
        </div>
        <div class="summary-card">
          <h3>Average GPA</h3>
          <div class="summary-value"><?= number_format($summary['avg_gpa'], 2) ?></div>
          <div class="summary-label">Dean's List Students</div>
        </div>
        <div class="summary-card">
          <h3>Pending Docs</h3>
          <div class="summary-value"><?= number_format($summary['pending_docs']) ?></div>
          <div class="summary-label">Awaiting Review</div>
        </div>
      </div>
      
      <!-- NEW: Dean's List Application Statistics -->
      <div class="report-section">
        <h2><i class="fas fa-award"></i> Dean's List Application Statistics</h2>
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-number"><?= number_format($dl_stats['total_qualifiers']) ?></div>
            <div class="stat-label">Total DL Qualifiers</div>
          </div>
          <div class="stat-box success">
            <div class="stat-number"><?= number_format($dl_stats['approved_dl']) ?></div>
            <div class="stat-label">Approved DL Applications</div>
          </div>
          <div class="stat-box danger">
            <div class="stat-number"><?= number_format($dl_stats['rejected_dl']) ?></div>
            <div class="stat-label">Rejected DL Applications</div>
          </div>
          <div class="stat-box warning">
            <div class="stat-number"><?= number_format($dl_stats['pending_dl']) ?></div>
            <div class="stat-label">Pending DL Applications</div>
          </div>
        </div>
      </div>
      
      <!-- NEW: Scholarship Application Statistics -->
      <div class="report-section">
        <h2><i class="fas fa-hand-holding-usd"></i> Scholarship Application Statistics</h2>
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-number"><?= number_format($scholarship_stats['total_applied']) ?></div>
            <div class="stat-label">DL Students Applied for Scholarship</div>
          </div>
          <div class="stat-box success">
            <div class="stat-number"><?= number_format($scholarship_stats['approved_scholarship']) ?></div>
            <div class="stat-label">Approved Scholarships</div>
          </div>
          <div class="stat-box danger">
            <div class="stat-number"><?= number_format($scholarship_stats['rejected_scholarship']) ?></div>
            <div class="stat-label">Rejected Scholarships</div>
          </div>
          <div class="stat-box warning">
            <div class="stat-number"><?= number_format($scholarship_stats['pending_scholarship']) ?></div>
            <div class="stat-label">Pending Scholarships</div>
          </div>
        </div>
      </div>
      
      <div class="report-section">
        <h2><i class="fas fa-graduation-cap"></i> Top 10 Courses by Enrollment</h2>
        <div style="margin-top: 1.5rem;">
          <?php 
          $course_stats->data_seek(0);
          $max_count = 0;
          $courses = [];
          while($row = $course_stats->fetch_assoc()) {
            $courses[] = $row;
            if($row['count'] > $max_count) $max_count = $row['count'];
          }
          
          foreach($courses as $course):
            $percentage = ($course['count'] / $max_count) * 100;
          ?>
          <div class="chart-bar">
            <div class="chart-label"><?= htmlspecialchars(substr($course['course'], 0, 20)) ?></div>
            <div class="chart-bar-bg">
              <div class="chart-bar-fill" style="width: <?= $percentage ?>%">
                <?= $course['count'] ?> students
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="report-section">
        <h2><i class="fas fa-star"></i> Dean's List Statistics by Year Level</h2>
        <table>
          <thead>
            <tr>
              <th>Year Level</th>
              <th>Number of Students</th>
              <th>Average GPA</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $deans_stats->data_seek(0);
            while($row = $deans_stats->fetch_assoc()): 
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($row['year_level']) ?></strong></td>
              <td><?= number_format($row['count']) ?></td>
              <td><strong><?= number_format($row['avg_gpa'], 2) ?></strong></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      
      <div class="report-section">
        <h2><i class="fas fa-info-circle"></i> Report Information</h2>
        <div style="padding: 1rem; background: #f7fafc; border-radius: 8px; font-size: 0.875rem; color: #4a5568;">
          <p><strong>Generated:</strong> <?= date('F d, Y h:i A') ?></p>
          <p><strong>Academic Period:</strong> <?= $selected_year ?> - <?= $selected_semester ?></p>
          <p><strong>Generated By:</strong> Registrar Office</p>
          <p><strong>Report Type:</strong> Comprehensive Enrollment & Academic Performance Report</p>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    function applyFilters() {
      const year = document.getElementById('yearSelect').value;
      const semester = document.getElementById('semesterSelect').value;
      window.location.href = `registrar_reports.php?year=${year}&semester=${semester}`;
    }
  </script>
</body>
</html>
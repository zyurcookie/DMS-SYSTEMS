<?php
session_start();
include('db.php');

// Restrict access to Registrar only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(DISTINCT student_id) FROM student_enrollment WHERE status='Enrolled') as total_enrolled,
    (SELECT COUNT(*) FROM student_enrollment WHERE academic_year='2024-2025') as enrolled_2024,
    (SELECT COUNT(*) FROM student_enrollment WHERE academic_year='2025-2026' AND semester='1st Semester') as enrolled_2025,
    (SELECT COUNT(*) FROM dean_list WHERE status='Verified') as deans_list_total,
    (SELECT COUNT(DISTINCT p.user_id) FROM profile p INNER JOIN user u ON p.user_id=u.user_id WHERE u.role='Student') as total_students";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get enrollment by year level
$year_level_sql = "SELECT year_level, COUNT(*) as count 
                   FROM student_enrollment 
                   WHERE status='Enrolled' 
                   GROUP BY year_level 
                   ORDER BY year_level";
$year_level_data = $conn->query($year_level_sql);

// Get enrollment by course
$course_sql = "SELECT course, COUNT(*) as count 
               FROM student_enrollment 
               WHERE status='Enrolled' 
               GROUP BY course 
               ORDER BY count DESC 
               LIMIT 10";
$course_data = $conn->query($course_sql);

// Recent enrollments
$recent_sql = "SELECT e.*, p.firstName, p.lastName, p.student_number 
               FROM student_enrollment e
               INNER JOIN profile p ON e.student_id = p.user_id
               ORDER BY e.enrollment_date DESC 
               LIMIT 10";
$recent_enrollments = $conn->query($recent_sql);

// Get pending tasks for registrar
$pending_grades = $conn->query("SELECT COUNT(*) as count FROM student_enrollment WHERE status='Enrolled'")->fetch_assoc()['count'];
$deans_eligible = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM dean_list WHERE status='Pending'")->fetch_assoc()['count'];
$scholarship_verifications = $conn->query("SELECT COUNT(*) as count FROM scholarship_application WHERE status='Under Review'")->fetch_assoc()['count'];
$transcript_requests = $conn->query("SELECT COUNT(*) as count FROM document WHERE doc_type='Transcript Request' AND status='Pending'")->fetch_assoc()['count'];

// Calculate grade encoding progress (example: assuming 100% if all enrolled have grades)
$grade_progress = 89; // This should be calculated based on actual grade records

$user_name = $_SESSION['role'];
$user_initial = substr($user_name, 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Registrar Dashboard</title>
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: white;
      padding: 1.75rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .stat-card h3 {
      font-size: 0.875rem;
      color: #718096;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.75rem;
      font-weight: 600;
    }
    .stat-value {
      font-size: 2.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
       -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 0.5rem;
    }
    .stat-label {
      font-size: 0.875rem;
      color: #a0aec0;
    }
    .content-row {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .content-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .content-card h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .pending-tasks {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 1.5rem;
    }
    .task-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      border-left: 4px solid #059669;
      background: #f7fafc;
      border-radius: 8px;
      margin-bottom: 0.75rem;
      transition: all 0.3s ease;
    }
    .task-item:hover {
      background: #e6f7f1;
      transform: translateX(5px);
    }
    .task-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.125rem;
    }
    .task-info {
      flex: 1;
      margin-left: 1rem;
    }
    .task-title {
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 0.25rem;
    }
    .task-desc {
      font-size: 0.875rem;
      color: #718096;
    }
    .task-count {
      font-size: 1.5rem;
      font-weight: 700;
      color: #059669;
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
    }
    tr:hover {
      background: #f7fafc;
    }
    .badge {
      display: inline-block;
      padding: 0.375rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-enrolled {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-withdrawn {
      background: #fee2e2;
      color: #991b1b;
    }
    .chart-container {
      margin-top: 1rem;
    }
    .chart-bar {
      display: flex;
      align-items: center;
      margin-bottom: 1rem;
    }
    .chart-label {
      width: 120px;
      font-size: 0.875rem;
      font-weight: 600;
      color: #4a5568;
    }
    .chart-bar-bg {
      flex: 1;
      height: 32px;
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
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    .action-card {
      background: #f7fafc;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      color: inherit;
    }
    .action-card:hover {
      border-color: #059669;
      background: #d1fae5;
      transform: translateY(-3px);
    }
    .action-card i {
      font-size: 2rem;
      color: #059669;
      margin-bottom: 0.75rem;
    }
    .action-card h3 {
      font-size: 0.875rem;
      color: #2d3748;
      font-weight: 600;
    }
    @media (max-width: 1024px) {
      .content-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-graduation-cap"></i> FEU Roosevelt DMS - Registrar</h1>
    <div class="user-info">
      <div class="user-avatar"><?= $user_initial ?></div>
      <span><?= htmlspecialchars($user_name) ?></span>
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
      <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Registrar Dashboard</h1>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Students</h3>
          <div class="stat-value"><?= number_format($stats['total_students']) ?></div>
          <div class="stat-label">Registered in System</div>
        </div>
        
        <div class="stat-card">
          <h3>Currently Enrolled</h3>
          <div class="stat-value"><?= number_format($stats['total_enrolled']) ?></div>
          <div class="stat-label">Active Enrollments</div>
        </div>
        
        <div class="stat-card">
          <h3>Grade Encoding</h3>
          <div class="stat-value"><?= $grade_progress ?>%</div>
          <div class="stat-label">Completion Progress</div>
        </div>
        
        <div class="stat-card">
          <h3>Dean's List Eligible</h3>
          <div class="stat-value"><?= number_format($deans_eligible) ?></div>
          <div class="stat-label">Pending Submission</div>
        </div>
      </div>
      
      <!-- Pending Tasks Section -->
      <div class="pending-tasks">
        <h2><i class="fas fa-tasks"></i> Pending Tasks</h2>
        
        <div class="task-item">
          <div class="task-icon"><i class="fas fa-edit"></i></div>
          <div class="task-info">
            <div class="task-title">Grades Pending Encoding</div>
            <div class="task-desc">Due in 3 days</div>
          </div>
          <div class="task-count"><?= $pending_grades ?></div>
        </div>
        
        <div class="task-item">
          <div class="task-icon"><i class="fas fa-star"></i></div>
          <div class="task-info">
            <div class="task-title">Dean's List Eligibility Report</div>
            <div class="task-desc">Ready to submit to Dean</div>
          </div>
          <div class="task-count"><?= $deans_eligible ?></div>
        </div>
        
        <div class="task-item">
          <div class="task-icon"><i class="fas fa-check-double"></i></div>
          <div class="task-info">
            <div class="task-title">Scholarship GPA Verifications</div>
            <div class="task-desc">Requested by Admin</div>
          </div>
          <div class="task-count"><?= $scholarship_verifications ?></div>
        </div>
        
        <div class="task-item">
          <div class="task-icon"><i class="fas fa-file-pdf"></i></div>
          <div class="task-info">
            <div class="task-title">Transcript Requests</div>
            <div class="task-desc">Awaiting processing</div>
          </div>
          <div class="task-count"><?= $transcript_requests ?></div>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="content-card" style="margin-bottom: 1.5rem;">
  <h2><i class="fas fa-bolt"></i> Quick Actions</h2>

  <div class="quick-actions">
    <a href="registrar_deans_list.php?action=generate" class="action-card">
      <i class="fas fa-star"></i>
      <h3>Generate Eligibility</h3>
    </a>
    <a href="registrar_transcripts.php?action=generate" class="action-card">
      <i class="fas fa-file-pdf"></i>
      <h3>Generate TOR</h3>
    </a>
    <a href="registrar_students.php?action=search" class="action-card">
      <i class="fas fa-search"></i>
      <h3>Search Student</h3>
    </a>
    <a href="registrar_reports.php" class="action-card">
      <i class="fas fa-chart-bar"></i>
      <h3>View Reports</h3>
    </a>
  </div>
</div>
      
      <div class="content-row">
        <div class="content-card">
          <h2><i class="fas fa-clock"></i> Recent Enrollments</h2>
          <table>
            <thead>
              <tr>
                <th>Student Number</th>
                <th>Name</th>
                <th>Year Level</th>
                <th>Academic Year</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while($enrollment = $recent_enrollments->fetch_assoc()): ?>
              <tr>
                <td><strong><?= htmlspecialchars($enrollment['student_number']) ?></strong></td>
                <td><?= htmlspecialchars($enrollment['lastName'] . ', ' . $enrollment['firstName']) ?></td>
                <td><?= htmlspecialchars($enrollment['year_level']) ?></td>
                <td><?= htmlspecialchars($enrollment['academic_year'] . ' ' . $enrollment['semester']) ?></td>
                <td><?= date('M d, Y', strtotime($enrollment['enrollment_date'])) ?></td>
                <td>
                  <span class="badge badge-<?= strtolower($enrollment['status']) ?>">
                    <?= htmlspecialchars($enrollment['status']) ?>
                  </span>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        
        <div>
          <div class="content-card" style="margin-bottom: 1.5rem;">
            <h2><i class="fas fa-layer-group"></i> By Year Level</h2>
            <div class="chart-container">
              <?php 
              $year_level_data->data_seek(0);
              $max_count = 0;
              $year_data = [];
              while($row = $year_level_data->fetch_assoc()) {
                $year_data[] = $row;
                if($row['count'] > $max_count) $max_count = $row['count'];
              }
              foreach($year_data as $data): 
                $percentage = ($data['count'] / $max_count) * 100;
              ?>
              <div class="chart-bar">
                <div class="chart-label"><?= htmlspecialchars($data['year_level']) ?></div>
                <div class="chart-bar-bg">
                  <div class="chart-bar-fill" style="width: <?= $percentage ?>%">
                    <?= $data['count'] ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          
          <div class="content-card">
            <h2><i class="fas fa-graduation-cap"></i> Top Courses</h2>
            <div class="chart-container">
              <?php 
              $course_data->data_seek(0);
              $max_count = 0;
              $course_array = [];
              while($row = $course_data->fetch_assoc()) {
                $course_array[] = $row;
                if($row['count'] > $max_count) $max_count = $row['count'];
              }
              $displayed = 0;
              foreach($course_array as $data): 
                if($displayed >= 5) break;
                $percentage = ($data['count'] / $max_count) * 100;
                $displayed++;
              ?>
              <div class="chart-bar">
                <div class="chart-label" style="width: 180px; font-size: 0.75rem;">
                  <?= htmlspecialchars(substr($data['course'], 0, 25)) ?>
                </div>
                <div class="chart-bar-bg">
                  <div class="chart-bar-fill" style="width: <?= $percentage ?>%">
                    <?= $data['count'] ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Recent Activity Footer -->
      <div class="content-card">
        <h2><i class="fas fa-history"></i> Recent Activity</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
          <div style="padding: 1rem; background: #f7fafc; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #718096; margin-bottom: 0.25rem;">Today</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">15</div>
            <div style="font-size: 0.875rem; color: #4a5568;">Grades Encoded</div>
          </div>
          <div style="padding: 1rem; background: #f7fafc; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #718096; margin-bottom: 0.25rem;">Today</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">8</div>
            <div style="font-size: 0.875rem; color: #4a5568;">Enrollments Processed</div>
          </div>
          <div style="padding: 1rem; background: #f7fafc; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #718096; margin-bottom: 0.25rem;">Today</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">3</div>
            <div style="font-size: 0.875rem; color: #4a5568;">Transcripts Issued</div>
          </div>
          <div style="padding: 1rem; background: #f7fafc; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #718096; margin-bottom: 0.25rem;">This Week</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">1</div>
            <div style="font-size: 0.875rem; color: #4a5568;">Dean's List Submitted</div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
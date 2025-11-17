<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle grade encoding/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grade'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $grades = $_POST['grades']; // Array of subject grades
    $gpa = $_POST['gpa'];
    
    // Update grades logic here
    // This is a simplified example - you'd have a grades table
    $successMessage = "Grades saved successfully!";
}

// Get filters
$academic_year = $_GET['academic_year'] ?? '2025-2026';
$semester = $_GET['semester'] ?? '1st Semester';
$year_level = $_GET['year_level'] ?? '';
$course = $_GET['course'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for students
$sql = "SELECT e.*, p.firstName, p.lastName, p.student_number, p.course 
        FROM student_enrollment e
        INNER JOIN profile p ON e.student_id = p.user_id
        WHERE e.status = 'Enrolled'
        AND e.academic_year = '$academic_year'
        AND e.semester = '$semester'";

if ($year_level) $sql .= " AND e.year_level = '$year_level'";
if ($course) $sql .= " AND e.course LIKE '%$course%'";
if ($search) $sql .= " AND (p.firstName LIKE '%$search%' OR p.lastName LIKE '%$search%' OR p.student_number LIKE '%$search%')";

$sql .= " ORDER BY p.lastName, p.firstName";
$students = $conn->query($sql);

// Get courses for filter
$courses = $conn->query("SELECT DISTINCT course FROM student_enrollment WHERE status='Enrolled'");

// Statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM student_enrollment WHERE status='Enrolled' AND academic_year='$academic_year' AND semester='$semester'")->fetch_assoc()['count'];
$grades_encoded = 0; // Calculate from grades table
$pending_grades = $total_students - $grades_encoded;
$avg_gpa = 0; // Calculate average

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Grade Management</title>
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
    }
    .badge-encoded {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-pending {
      background: #fef3c7;
      color: #92400e;
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
        <a href="registrar_dashboard.php" class="nav-item">
          <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="registrar_students.php" class="nav-item">
          <i class="fas fa-user-graduate"></i> Student Records
        </a>
        <a href="registrar_enrollments.php" class="nav-item">
          <i class="fas fa-clipboard-list"></i> Enrollment
        </a>
        <a href="registrar_grades.php" class="nav-item active">
          <i class="fas fa-file-alt"></i> Grade Management
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
      <h1 class="page-title"><i class="fas fa-edit"></i> Grade Management</h1>
      
      <?php if($successMessage): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($successMessage) ?></span>
      </div>
      <?php endif; ?>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Students</h3>
          <div class="stat-value"><?= number_format($total_students) ?></div>
        </div>
        <div class="stat-card">
          <h3>Grades Encoded</h3>
          <div class="stat-value"><?= number_format($grades_encoded) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending</h3>
          <div class="stat-value"><?= number_format($pending_grades) ?></div>
        </div>
        <div class="stat-card">
          <h3>Average GPA</h3>
          <div class="stat-value"><?= number_format($avg_gpa, 2) ?></div>
        </div>
      </div>
      
      <div class="toolbar">
        <select id="academicYearFilter">
          <option value="2024-2025" <?= $academic_year == '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
          <option value="2025-2026" <?= $academic_year == '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
        </select>
        <select id="semesterFilter">
          <option value="1st Semester" <?= $semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
          <option value="2nd Semester" <?= $semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
          <option value="Summer" <?= $semester == 'Summer' ? 'selected' : '' ?>>Summer</option>
        </select>
        <select id="yearLevelFilter">
          <option value="">All Year Levels</option>
          <option value="1st Year" <?= $year_level == '1st Year' ? 'selected' : '' ?>>1st Year</option>
          <option value="2nd Year" <?= $year_level == '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
          <option value="3rd Year" <?= $year_level == '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
          <option value="4th Year" <?= $year_level == '4th Year' ? 'selected' : '' ?>>4th Year</option>
        </select>
        <select id="courseFilter">
          <option value="">All Courses</option>
          <?php while($c = $courses->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($c['course']) ?>" <?= $course == $c['course'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['course']) ?>
          </option>
          <?php endwhile; ?>
        </select>
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      
      <div class="content-card">
        <table>
          <thead>
            <tr>
              <th>Student #</th>
              <th>Name</th>
              <th>Year Level</th>
              <th>Course</th>
              <th>GPA</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($student = $students->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($student['student_number']) ?></strong></td>
              <td><?= htmlspecialchars($student['lastName'] . ', ' . $student['firstName']) ?></td>
              <td><?= htmlspecialchars($student['year_level']) ?></td>
              <td><?= htmlspecialchars(substr($student['course'], 0, 30)) ?></td>
              <td><strong>-</strong></td>
              <td>
                <span class="badge badge-pending">Pending</span>
              </td>
              <td>
                <button class="btn btn-primary btn-sm" onclick="encodeGrades(<?= $student['enrollment_id'] ?>, '<?= htmlspecialchars($student['firstName'] . ' ' . $student['lastName']) ?>')">
                  <i class="fas fa-edit"></i> Encode
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
  
  <script>
    function applyFilters() {
      const year = document.getElementById('academicYearFilter').value;
      const sem = document.getElementById('semesterFilter').value;
      const level = document.getElementById('yearLevelFilter').value;
      const course = document.getElementById('courseFilter').value;
      const search = document.getElementById('searchInput').value;
      window.location.href = `registrar_grades.php?academic_year=${year}&semester=${sem}&year_level=${level}&course=${course}&search=${search}`;
    }
    
    document.getElementById('academicYearFilter').addEventListener('change', applyFilters);
    document.getElementById('semesterFilter').addEventListener('change', applyFilters);
    document.getElementById('yearLevelFilter').addEventListener('change', applyFilters);
    document.getElementById('courseFilter').addEventListener('change', applyFilters);
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    
    function encodeGrades(enrollmentId, studentName) {
      alert('Grade encoding form for ' + studentName + ' will be implemented here');
      // This would open a modal or redirect to grade encoding form
    }
  </script>
</body>
</html>

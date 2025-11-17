<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

// Get student info if generating transcript
$student_id = $_GET['student'] ?? null;
$student_data = null;
$enrollments = null;
$deans_list = null;

if ($student_id) {
    // Get student info
    $sql = "SELECT u.*, p.* FROM user u
            INNER JOIN profile p ON u.user_id = p.user_id
            WHERE u.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_data = $stmt->get_result()->fetch_assoc();
    
    // Get enrollments
    $enrollments = $conn->query("SELECT * FROM student_enrollment 
                                 WHERE student_id = $student_id 
                                 ORDER BY academic_year DESC, semester DESC");
    
    // Get dean's list
    $deans_list = $conn->query("SELECT * FROM dean_list 
                                WHERE student_id = $student_id AND status = 'Verified'
                                ORDER BY academic_year DESC, semester DESC");
}

// Get all students for selection
$students = $conn->query("SELECT u.user_id, p.student_number, p.firstName, p.lastName, p.course, p.year_level
                          FROM user u
                          INNER JOIN profile p ON u.user_id = p.user_id
                          WHERE u.role = 'Student'
                          ORDER BY p.lastName, p.firstName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Transcript of Records</title>
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
    .main-content {
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }
    .page-title {
      color: white;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 2rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
      text-align: center;
    }
    .selector-card {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .selector-card h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      font-weight: 700;
    }
    .form-row {
      display: flex;
      gap: 1rem;
      align-items: end;
    }
    select {
      flex: 1;
      padding: 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
      background: white;
    }
    select:focus {
      outline: none;
      border-color: #059669;
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
    .btn-secondary {
      background: #e2e8f0;
      color: #4a5568;
    }
    .transcript-container {
      background: white;
      padding: 3rem;
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      border: 8px solid;
      border-image: linear-gradient(135deg, #059669 0%, #10b981 100%) 1;
    }
    .transcript-header {
      text-align: center;
      padding-bottom: 2rem;
      border-bottom: 3px solid #059669;
      margin-bottom: 2rem;
    }
    .university-name {
      font-size: 2rem;
      font-weight: 800;
      color: #2d3748;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
    }
    .university-subtitle {
      font-size: 1.125rem;
      color: #059669;
      font-weight: 600;
      margin-bottom: 1rem;
    }
    .document-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #667eea;
      margin-top: 1rem;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    .student-info {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem 2rem;
      padding: 1.5rem;
      background: #f7fafc;
      border-radius: 10px;
      margin-bottom: 2rem;
    }
    .info-item {
      display: flex;
    }
    .info-label {
      font-weight: 700;
      color: #4a5568;
      min-width: 150px;
    }
    .info-value {
      color: #2d3748;
    }
    .section-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: #059669;
      margin: 2rem 0 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid #e2e8f0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2rem;
    }
    th {
      background: #f7fafc;
      padding: 0.75rem;
      text-align: left;
      font-weight: 700;
      color: #4a5568;
      font-size: 0.875rem;
      border: 1px solid #e2e8f0;
    }
    td {
      padding: 0.75rem;
      border: 1px solid #e2e8f0;
      color: #2d3748;
      font-size: 0.875rem;
    }
    .footer {
      margin-top: 3rem;
      padding-top: 2rem;
      border-top: 2px solid #e2e8f0;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
    }
    .signature-block {
      text-align: center;
    }
    .signature-line {
      border-top: 2px solid #2d3748;
      margin-top: 3rem;
      padding-top: 0.5rem;
    }
    .signature-name {
      font-weight: 700;
      color: #2d3748;
    }
    .signature-title {
      font-size: 0.875rem;
      color: #718096;
      font-style: italic;
    }
    .action-buttons {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 2rem;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    @media print {
      body {
        background: white;
      }
      .header, .selector-card, .action-buttons, .logout-btn {
        display: none !important;
      }
      .transcript-container {
        border: 8px solid #059669;
        box-shadow: none;
        page-break-inside: avoid;
      }
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
  
  <div class="main-content">
    <h1 class="page-title"><i class="fas fa-scroll"></i> Transcript of Records Generator</h1>
    
    <div class="selector-card">
      <h2><i class="fas fa-search"></i> Select Student</h2>
      <form method="GET">
        <div class="form-row">
          <select name="student" required>
            <option value="">-- Select Student --</option>
            <?php while($s = $students->fetch_assoc()): ?>
            <option value="<?= $s['user_id'] ?>" <?= $student_id == $s['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['student_number'] . ' - ' . $s['lastName'] . ', ' . $s['firstName'] . ' (' . $s['course'] . ')') ?>
            </option>
            <?php endwhile; ?>
          </select>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-file-alt"></i> Generate Transcript
          </button>
        </div>
      </form>
    </div>
    
    <?php if($student_data): ?>
    <div class="transcript-container" id="transcript">
      <div class="transcript-header">
        <div style="width: 80px; height: 80px; margin: 0 auto 1rem; background: linear-gradient(135deg, #059669 0%, #10b981 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
          <i class="fas fa-university"></i>
        </div>
        <div class="university-name">FEU Roosevelt</div>
        <div class="university-subtitle">Marikina Campus</div>
        <div class="document-title">Transcript of Records</div>
      </div>
      
      <div class="student-info">
        <div class="info-item">
          <span class="info-label">Student Number:</span>
          <span class="info-value"><?= htmlspecialchars($student_data['student_number']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Name:</span>
          <span class="info-value"><?= htmlspecialchars($student_data['firstName'] . ' ' . ($student_data['middleName'] ? $student_data['middleName'] . ' ' : '') . $student_data['lastName']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Course:</span>
          <span class="info-value"><?= htmlspecialchars($student_data['course'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Year Level:</span>
          <span class="info-value"><?= htmlspecialchars($student_data['year_level'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Date of Issue:</span>
          <span class="info-value"><?= date('F d, Y') ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">TOR Number:</span>
          <span class="info-value">TOR-<?= str_pad($student_data['user_id'], 6, '0', STR_PAD_LEFT) ?>-<?= date('Y') ?></span>
        </div>
      </div>
      
      <div class="section-title">Enrollment History</div>
      <?php if($enrollments && $enrollments->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Academic Year</th>
            <th>Semester</th>
            <th>Year Level</th>
            <th>Course</th>
            <th>Enrollment Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while($enroll = $enrollments->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($enroll['academic_year']) ?></td>
            <td><?= htmlspecialchars($enroll['semester']) ?></td>
            <td><?= htmlspecialchars($enroll['year_level']) ?></td>
            <td><?= htmlspecialchars($enroll['course']) ?></td>
            <td><?= date('M d, Y', strtotime($enroll['enrollment_date'])) ?></td>
            <td><strong><?= htmlspecialchars($enroll['status']) ?></strong></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color: #a0aec0; padding: 1rem;">No enrollment records found.</p>
      <?php endif; ?>
      
      <div class="section-title">Academic Honors & Dean's List</div>
      <?php if($deans_list && $deans_list->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Academic Year</th>
            <th>Semester</th>
            <th>Year Level</th>
            <th>GPA</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while($dl = $deans_list->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($dl['academic_year']) ?></td>
            <td><?= htmlspecialchars($dl['semester']) ?></td>
            <td><?= htmlspecialchars($dl['year_level']) ?></td>
            <td><strong><?= number_format($dl['gpa'], 2) ?></strong></td>
            <td><?= htmlspecialchars($dl['status']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color: #a0aec0; padding: 1rem;">No Dean's List records found.</p>
      <?php endif; ?>
      
      <div style="margin: 2rem 0; padding: 1rem; background: #f7fafc; border-radius: 8px;">
        <p style="font-size: 0.875rem; color: #4a5568; text-align: center; font-style: italic;">
          This transcript is issued upon request and contains a true and accurate record of the student's academic performance at FEU Roosevelt Marikina.
        </p>
      </div>
      
      <div class="footer">
        <div class="signature-block">
          <div class="signature-line">
            <div class="signature-name">[Registrar's Name]</div>
            <div class="signature-title">University Registrar</div>
          </div>
        </div>
        <div class="signature-block">
          <div class="signature-line">
            <div class="signature-name">Dr. [Dean's Name]</div>
            <div class="signature-title">Dean, College of [Department]</div>
          </div>
        </div>
      </div>
      
      <div style="text-align: center; margin-top: 2rem; font-size: 0.75rem; color: #a0aec0;">
        <p>FEU Roosevelt Marikina • Ligaya Drive, Marikina City • Tel: (02) XXXX-XXXX</p>
        <p>This is an electronically generated document. No signature is required.</p>
      </div>
    </div>
    
    <div class="action-buttons">
      <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Transcript
      </button>
      <button onclick="downloadPDF()" class="btn btn-primary">
        <i class="fas fa-download"></i> Download PDF
      </button>
      <a href="registrar_transcripts.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Generate Another
      </a>
    </div>
    
    <?php else: ?>
    <div style="background: white; padding: 3rem; border-radius: 15px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
      <i class="fas fa-search" style="font-size: 4rem; color: #a0aec0; margin-bottom: 1rem;"></i>
      <h2 style="color: #2d3748; margin-bottom: 1rem;">Select a Student</h2>
      <p style="color: #718096;">Choose a student from the dropdown above to generate their transcript of records.</p>
    </div>
    <?php endif; ?>
  </div>
  
  <script>
    function downloadPDF() {
      alert('PDF download functionality will be implemented with a PDF library like jsPDF or html2pdf.js');
      if(confirm('Would you like to print this transcript? You can save it as PDF from the print dialog.')) {
        window.print();
      }
    }
  </script>
</body>
</html>
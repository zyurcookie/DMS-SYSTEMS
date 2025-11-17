<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Dean', 'Registrar'])) {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Get application ID
$app_id = $_GET['id'] ?? null;

if (!$app_id) {
    header('Location: scholarships.php');
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';
    
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
}

// Get application details
$sql = "SELECT sa.*, 
        p.firstName, p.lastName, p.middleName, p.student_number, p.course, p.year_level, p.contactNumber, p.address,
        u.email,
        dl.gpa, dl.status as deans_status,
        r.email as reviewer_email
        FROM scholarship_application sa
        INNER JOIN user u ON sa.student_id = u.user_id
        INNER JOIN profile p ON u.user_id = p.user_id
        LEFT JOIN dean_list dl ON sa.student_id = dl.student_id AND sa.academic_year = dl.academic_year AND sa.semester = dl.semester
        LEFT JOIN user r ON sa.reviewed_by = r.user_id
        WHERE sa.app_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    header('Location: scholarships.php');
    exit();
}

// Get related documents
$docs_sql = "SELECT * FROM document 
             WHERE student_id = ? AND related_type = 'scholarship' AND related_id = ?
             ORDER BY upload_date DESC";
$stmt = $conn->prepare($docs_sql);
$stmt->bind_param("ii", $application['student_id'], $app_id);
$stmt->execute();
$documents = $stmt->get_result();

// Get student's dean list history
$deans_history = $conn->query("SELECT * FROM dean_list 
                                WHERE student_id = {$application['student_id']} 
                                ORDER BY academic_year DESC, semester DESC 
                                LIMIT 5");

// Get student's enrollment history
$enrollment_history = $conn->query("SELECT * FROM student_enrollment 
                                    WHERE student_id = {$application['student_id']} 
                                    ORDER BY academic_year DESC, semester DESC 
                                    LIMIT 5");

$user_name = $_SESSION['role'];
$user_initial = substr($user_name, 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - View Application</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
      color: #667eea;
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
    }
    .main-content {
      padding: 2rem;
      max-width: 1400px;
      margin: 0 auto;
    }
    .page-title {
      color: white;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 2rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    .back-btn {
      background: white;
      color: #667eea;
      padding: 0.625rem 1.25rem;
      border-radius: 8px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    .back-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
    }
    .content-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .content-card h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e2e8f0;
    }
    .student-header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      padding: 1.5rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 12px;
      color: white;
      margin-bottom: 1.5rem;
    }
    .student-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: white;
      color: #667eea;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      border: 4px solid rgba(255,255,255,0.3);
    }
    .student-info h3 {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
    }
    .student-meta {
      display: flex;
      gap: 1.5rem;
      font-size: 0.875rem;
      opacity: 0.95;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .info-item {
      padding: 1rem;
      background: #f7fafc;
      border-radius: 8px;
    }
    .info-label {
      font-size: 0.75rem;
      color: #a0aec0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.25rem;
    }
    .info-value {
      font-size: 1rem;
      color: #2d3748;
      font-weight: 600;
    }
    .badge {
      display: inline-block;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-submitted {
      background: #dbeafe;
      color: #1e40af;
    }
    .badge-under {
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
    .badge-hold {
      background: #e0e7ff;
      color: #3730a3;
    }
    .badge-verified {
      background: #d1fae5;
      color: #065f46;
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
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
      transition: all 0.3s ease;
    }
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .btn-success {
      background: #10b981;
      color: white;
    }
    .btn-danger {
      background: #ef4444;
      color: white;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th {
      background: #f7fafc;
      padding: 0.75rem;
      text-align: left;
      font-weight: 600;
      color: #4a5568;
      font-size: 0.875rem;
      border-bottom: 2px solid #e2e8f0;
    }
    td {
      padding: 0.75rem;
      border-bottom: 1px solid #e2e8f0;
      color: #4a5568;
      font-size: 0.875rem;
    }
    .document-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      margin-bottom: 0.75rem;
      transition: all 0.3s ease;
    }
    .document-item:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }
    .doc-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .doc-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: #e6f0ff;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #667eea;
      font-size: 1.25rem;
    }
    .empty-state {
      text-align: center;
      padding: 2rem;
      color: #a0aec0;
    }
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 0.75rem;
      opacity: 0.5;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    @media (max-width: 1024px) {
      .content-grid {
        grid-template-columns: 1fr;
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
  
  <div class="main-content">
    <a href="scholarships.php" class="back-btn">
      <i class="fas fa-arrow-left"></i> Back to Scholarships
    </a>
    
    <h1 class="page-title"><i class="fas fa-award"></i> Scholarship Application Details</h1>
    
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
    
    <div class="content-grid">
      <div>
        <!-- Student Information -->
        <div class="content-card">
          <div class="student-header">
            <div class="student-avatar">
              <?= strtoupper(substr($application['firstName'], 0, 1) . substr($application['lastName'], 0, 1)) ?>
            </div>
            <div class="student-info">
              <h3><?= htmlspecialchars($application['firstName'] . ' ' . ($application['middleName'] ? $application['middleName'][0] . '. ' : '') . $application['lastName']) ?></h3>
              <div class="student-meta">
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($application['student_number']) ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($application['email']) ?></span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($application['contactNumber'] ?? 'N/A') ?></span>
              </div>
            </div>
          </div>
          
          <h2><i class="fas fa-user"></i> Student Details</h2>
          
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Course</div>
              <div class="info-value"><?= htmlspecialchars($application['course']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Year Level</div>
              <div class="info-value"><?= htmlspecialchars($application['year_level']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">GPA</div>
              <div class="info-value"><?= $application['gpa'] ? number_format($application['gpa'], 2) : 'N/A' ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Dean's List Status</div>
              <div class="info-value">
                <?php if($application['deans_status']): ?>
                  <span class="badge badge-verified"><?= htmlspecialchars($application['deans_status']) ?></span>
                <?php else: ?>
                  Not on Dean's List
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <?php if($application['address']): ?>
          <div class="info-item" style="margin-top: 1rem;">
            <div class="info-label">Address</div>
            <div class="info-value"><?= htmlspecialchars($application['address']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Application Information -->
        <div class="content-card">
          <h2><i class="fas fa-file-alt"></i> Application Information</h2>
          
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Application ID</div>
              <div class="info-value">#<?= str_pad($application['app_id'], 6, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Scholarship Type</div>
              <div class="info-value"><?= htmlspecialchars($application['scholarship_type']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Academic Year</div>
              <div class="info-value"><?= htmlspecialchars($application['academic_year']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Semester</div>
              <div class="info-value"><?= htmlspecialchars($application['semester']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Application Date</div>
              <div class="info-value"><?= date('M d, Y h:i A', strtotime($application['application_date'])) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Current Status</div>
              <div class="info-value">
                <span class="badge badge-<?= str_replace(' ', '', strtolower($application['status'])) ?>">
                  <?= htmlspecialchars($application['status']) ?>
                </span>
              </div>
            </div>
          </div>
          
          <?php if($application['reviewed_by']): ?>
          <div class="info-grid" style="margin-top: 1rem;">
            <div class="info-item">
              <div class="info-label">Reviewed By</div>
              <div class="info-value"><?= htmlspecialchars($application['reviewer_email']) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">Review Date</div>
              <div class="info-value"><?= date('M d, Y h:i A', strtotime($application['review_date'])) ?></div>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if($application['remarks']): ?>
          <div class="info-item" style="margin-top: 1rem;">
            <div class="info-label">Remarks</div>
            <div class="info-value"><?= htmlspecialchars($application['remarks']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Submitted Documents -->
        <div class="content-card">
          <h2><i class="fas fa-paperclip"></i> Submitted Documents</h2>
          
          <?php if($documents && $documents->num_rows > 0): ?>
            <?php while($doc = $documents->fetch_assoc()): ?>
            <div class="document-item">
              <div class="doc-info">
                <div class="doc-icon">
                  <i class="fas fa-file-pdf"></i>
                </div>
                <div>
                  <div style="font-weight: 600; color: #2d3748; margin-bottom: 0.25rem;">
                    <?= htmlspecialchars($doc['doc_name']) ?>
                  </div>
                  <div style="font-size: 0.75rem; color: #718096;">
                    <?= htmlspecialchars($doc['doc_type']) ?> • 
                    <?= number_format($doc['file_size'] / 1024, 2) ?> KB • 
                    <?= date('M d, Y', strtotime($doc['upload_date'])) ?>
                  </div>
                </div>
              </div>
              <div>
                <span class="badge badge-<?= strtolower($doc['status']) ?>">
                  <?= htmlspecialchars($doc['status']) ?>
                </span>
                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-primary" style="margin-left: 0.5rem; padding: 0.5rem 1rem;">
                  <i class="fas fa-eye"></i> View
                </a>
              </div>
            </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-inbox"></i>
              <p>No documents submitted</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div>
        <!-- Update Status -->
        <div class="content-card">
          <h2><i class="fas fa-edit"></i> Update Status</h2>
          
          <form method="POST">
            <div class="form-group">
              <label>New Status</label>
              <select name="status" required>
                <option value="Submitted" <?= $application['status'] == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="Under Review" <?= $application['status'] == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                <option value="Approved" <?= $application['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $application['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="On Hold" <?= $application['status'] == 'On Hold' ? 'selected' : '' ?>>On Hold</option>
              </select>
            </div>
            
            <div class="form-group">
              <label>Remarks/Feedback</label>
              <textarea name="remarks" rows="5" placeholder="Enter remarks or feedback for the student..."><?= htmlspecialchars($application['remarks'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" name="update_status" class="btn btn-success" style="width: 100%; justify-content: center;">
              <i class="fas fa-save"></i> Update Status
            </button>
          </form>
        </div>
        
        <!-- Dean's List History -->
        <div class="content-card">
          <h2><i class="fas fa-star"></i> Dean's List History</h2>
          
          <?php if($deans_history && $deans_history->num_rows > 0): ?>
            <table>
              <thead>
                <tr>
                  <th>Period</th>
                  <th>GPA</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while($dl = $deans_history->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($dl['academic_year']) ?><br>
                    <small style="color: #a0aec0;"><?= htmlspecialchars($dl['semester']) ?></small>
                  </td>
                  <td><strong><?= number_format($dl['gpa'], 2) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= strtolower($dl['status']) ?>">
                      <?= htmlspecialchars($dl['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-star"></i>
              <p>No Dean's List records</p>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Enrollment History -->
        <div class="content-card">
          <h2><i class="fas fa-clipboard-list"></i> Enrollment History</h2>
          
          <?php if($enrollment_history && $enrollment_history->num_rows > 0): ?>
            <table>
              <thead>
                <tr>
                  <th>Period</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while($enroll = $enrollment_history->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($enroll['academic_year']) ?><br>
                    <small style="color: #a0aec0;">
                      <?= htmlspecialchars($enroll['semester']) ?> • 
                      <?= htmlspecialchars($enroll['year_level']) ?>
                    </small>
                  </td>
                  <td>
                    <span class="badge badge-<?= strtolower($enroll['status']) ?>">
                      <?= htmlspecialchars($enroll['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-clipboard-list"></i>
              <p>No enrollment records</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
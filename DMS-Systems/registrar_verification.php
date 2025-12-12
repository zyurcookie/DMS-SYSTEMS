<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_action'])) {
    $type = $_POST['type'];
    $id = $_POST['id'];
    $action = $_POST['action'];
    $remarks = $_POST['remarks'] ?? '';
    
    if ($type == 'dean_list') {
        $status = $action == 'approve' ? 'Verified' : 'Rejected';
        $sql = "UPDATE dean_list SET status=?, remarks=?, verified_by=?, verified_date=NOW() WHERE list_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $id);
        
        if ($stmt->execute()) {
            $successMessage = "Dean's List entry " . ($action == 'approve' ? 'verified' : 'rejected') . " successfully!";
        } else {
            $errorMessage = "Error updating verification status.";
        }
    } elseif ($type == 'document') {
        $status = $action == 'approve' ? 'Approved' : 'Rejected';
        $sql = "UPDATE document SET status=?, comments=?, reviewed_by=?, review_date=NOW() WHERE doc_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $id);
        
        if ($stmt->execute()) {
            $successMessage = "Document " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
        } else {
            $errorMessage = "Error updating document status.";
        }
    } elseif ($type == 'scholarship') {
        $status = $action == 'approve' ? 'Approved' : 'Rejected';
        $sql = "UPDATE scholarship_applications SET status=?, remarks=?, reviewed_by=?, review_date=NOW() WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $id);
        
        if ($stmt->execute()) {
            $successMessage = "Scholarship application " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
        } else {
            $errorMessage = "Error updating scholarship status.";
        }
    }
}

// Get pending Dean's List entries
$deans_pending = $conn->query("SELECT dl.*, p.firstName, p.lastName, p.student_number, p.course 
                                FROM dean_list dl
                                INNER JOIN profile p ON dl.student_id = p.user_id
                                WHERE dl.status = 'Pending'
                                ORDER BY dl.academic_year DESC, dl.semester DESC");

// Get pending documents
$docs_pending = $conn->query("SELECT d.*, p.firstName, p.lastName, p.student_number 
                               FROM document d
                               INNER JOIN profile p ON d.student_id = p.user_id
                               WHERE d.status = 'Pending'
                               ORDER BY d.upload_date DESC");

// Get pending scholarship applications
$scholarship_query = "SELECT 
    sa.*,
    p.firstName, 
    p.lastName, 
    p.student_number,
    p.course,
    dl.gpa,
    dl.academic_year as dl_year,
    dl.semester as dl_semester,
    (SELECT COUNT(*) FROM document WHERE student_id = sa.student_id AND doc_type = 'Scholarship Form' AND status = 'Approved') as has_form,
    (SELECT COUNT(*) FROM document WHERE student_id = sa.student_id AND doc_type = 'Transcript' AND status = 'Approved') as has_transcript,
    (SELECT COUNT(*) FROM document WHERE student_id = sa.student_id AND doc_type = 'Dean\\'s List Certificate' AND status = 'Approved') as has_dl_cert,
    (SELECT COUNT(*) FROM document WHERE student_id = sa.student_id AND doc_type = 'Enrollment Proof' AND status = 'Approved') as has_enrollment
    FROM scholarship_applications sa
    INNER JOIN profile p ON sa.student_id = p.user_id
    LEFT JOIN dean_list dl ON sa.student_id = dl.student_id 
        AND dl.academic_year = sa.academic_year 
        AND dl.semester = sa.semester
        AND dl.status = 'Verified'
    WHERE sa.status = 'Pending'
    ORDER BY sa.application_date DESC";

$scholarships_pending = $conn->query($scholarship_query);

// Get statistics
$scholarship_count = $scholarships_pending ? $scholarships_pending->num_rows : 0;
$stats = [
    'pending_deans' => $deans_pending->num_rows,
    'pending_docs' => $docs_pending->num_rows,
    'pending_scholarships' => $scholarship_count,
    'total_pending' => $deans_pending->num_rows + $docs_pending->num_rows + $scholarship_count
];

// Reset for display
$deans_pending->data_seek(0);
$docs_pending->data_seek(0);
if ($scholarships_pending) $scholarships_pending->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Verification Queue</title>
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: white;
      padding: 1.75rem;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    }
    .stat-card h3 {
      font-size: 0.875rem;
      color: #718096;
      text-transform: uppercase;
      margin-bottom: 0.75rem;
    }
    .stat-value {
      font-size: 2.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
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
    }
    .verification-item {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      transition: all 0.3s ease;
    }
    .verification-item:hover {
      border-color: #059669;
      box-shadow: 0 4px 15px rgba(5, 150, 105, 0.1);
    }
    .item-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #e2e8f0;
    }
    .item-title {
      font-size: 1.125rem;
      font-weight: 700;
      color: #2d3748;
      margin-bottom: 0.25rem;
    }
    .item-subtitle {
      font-size: 0.875rem;
      color: #718096;
    }
    .item-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .detail-item {
      display: flex;
      flex-direction: column;
    }
    .detail-label {
      font-size: 0.75rem;
      color: #a0aec0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.25rem;
    }
    .detail-value {
      font-size: 0.95rem;
      color: #2d3748;
      font-weight: 600;
    }
    .requirements-checklist {
      background: #f7fafc;
      padding: 1rem;
      border-radius: 8px;
      margin: 1rem 0;
    }
    .requirements-checklist h4 {
      font-size: 0.875rem;
      color: #4a5568;
      margin-bottom: 0.75rem;
      font-weight: 600;
    }
    .requirement-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 0;
      font-size: 0.875rem;
    }
    .requirement-item i {
      font-size: 1rem;
    }
    .requirement-item.complete {
      color: #059669;
    }
    .requirement-item.incomplete {
      color: #dc2626;
    }
    .action-buttons {
      display: flex;
      gap: 0.75rem;
      margin-top: 1rem;
      flex-wrap: wrap;
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
    .btn-success {
      background: #10b981;
      color: white;
    }
    .btn-success:hover {
      background: #059669;
      transform: translateY(-2px);
    }
    .btn-danger {
      background: #ef4444;
      color: white;
    }
    .btn-danger:hover {
      background: #dc2626;
      transform: translateY(-2px);
    }
    .btn-info {
      background: #3b82f6;
      color: white;
    }
    .btn-warning {
      background: #f59e0b;
      color: white;
    }
    .badge {
      display: inline-block;
      padding: 0.375rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .badge-pending {
      background: #fef3c7;
      color: #92400e;
    }
    .badge-warning {
      background: #fee2e2;
      color: #991b1b;
    }
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: #a0aec0;
    }
    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.5;
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
      max-width: 600px;
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
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.875rem;
      min-height: 100px;
      font-family: inherit;
    }
    .form-group textarea:focus {
      outline: none;
      border-color: #059669;
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
      <h1 class="page-title"><i class="fas fa-check-double"></i> Verification Queue</h1>
      
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
          <h3>Total Pending</h3>
          <div class="stat-value"><?= $stats['total_pending'] ?></div>
        </div>
        <div class="stat-card">
          <h3>Dean's List</h3>
          <div class="stat-value"><?= $stats['pending_deans'] ?></div>
        </div>
        <div class="stat-card">
          <h3>Scholarships</h3>
          <div class="stat-value"><?= $stats['pending_scholarships'] ?></div>
        </div>
        <div class="stat-card">
          <h3>Documents</h3>
          <div class="stat-value"><?= $stats['pending_docs'] ?></div>
        </div>
      </div>
      
      <!-- Scholarship Applications -->
      <div class="content-card">
        <h2><i class="fas fa-hand-holding-usd"></i> Scholarship Applications Pending</h2>
        
        <?php if($scholarships_pending && $scholarships_pending->num_rows > 0): ?>
          <?php while($app = $scholarships_pending->fetch_assoc()): 
            $all_requirements_met = ($app['has_form'] > 0 && $app['has_transcript'] > 0 && $app['has_dl_cert'] > 0 && $app['has_enrollment'] > 0);
          ?>
          <div class="verification-item">
            <div class="item-header">
              <div>
                <div class="item-title"><?= htmlspecialchars($app['lastName'] . ', ' . $app['firstName']) ?></div>
                <div class="item-subtitle">Student #: <?= htmlspecialchars($app['student_number']) ?></div>
              </div>
              <span class="badge <?= $all_requirements_met ? 'badge-pending' : 'badge-warning' ?>">
                <?= $all_requirements_met ? 'Ready for Review' : 'Incomplete Documents' ?>
              </span>
            </div>
            
            <div class="item-details">
              <div class="detail-item">
                <span class="detail-label">Academic Year</span>
                <span class="detail-value"><?= htmlspecialchars($app['academic_year']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Semester</span>
                <span class="detail-value"><?= htmlspecialchars($app['semester']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Course</span>
                <span class="detail-value"><?= htmlspecialchars($app['course']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">GPA</span>
                <span class="detail-value"><?= $app['gpa'] ? number_format($app['gpa'], 2) : 'N/A' ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Application Date</span>
                <span class="detail-value"><?= date('M d, Y', strtotime($app['application_date'])) ?></span>
              </div>
            </div>
            
            <div class="requirements-checklist">
              <h4><i class="fas fa-clipboard-check"></i> Required Documents:</h4>
              
              <div class="requirement-item <?= $app['has_form'] > 0 ? 'complete' : 'incomplete' ?>">
                <i class="fas <?= $app['has_form'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span>Academic Scholarship Form with signature</span>
              </div>
              
              <div class="requirement-item <?= $app['has_transcript'] > 0 ? 'complete' : 'incomplete' ?>">
                <i class="fas <?= $app['has_transcript'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span>Copy of Transcript/Course List from NetSuite</span>
              </div>
              
              <div class="requirement-item <?= $app['has_dl_cert'] > 0 ? 'complete' : 'incomplete' ?>">
                <i class="fas <?= $app['has_dl_cert'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span>Copy of Certificate of Dean's Lister</span>
              </div>
              
              <div class="requirement-item <?= $app['has_enrollment'] > 0 ? 'complete' : 'incomplete' ?>">
                <i class="fas <?= $app['has_enrollment'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span>Copy of Proof of Enrollment (Certified True Copy/Receipt)</span>
              </div>
            </div>
            
            <div class="action-buttons">
              <a href="student_documents.php?student_id=<?= $app['student_id'] ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-folder-open"></i> View Documents
              </a>
              <?php if($all_requirements_met): ?>
              <button class="btn btn-success" onclick="openVerifyModal('scholarship', <?= $app['id'] ?>, 'approve', '<?= htmlspecialchars($app['firstName'] . ' ' . $app['lastName']) ?>')">
                <i class="fas fa-check"></i> Approve
              </button>
              <?php else: ?>
              <button class="btn btn-warning" disabled title="Incomplete requirements">
                <i class="fas fa-exclamation-triangle"></i> Incomplete
              </button>
              <?php endif; ?>
              <button class="btn btn-danger" onclick="openVerifyModal('scholarship', <?= $app['id'] ?>, 'reject', '<?= htmlspecialchars($app['firstName'] . ' ' . $app['lastName']) ?>')">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-hand-holding-usd"></i>
            <p>No pending scholarship applications</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Dean's List Verifications -->
      <div class="content-card">
        <h2><i class="fas fa-star"></i> Dean's List Pending Verification</h2>
        
        <?php if($deans_pending->num_rows > 0): ?>
          <?php while($item = $deans_pending->fetch_assoc()): ?>
          <div class="verification-item">
            <div class="item-header">
              <div>
                <div class="item-title"><?= htmlspecialchars($item['lastName'] . ', ' . $item['firstName']) ?></div>
                <div class="item-subtitle">Student #: <?= htmlspecialchars($item['student_number']) ?></div>
              </div>
              <span class="badge badge-pending">Pending</span>
            </div>
            
            <div class="item-details">
              <div class="detail-item">
                <span class="detail-label">Academic Year</span>
                <span class="detail-value"><?= htmlspecialchars($item['academic_year']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Semester</span>
                <span class="detail-value"><?= htmlspecialchars($item['semester']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Year Level</span>
                <span class="detail-value"><?= htmlspecialchars($item['year_level']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">GPA</span>
                <span class="detail-value"><?= number_format($item['gpa'], 2) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Course</span>
                <span class="detail-value"><?= htmlspecialchars($item['course']) ?></span>
              </div>
            </div>
            
            <div class="action-buttons">
              <button class="btn btn-success" onclick="openVerifyModal('dean_list', <?= $item['list_id'] ?>, 'approve', '<?= htmlspecialchars($item['firstName']) ?>')">
                <i class="fas fa-check"></i> Approve
              </button>
              <button class="btn btn-danger" onclick="openVerifyModal('dean_list', <?= $item['list_id'] ?>, 'reject', '<?= htmlspecialchars($item['firstName']) ?>')">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No pending Dean's List verifications</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Document Verifications -->
      <div class="content-card">
        <h2><i class="fas fa-file-alt"></i> Documents Pending Verification</h2>
        
        <?php if($docs_pending->num_rows > 0): ?>
          <?php while($doc = $docs_pending->fetch_assoc()): ?>
          <div class="verification-item">
            <div class="item-header">
              <div>
                <div class="item-title"><?= htmlspecialchars($doc['doc_name']) ?></div>
                <div class="item-subtitle">
                  Uploaded by: <?= htmlspecialchars($doc['lastName'] . ', ' . $doc['firstName']) ?> 
                  (<?= htmlspecialchars($doc['student_number']) ?>)
                </div>
              </div>
              <span class="badge badge-pending">Pending</span>
            </div>
            
            <div class="item-details">
              <div class="detail-item">
                <span class="detail-label">Document Type</span>
                <span class="detail-value"><?= htmlspecialchars($doc['doc_type']) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Upload Date</span>
                <span class="detail-value"><?= date('M d, Y', strtotime($doc['upload_date'])) ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">File Size</span>
                <span class="detail-value"><?= number_format($doc['file_size'] / 1024, 2) ?> KB</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Category</span>
                <span class="detail-value" style="text-transform: capitalize;">
                  <?= str_replace('_', ' ', htmlspecialchars($doc['related_type'])) ?>
                </span>
              </div>
            </div>
            
            <div class="action-buttons">
              <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-info">
                <i class="fas fa-eye"></i> View Document
              </a>
              <button class="btn btn-success" onclick="openVerifyModal('document', <?= $doc['doc_id'] ?>, 'approve', '<?= htmlspecialchars($doc['doc_name']) ?>')">
                <i class="fas fa-check"></i> Approve
              </button>
              <button class="btn btn-danger" onclick="openVerifyModal('document', <?= $doc['doc_id'] ?>, 'reject', '<?= htmlspecialchars($doc['doc_name']) ?>')">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No pending document verifications</p>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
  
  <!-- Verification Modal -->
  <div id="verifyModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Confirm Action</h2>
        <button class="close-btn" onclick="closeModal()">×</button>
      </div>
      <form method="POST">
        <input type="hidden" name="type" id="verify_type">
        <input type="hidden" name="id" id="verify_id">
        <input type="hidden" name="action" id="verify_action">
        
        <p id="modalMessage" style="margin-bottom: 1.5rem; color: #4a5568;"></p>
        
        <div class="form-group">
          <label>Remarks/Comments</label>
          <textarea name="remarks" id="verify_remarks" placeholder="Enter any remarks or feedback..."></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem;">
          <button type="submit" name="verify_action" class="btn btn-success" id="confirmBtn">
            <i class="fas fa-check"></i> Confirm
          </button>
          <button type="button" class="btn btn-danger" onclick="closeModal()">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    function openVerifyModal(type, id, action, name) {
      document.getElementById('verify_type').value = type;
      document.getElementById('verify_id').value = id;
      document.getElementById('verify_action').value = action;
      
      const actionText = action == 'approve' ? 'approve' : 'reject';
      let typeText = '';
      
      if (type == 'dean_list') {
        typeText = "Dean's List entry for";
      } else if (type == 'scholarship') {
        typeText = "scholarship application for";
      } else {
        typeText = 'document';
      }
      
      document.getElementById('modalTitle').textContent = action == 'approve' ? 'Approve Verification' : 'Reject Verification';
      document.getElementById('modalMessage').textContent = `Are you sure you want to ${actionText} this ${typeText} ${name}?`;
      
      const confirmBtn = document.getElementById('confirmBtn');
      confirmBtn.className = action == 'approve' ? 'btn btn-success' : 'btn btn-danger';
      confirmBtn.innerHTML = action == 'approve' ? '<i class="fas fa-check"></i> Confirm Approval' : '<i class="fas fa-times"></i> Confirm Rejection';
      
      document.getElementById('verifyModal').classList.add('active');
    }
    
    function closeModal() {
      document.getElementById('verifyModal').classList.remove('active');
      document.getElementById('verify_remarks').value = '';
    }
    
    // Close modal when clicking outside
    document.getElementById('verifyModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  </script>
</body>
</html>

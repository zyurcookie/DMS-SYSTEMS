<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_document'])) {
    $doc_id = $_POST['doc_id'];
    $action = $_POST['action'];
    $comments = $_POST['comments'] ?? '';
    
    $status = ($action == 'approve') ? 'Approved' : 'Rejected';
    
    $sql = "UPDATE document SET status=?, comments=?, reviewed_by=?, review_date=NOW() WHERE doc_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $status, $comments, $_SESSION['user_id'], $doc_id);
    
    if ($stmt->execute()) {
        $successMessage = "Document " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
        
        // Log audit trail
        $audit_sql = "INSERT INTO audit_trail (user_id, action, table_name, record_id, timestamp) 
                      VALUES (?, 'Document Verification', 'document', ?, NOW())";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("ii", $_SESSION['user_id'], $doc_id);
        $audit_stmt->execute();
    } else {
        $errorMessage = "Error updating document status.";
    }
}

// Handle bulk archive
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_archive'])) {
    $selected_docs = $_POST['selected_docs'] ?? [];
    
    if (count($selected_docs) > 0) {
        $ids = implode(',', array_map('intval', $selected_docs));
        $sql = "UPDATE document SET status='Archived', review_date=NOW() WHERE doc_id IN ($ids)";
        if ($conn->query($sql)) {
            $successMessage = count($selected_docs) . " document(s) archived successfully!";
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? 'Pending';
$doc_type_filter = $_GET['doc_type'] ?? '';
$related_type_filter = $_GET['related_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT d.*, p.firstName, p.lastName, p.student_number
        FROM document d
        INNER JOIN profile p ON d.student_id = p.user_id
        WHERE 1=1";

if ($status_filter) $sql .= " AND d.status = '$status_filter'";
if ($doc_type_filter) $sql .= " AND d.doc_type = '$doc_type_filter'";
if ($related_type_filter) $sql .= " AND d.related_type = '$related_type_filter'";
if ($search) $sql .= " AND (p.firstName LIKE '%$search%' OR p.lastName LIKE '%$search%' OR p.student_number LIKE '%$search%' OR d.doc_name LIKE '%$search%')";

$sql .= " ORDER BY d.upload_date DESC";
$documents = $conn->query($sql);

// Get document types for filter
$doc_types = $conn->query("SELECT DISTINCT doc_type FROM document ORDER BY doc_type");

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) as archived
    FROM document";
$stats = $conn->query($stats_sql)->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Document Management</title>
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
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .document-grid {
      display: grid;
      gap: 1rem;
      padding: 1.5rem;
    }
    .document-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.5rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      transition: all 0.3s ease;
    }
    .document-item:hover {
      border-color: #059669;
      box-shadow: 0 4px 15px rgba(5, 150, 105, 0.1);
    }
    .doc-left {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex: 1;
    }
    .doc-checkbox {
      width: 18px;
      height: 18px;
      cursor: pointer;
    }
    .doc-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      background: #e6f7f1;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #059669;
      font-size: 1.5rem;
    }
    .doc-info {
      flex: 1;
    }
    .doc-name {
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 0.25rem;
      font-size: 1rem;
    }
    .doc-meta {
      font-size: 0.875rem;
      color: #718096;
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .doc-actions {
      display: flex;
      gap: 0.5rem;
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
    .badge-approved {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-rejected {
      background: #fee2e2;
      color: #991b1b;
    }
    .badge-archived {
      background: #e2e8f0;
      color: #475569;
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
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e2e8f0;
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
        <a href="registrar_documents.php" class="nav-item active">
          <i class="fas fa-folder-open"></i> Documents
        </a>
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-folder-open"></i> Document Management</h1>
      
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
          <h3>Total Documents</h3>
          <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending Review</h3>
          <div class="stat-value"><?= number_format($stats['pending']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Approved</h3>
          <div class="stat-value"><?= number_format($stats['approved']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Rejected</h3>
          <div class="stat-value"><?= number_format($stats['rejected']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Archived</h3>
          <div class="stat-value"><?= number_format($stats['archived']) ?></div>
        </div>
      </div>
      
      <div class="toolbar">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search documents or students..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select id="statusFilter">
          <option value="">All Status</option>
          <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
          <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="Archived" <?= $status_filter == 'Archived' ? 'selected' : '' ?>>Archived</option>
        </select>
        <select id="docTypeFilter">
          <option value="">All Types</option>
          <?php while($type = $doc_types->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($type['doc_type']) ?>" <?= $doc_type_filter == $type['doc_type'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($type['doc_type']) ?>
          </option>
          <?php endwhile; ?>
        </select>
        <select id="relatedTypeFilter">
          <option value="">All Categories</option>
          <option value="enrollment" <?= $related_type_filter == 'enrollment' ? 'selected' : '' ?>>Enrollment</option>
          <option value="dean_list" <?= $related_type_filter == 'dean_list' ? 'selected' : '' ?>>Dean's List</option>
          <option value="scholarship" <?= $related_type_filter == 'scholarship' ? 'selected' : '' ?>>Scholarship</option>
        </select>
        <button class="btn btn-warning" onclick="bulkArchive()">
          <i class="fas fa-archive"></i> Archive Selected
        </button>
      </div>
      
      <div class="content-card">
        <form method="POST" id="documentForm">
          <div class="document-grid">
            <?php if($documents && $documents->num_rows > 0): ?>
              <?php while($doc = $documents->fetch_assoc()): ?>
              <div class="document-item">
                <div class="doc-left">
                  <input type="checkbox" name="selected_docs[]" value="<?= $doc['doc_id'] ?>" class="doc-checkbox">
                  <div class="doc-icon">
                    <i class="fas fa-file-pdf"></i>
                  </div>
                  <div class="doc-info">
                    <div class="doc-name"><?= htmlspecialchars($doc['doc_name']) ?></div>
                    <div class="doc-meta">
                      <span><i class="fas fa-user"></i> <?= htmlspecialchars($doc['student_number']) ?> - <?= htmlspecialchars($doc['lastName'] . ', ' . $doc['firstName']) ?></span>
                      <span><i class="fas fa-tag"></i> <?= htmlspecialchars($doc['doc_type']) ?></span>
                      <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($doc['upload_date'])) ?></span>
                      <span><i class="fas fa-hdd"></i> <?= number_format($doc['file_size'] / 1024, 2) ?> KB</span>
                    </div>
                  </div>
                </div>
                <div class="doc-actions">
                  <span class="badge badge-<?= strtolower($doc['status']) ?>">
                    <?= htmlspecialchars($doc['status']) ?>
                  </span>
                  <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> View
                  </a>
                  <?php if($doc['status'] == 'Pending'): ?>
                  <button type="button" class="btn btn-success btn-sm" onclick="verifyDocument(<?= $doc['doc_id'] ?>, 'approve', '<?= htmlspecialchars($doc['doc_name']) ?>')">
                    <i class="fas fa-check"></i> Approve
                  </button>
                  <button type="button" class="btn btn-danger btn-sm" onclick="verifyDocument(<?= $doc['doc_id'] ?>, 'reject', '<?= htmlspecialchars($doc['doc_name']) ?>')">
                    <i class="fas fa-times"></i> Reject
                  </button>
                  <?php endif; ?>
                </div>
              </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div style="text-align: center; padding: 3rem; color: #a0aec0;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                <p>No documents found</p>
              </div>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </main>
  </div>
  
  <!-- Verification Modal -->
  <div id="verifyModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Verify Document</h2>
        <button class="close-btn" onclick="closeModal()">×</button>
      </div>
      <form method="POST">
        <input type="hidden" name="doc_id" id="verify_doc_id">
        <input type="hidden" name="action" id="verify_action">
        
        <p id="modalMessage" style="margin-bottom: 1.5rem; color: #4a5568;"></p>
        
        <div class="form-group">
          <label>Comments/Feedback</label>
          <textarea name="comments" id="verify_comments" placeholder="Enter comments or reason..."></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem;">
          <button type="submit" name="verify_document" class="btn btn-success" id="confirmBtn">
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
    function applyFilters() {
      const search = document.getElementById('searchInput').value;
      const status = document.getElementById('statusFilter').value;
      const docType = document.getElementById('docTypeFilter').value;
      const relatedType = document.getElementById('relatedTypeFilter').value;
      window.location.href = `registrar_documents.php?search=${search}&status=${status}&doc_type=${docType}&related_type=${relatedType}`;
    }
    
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('docTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('relatedTypeFilter').addEventListener('change', applyFilters);
    
    function verifyDocument(docId, action, docName) {
      document.getElementById('verify_doc_id').value = docId;
      document.getElementById('verify_action').value = action;
      
      const actionText = action == 'approve' ? 'approve' : 'reject';
      document.getElementById('modalTitle').textContent = action == 'approve' ? 'Approve Document' : 'Reject Document';
      document.getElementById('modalMessage').textContent = `Are you sure you want to ${actionText} "${docName}"?`;
      
      const confirmBtn = document.getElementById('confirmBtn');
      confirmBtn.className = action == 'approve' ? 'btn btn-success' : 'btn btn-danger';
      confirmBtn.innerHTML = action == 'approve' ? '<i class="fas fa-check"></i> Confirm Approval' : '<i class="fas fa-times"></i> Confirm Rejection';
      
      document.getElementById('verifyModal').classList.add('active');
    }
    
    function closeModal() {
      document.getElementById('verifyModal').classList.remove('active');
      document.getElementById('verify_comments').value = '';
    }
    
    function bulkArchive() {
      const selected = document.querySelectorAll('.doc-checkbox:checked');
      if (selected.length === 0) {
        alert('Please select at least one document to archive.');
        return;
      }
      
      if (confirm(`Archive ${selected.length} document(s)?`)) {
        const form = document.getElementById('documentForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_archive';
        input.value = '1';
        form.appendChild(input);
        form.submit();
      }
    }
  </script>
</body>
</html>
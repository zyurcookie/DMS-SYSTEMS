<?php
session_start();
include('include/db.php');

// Restrict access to Students only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Student') {
    header('Location: landing.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$successMessage = "";
$errorMessage = "";

// Get student profile and details
$student = getStudentDetails($conn, $user_id, 'user_id');

if (!$student) {
    $_SESSION['errorMessage'] = "Student profile not found.";
    header('Location: landing.php');
    exit();
}

$stud_id = $student['stud_id'];

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    $doc_name = $_POST['doc_name'];
    $file_name = $_POST['file_name'];
    $doc_type = $_POST['doc_type'];
    $doc_desc = $_POST['doc_desc'];
    $related_type = $_POST['related_type'] ?? 'general';
    
    if ($_FILES['document_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('doc_' . $stud_id . '_') . '.' . $file_extension;
        $file_path = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
            $file_size = $_FILES['document_file']['size'];
            
            $stmt = $conn->prepare("INSERT INTO document (student_id, stud_id, doc_name, file_name, doc_type, doc_desc, file_path, file, file_size, related_type, status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $created_by = $student['user_name'];
            $stmt->bind_param("iissssssis", $user_id, $stud_id, $doc_name, $file_name, $doc_type, $doc_desc, $file_path, $file_path, $file_size, $related_type, $created_by);
            
            if ($stmt->execute()) {
                $successMessage = "Document uploaded successfully!";
                logAudit($conn, $user_id, 'Uploaded Document', 'document', $conn->insert_id);
            } else {
                $errorMessage = "Database insert failed: " . $stmt->error;
            }
        } else {
            $errorMessage = "File upload failed.";
        }
    } else {
        $errorMessage = "File upload error: " . $_FILES['document_file']['error'];
    }
}

// Get current enrollment
$enrollment = null;
$enrollment_sql = "SELECT * FROM student_enrollment 
                   WHERE student_id = ? AND status = 'Enrolled' 
                   ORDER BY enrollment_date DESC LIMIT 1";
$stmt = $conn->prepare($enrollment_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enrollment_result = $stmt->get_result();
if ($enrollment_result->num_rows > 0) {
    $enrollment = $enrollment_result->fetch_assoc();
}

// Get Dean's List records
$deans_list_records = getDeansListRecords($conn, $user_id);

// Get scholarship applications
$scholarship_applications = getScholarshipApplications($conn, $user_id);

// Get uploaded documents
$documents = getStudentDocuments($conn, $user_id);

// Calculate eligibility percentage
$eligibility_percentage = calculateEligibilityPercentage($student);

$user_name = $student['firstName'] . ' ' . $student['lastName'];
$user_initial = substr($student['firstName'], 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FEU Roosevelt - Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <style>
        .eligibility-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .progress-ring {
            width: 150px;
            height: 150px;
            margin: 0 auto;
        }
        
        .criteria-item {
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .criteria-item.approved {
            border-left: 4px solid #10b981;
        }
        
        .criteria-item.pending {
            border-left: 4px solid #f59e0b;
        }
        
        .criteria-item.declined {
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <?php include('include/header.php'); ?>
    
    <div class="layout-wrapper">
        <?php include('include/sidebar.php'); ?>
        
        <div class="main-content flex-grow-1">
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <h1 class="mb-4">Welcome, <?= htmlspecialchars($student['firstName']) ?>!</h1>
            
            <!-- Student Info Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="fas fa-user me-2"></i>Personal Information</h6>
                            <p class="mb-1"><strong>Student Number:</strong> <?= htmlspecialchars($student['student_number']) ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                            <p class="mb-0"><strong>Contact:</strong> <?= htmlspecialchars($student['contactNumber'] ?? 'Not set') ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                            <?php if($enrollment): ?>
                            <p class="mb-1"><strong>Year Level:</strong> <?= htmlspecialchars($enrollment['year_level']) ?></p>
                            <p class="mb-1"><strong>Course:</strong> <?= htmlspecialchars($enrollment['course']) ?></p>
                            <p class="mb-0"><strong>Academic Year:</strong> <?= htmlspecialchars($enrollment['academic_year']) ?></p>
                            <?php else: ?>
                            <p class="text-muted">No active enrollment found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="fas fa-star me-2"></i>Dean's List Status</h6>
                            <?php if(count($deans_list_records) > 0): 
                                $latest_dl = $deans_list_records[0];
                            ?>
                            <p class="mb-1">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?= $latest_dl['status'] == 'Verified' ? 'success' : 'warning' ?>">
                                    <?= htmlspecialchars($latest_dl['status']) ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>GPA:</strong> <?= number_format($latest_dl['gpa'], 2) ?></p>
                            <p class="mb-0"><strong>Period:</strong> <?= htmlspecialchars($latest_dl['academic_year']) ?></p>
                            <?php else: ?>
                            <p class="text-muted">Not yet on Dean's List</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Eligibility Section -->
            <div class="eligibility-card">
                <h4 class="text-center mb-4"><i class="fas fa-chart-pie me-2"></i>Dean's List Eligibility Status</h4>
                
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <div class="progress-ring mb-3">
                            <svg width="150" height="150">
                                <circle cx="75" cy="75" r="65" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="10"/>
                                <circle cx="75" cy="75" r="65" fill="none" stroke="#FFD700" stroke-width="10" 
                                        stroke-dasharray="<?= $eligibility_percentage * 4.08 ?> 408" 
                                        stroke-linecap="round" transform="rotate(-90 75 75)"/>
                            </svg>
                            <div style="margin-top: -120px;">
                                <h1 class="mb-0"><?= $eligibility_percentage ?>%</h1>
                                <p class="mb-0">Eligible</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="criteria-item <?= !is_null($student['qpa']) && $student['qpa'] >= 3.5 ? 'approved' : 'pending' ?>">
                            <span>QPA ≥ 3.50</span>
                            <?= getStatusBadge($student['qpa'], 3.5) ?>
                        </div>
                        
                        <div class="criteria-item <?= !is_null($student['min_grade']) && $student['min_grade'] > 3.0 ? 'approved' : 'pending' ?>">
                            <span>No grade lower than 3.00</span>
                            <?= getStatusBadge($student['min_grade'], 3.0, true) ?>
                        </div>
                        
                        <div class="criteria-item <?= $student['is_regular_student'] == 1 ? 'approved' : 'pending' ?>">
                            <span>Regular Student</span>
                            <?= getStatusBadge($student['is_regular_student'], 1) ?>
                        </div>
                        
                        <div class="criteria-item <?= $student['has_incomplete_grade'] == 0 ? 'approved' : 'declined' ?>">
                            <span>No Incomplete Grades</span>
                            <?= getStatusBadge($student['has_incomplete_grade'], 0) ?>
                        </div>
                        
                        <div class="criteria-item <?= $student['has_dropped_or_failed'] == 0 ? 'approved' : 'declined' ?>">
                            <span>No Dropped/Failed Subjects</span>
                            <?= getStatusBadge($student['has_dropped_or_failed'], 0) ?>
                        </div>
                        
                        <div class="criteria-item <?= $student['violated_rules'] == 0 ? 'approved' : 'declined' ?>">
                            <span>No Rule Violations</span>
                            <?= getStatusBadge($student['violated_rules'], 0) ?>
                        </div>
                        
                        <div class="criteria-item <?= !is_null($student['attendance_percent']) && $student['attendance_percent'] >= 80 ? 'approved' : 'pending' ?>">
                            <span>Attendance ≥ 80%</span>
                            <?= getStatusBadge($student['attendance_percent']) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Upload & List -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Documents</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Document Name</label>
                                    <input type="text" name="doc_name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">File Name</label>
                                    <input type="text" name="file_name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Document Type</label>
                                    <select name="doc_type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="Grades">Grades</option>
                                        <option value="Certificate">Certificate</option>
                                        <option value="ID">ID</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="related_type" class="form-select" required>
                                        <option value="general">General</option>
                                        <option value="dean_list">Dean's List</option>
                                        <option value="scholarship">Scholarship</option>
                                        <option value="enrollment">Enrollment</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="doc_desc" class="form-control" rows="2"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Upload Document</label>
                                    <input type="file" name="document_file" class="form-control" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-2"></i>Upload
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>My Documents</h5>
                        </div>
                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                            <?php if (!empty($documents)): ?>
                            <div class="list-group">
                                <?php foreach ($documents as $doc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($doc['doc_name']) ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($doc['doc_type']) ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($doc['upload_date'])) ?>
                                            </p>
                                            <?php if ($doc['doc_desc']): ?>
                                            <p class="mb-1 small"><?= htmlspecialchars($doc['doc_desc']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end ms-2">
                                            <span class="badge bg-<?= 
                                                $doc['status'] == 'Approved' ? 'success' : 
                                                ($doc['status'] == 'Pending' ? 'warning' : 'danger') 
                                            ?> mb-2">
                                                <?= htmlspecialchars($doc['status']) ?>
                                            </span>
                                            <?php if (!empty($doc['file_path'])): ?>
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center py-4">No documents uploaded yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Scholarship Applications -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-award me-2"></i>My Scholarship Applications</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($scholarship_applications)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Academic Year</th>
                                    <th>Scholarship Type</th>
                                    <th>Status</th>
                                    <th>Application Date</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scholarship_applications as $sa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sa['academic_year']) ?></td>
                                    <td><?= htmlspecialchars($sa['scholarship_type']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $sa['status'] == 'Approved' ? 'success' : 
                                            ($sa['status'] == 'Under Review' ? 'info' : 'warning') 
                                        ?>">
                                            <?= htmlspecialchars($sa['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($sa['application_date'])) ?></td>
                                    <td><?= htmlspecialchars($sa['remarks'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-4">No scholarship applications yet</p>
                    <div class="text-center">
                        <a href="apply_scholarship.php" class="btn btn-warning">
                            <i class="fas fa-plus me-2"></i>Apply for Scholarship
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dean's List History -->
            <?php if (!empty($deans_list_records)): ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Dean's List History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                <?php foreach ($deans_list_records as $dl): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dl['academic_year']) ?></td>
                                    <td><?= htmlspecialchars($dl['semester']) ?></td>
                                    <td><?= htmlspecialchars($dl['year_level']) ?></td>
                                    <td><strong><?= number_format($dl['gpa'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $dl['status'] == 'Verified' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($dl['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
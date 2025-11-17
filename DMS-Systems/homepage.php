<?php
session_start();
include('include/db.php');

// Restrict access to Students only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Student') {
    header('Location: landing.php');
    exit();
}

// Fetch logged-in user's details
$email = $_SESSION['email'] ?? '';

// Ensure the email is set and valid
if (!empty($email)) {
    // Step 1: Get stud_id from the user table based on email
    $sql = "SELECT stud_id FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Step 2: Fetch the stud_id from the user table
    $stud_id = $user['stud_id'] ?? null;

    // If no stud_id is found, redirect to login
    if (!$stud_id) {
        header('Location: login.php');
        exit();
    }
}

// Initialize variables for counting files
$totalFiles = 0;
$pendingFiles = 0;
$approvedFiles = 0;
$declinedFiles = 0;
$documents = [];

// Step 3: Query to fetch the documents for the logged-in student using stud_id
$sql = "SELECT * FROM document WHERE stud_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $stud_id);
$stmt->execute();
$result = $stmt->get_result();

// Store the documents for the table display
while ($doc = $result->fetch_assoc()) {
    $documents[] = $doc;

    // Count total files
    $totalFiles++;

    // Count files by status
    if ($doc['status'] == 'Pending') {
        $pendingFiles++;
    } elseif ($doc['status'] == 'Approved') {
        $approvedFiles++;
    } elseif ($doc['status'] == 'Declined') {
        $declinedFiles++;
    }
}

// Step 4: Fetch student eligibility data
$studentData = null;
$sql = "SELECT * FROM student WHERE stud_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $stud_id);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

function getStatus($value, $expected = null, $isLessThan = false) {
    if (is_null($value)) {
        return '<span class="text-warning">Pending</span>';
    }

    if (!is_null($expected)) {
        if ($isLessThan) {
            return $value > $expected 
                ? '<span class="text-success">Approved</span>' 
                : '<span class="text-danger">Declined</span>';
        } else {
            return $value == $expected || $value >= $expected
                ? '<span class="text-success">Approved</span>'
                : '<span class="text-danger">Declined</span>';
        }
    }

    // For special case: attendance_percent
    return $value >= 80 
        ? '<span class="text-success">Approved</span>' 
        : '<span class="text-danger">Declined</span>';
}

function calculateEligibilityPercentage($data) {
    $totalCriteria = 8;
    $approved = 0;

    if (!is_null($data['qpa']) && $data['qpa'] >= 3.5) $approved++;
    if (!is_null($data['min_grade']) && $data['min_grade'] > 3.0) $approved++;
    if (!is_null($data['is_regular_student']) && $data['is_regular_student'] == 1) $approved++;
    if (!is_null($data['took_only_curriculum_courses']) && $data['took_only_curriculum_courses'] == 1) $approved++;
    if (!is_null($data['has_incomplete_grade']) && $data['has_incomplete_grade'] == 0) $approved++;
    if (!is_null($data['has_dropped_or_failed']) && $data['has_dropped_or_failed'] == 0) $approved++;
    if (!is_null($data['violated_rules']) && $data['violated_rules'] == 0) $approved++;
    if (!is_null($data['attendance_percent']) && $data['attendance_percent'] >= 80) $approved++;

    return intval(($approved / $totalCriteria) * 100);
}

// Calculate eligibility percentage only if data exists
$eligibilityPercentage = 0;
if ($studentData) {
    $eligibilityPercentage = calculateEligibilityPercentage($studentData);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    $stud_id = $_POST['stud_id'];
    $file_name = $_POST['file_name'];
    $doc_type = $_POST['doc_type'];
    $doc_desc = $_POST['doc_desc'];
    $created_by = $_POST['created_by'];

    // Handle file upload
    $uploadDir = 'uploads/';
    $fileTmpPath = $_FILES['document_file']['tmp_name'];
    $originalFileName = $_FILES['document_file']['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $newFileName = uniqid('doc_', true) . '.' . $fileExtension;
    $destinationPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destinationPath)) {
        $stmt = $conn->prepare("INSERT INTO document (stud_id, file_name, doc_type, doc_desc, file, status, created_by, uploaded_at) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())");
        $stmt->bind_param("isssss", $stud_id, $file_name, $doc_type, $doc_desc, $destinationPath, $created_by);
        if ($stmt->execute()) {
            $successMessage = "Document uploaded successfully.";
        } else {
            $errorMessage = "Database insert failed: " . $stmt->error;
        }
    } else {
        $errorMessage = "File upload failed.";
    }
}


?>

<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FEU Roosevelt Dean's List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="css/global.css">
</head>

<body>
    <?php include('include/header.php'); ?>
    <div class="layout-wrapper">
        <!-- Add padding to wrapper -->
        <?php include('include/sidebar.php'); ?>
        <div class="main-content flex-grow-1">
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show w-100" role="alert" id="registerAlert"
                style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1050;">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php elseif (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show w-100" role="alert" id="registerAlert"
                style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1050;">
                <?= $errorMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="d-flex">
                <div class="home-left-container">
                    <main class="content-area">
                        <section class="home-section">
                            <div class="mb-4 text-center">
                                <label for="eligibilityProgress" class="form-label fw-bold">Eligibility Progress:
                                    <?= $eligibilityPercentage ?>%</label>
                                <div class="progress" style="height: 30px; background-color: #f0f0f0;">
                                    <div class="progress-bar" role="progressbar"
                                        style="width: <?= $eligibilityPercentage ?>%;"
                                        aria-valuenow="<?= $eligibilityPercentage ?>" aria-valuemin="0"
                                        aria-valuemax="100"></div>
                                </div>
                            </div>
                        </section>
                        <section class="home-section">
                            <h2 class="text-center mb-4">Eligibility Status</h2>
                            <div class="row g-3 align-items-center">
                                <div class="col-4 text-end fw-bold"><?= getStatus($studentData['qpa'], 3.5) ?></div>
                                <div class="col-8 text-start">Quality Point Average (QPA) must be at least 3.50 (B+) in
                                    the proceeding semester</div>

                                <div class="col-4 text-end fw-bold">
                                    <?= getStatus($studentData['min_grade'], 3.00, true) ?></div>
                                <div class="col-8 text-start">Has no grade lower than 3.00</div>

                                <div class="col-4 text-end fw-bold">
                                    <?= getStatus($studentData['is_regular_student'], 1) ?></div>
                                <div class="col-8 text-start">Regular Student</div>

                                <div class="col-4 text-end fw-bold">
                                    <?= getStatus($studentData['took_only_curriculum_courses'], 1) ?></div>
                                <div class="col-8 text-start">Has taken only the course specified in his/her curriculum
                                    in the previous semester.</div>

                                <div class="col-4 text-end fw-bold">
                                    <?= getStatus($studentData['has_incomplete_grade'], 0) ?></div>
                                <div class="col-8 text-start">Has no grade of "Incomplete" upon encoding by the Faculty,
                                    nor a grade of "Dropped" or "Failed" in any subject including PATHFIT and NSTP.
                                </div>

                                <div class="col-4 text-end fw-bold">
                                    <?= getStatus($studentData['has_dropped_or_failed'], 0) ?></div>
                                <div class="col-8 text-start">No subject has been Dropped or Failed including PATHFIT
                                    and NSTP.</div>

                                <div class="col-4 text-end fw-bold"><?= getStatus($studentData['violated_rules'], 0) ?>
                                </div>
                                <div class="col-8 text-start">Has not violated any of the rules and regulations of the
                                    school</div>

                                <div class="col-4 text-end fw-bold"><?= getStatus($studentData['attendance_percent']) ?>
                                </div>
                                <div class="col-8 text-start">Attended at least 80% of the total face-to-face/Online
                                    class periods.</div>
                            </div>

                        </section>
                    </main>
                </div>

                <div class="home-right-container">
                    <main class="content-area">
                        <section class="home-section">
                            <h2 class="text-center mb-4">Upload Documents</h2>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="stud_id" value="<?= htmlspecialchars($stud_id) ?>">
                                <input type="hidden" name="created_by"
                                    value="<?= htmlspecialchars($user['user_name'] ?? 'Unknown') ?>">

                                <div class="mb-3">
                                    <label for="file_name" class="form-label">File Name</label>
                                    <input type="text" class="form-control" name="file_name" id="file_name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="doc_type" class="form-label">Document Type</label>
                                    <select class="form-select" name="doc_type" id="doc_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="Grades">Grades</option>
                                        <option value="Certificate">Certificate</option>
                                        <option value="ID">ID</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="doc_desc" class="form-label">Description</label>
                                    <textarea class="form-control" name="doc_desc" id="doc_desc" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="document_file" class="form-label">Upload Document</label>
                                    <input type="file" class="form-control" name="document_file" id="document_file"
                                        required>
                                </div>

                                <button type="submit" class="btn btn-primary">Upload</button>
                            </form>
                        </section>

                        <section class="home-section">
                            <h2 class="text-center mb-4">Uploaded Documents</h2>
                            <?php if (!empty($documents)): ?>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($doc['file_name']) ?></td>
                                        <td><?= htmlspecialchars($doc['doc_type']) ?></td>
                                        <td><?= htmlspecialchars($doc['doc_desc']) ?></td>
                                        <td><?= htmlspecialchars($doc['status']) ?></td>
                                        <td>
                                            <?php if (!empty($doc['file'])): ?>
                                            <a href="<?= htmlspecialchars($doc['file']) ?>" target="_blank"
                                                class="btn btn-sm btn-info">Open</a>
                                            <?php else: ?>
                                            <span class="text-muted">No file</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p class="text-muted">No documents uploaded yet.</p>
                            <?php endif; ?>

                        </section>
                    </main>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php
session_start();
include('include/db.php');

// Restrict access to Deans only (assuming role is stored in session)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Dean') {
    header('Location: landing.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $document_id = $_POST['document_id'] ?? null;
    $upload_dir = 'uploads/';
    $allowed_ext = ['pdf'];

    if (empty($student_id)) {
        $_SESSION['message'] = 'Student ID is required.';
        header('Location: upload.php');
        exit();
    }

    if (isset($_POST['delete']) && $document_id) {
        // Delete document
        $sql = "SELECT filename FROM document WHERE id = $document_id";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $file_path = $upload_dir . $row['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $conn->query("DELETE FROM document WHERE id = $document_id");

            // Audit trail
            $user_id = $_SESSION['user_id'] ?? 0;
            $audit_sql = "INSERT INTO audit_trail (user_id, action, document_id, timestamp) VALUES ($user_id, 'Deleted', $document_id, NOW())";
            $conn->query($audit_sql);

            $_SESSION['message'] = 'Document deleted successfully.';
        }
        header('Location: admin_dashboard.php');
        exit();
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = 'File upload error.';
        header('Location: upload.php');
        exit();
    }

    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['message'] = 'Only PDF files are allowed.';
        header('Location: upload.php');
        exit();
    }

    $filename = $student_id . '_' . time() . '_' . basename($file['name']);
    $target_file = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $student_id_esc = $conn->real_escape_string($student_id);
        $filename_esc = $conn->real_escape_string($filename);

        if ($document_id) {
            // Update existing document
            $sql = "UPDATE document SET student_id = '$student_id_esc', filename = '$filename_esc', status = 'Pending', created_at = NOW() WHERE id = $document_id";
            $action = 'Updated';
        } else {
            // Insert new document
            $sql = "INSERT INTO document (student_id, filename, status, created_at) VALUES ('$student_id_esc', '$filename_esc', 'Pending', NOW())";
            $action = 'Uploaded';
        }

        if ($conn->query($sql)) {
            // Audit trail
            $user_id = $_SESSION['user_id'] ?? 0;
            $last_id = $document_id ? $document_id : $conn->insert_id;
            $audit_sql = "INSERT INTO audit_trail (user_id, action, document_id, timestamp) VALUES ($user_id, '$action', $last_id, NOW())";
            $conn->query($audit_sql);

            $_SESSION['message'] = "File $action successfully.";
        } else {
            $_SESSION['message'] = 'Database error: ' . $conn->error;
        }
    } else {
        $_SESSION['message'] = 'Failed to move uploaded file.';
    }

    header('Location: admin_dashboard.php');
    exit();
}

$edit_document = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM document WHERE id = $edit_id");
    if ($res && $res->num_rows > 0) {
        $edit_document = $res->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Upload/Edit Document - Dean's Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <header class="bg-white shadow p-4 flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800"><?= $edit_document ? 'Edit' : 'Upload' ?> Eligibility Document</h1>
        <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800 font-medium">Back to Dashboard</a>
    </header>
    <main class="p-6 max-w-lg mx-auto bg-white rounded shadow mt-6">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <form action="upload.php" method="post" enctype="multipart/form-data" class="space-y-4">
            <?php if ($edit_document): ?>
                <input type="hidden" name="document_id" value="<?= $edit_document['id'] ?>" />
            <?php endif; ?>
            <div>
                <label for="student_id" class="block text-gray-700 font-semibold mb-1">Student ID</label>
                <input type="text" id="student_id" name="student_id" required value="<?= htmlspecialchars($edit_document['student_id'] ?? '') ?>" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
                <label for="file" class="block text-gray-700 font-semibold mb-1"><?= $edit_document ? 'Replace PDF Document' : 'Select PDF Document' ?></label>
                <input type="file" id="file" name="file" <?= $edit_document ? '' : 'required' ?> accept=".pdf" class="w-full" />
            </div>
            <div class="flex space-x-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition duration-200">
                    <i class="fas fa-upload mr-2"></i> <?= $edit_document ? 'Update Document' : 'Upload Document' ?>
                </button>
                <?php if ($edit_document): ?>
                <form action="upload.php" method="post" onsubmit="return confirm('Are you sure you want to delete this document?');" class="inline">
                    <input type="hidden" name="document_id" value="<?= $edit_document['id'] ?>" />
                    <input type="hidden" name="delete" value="1" />
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded transition duration-200">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Document
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </form>
    </main>
</body>
</html>

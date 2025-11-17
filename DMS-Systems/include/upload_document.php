<?php
session_start();
include('db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stud_id = $_POST['stud_id'];
    $file_name = $_POST['file_name'];
    $doc_type = $_POST['doc_type'];
    $doc_desc = $_POST['doc_desc'];
    $status = $_POST['status'] ?? 'Pending';
    $created_by = $_POST['created_by'];

    // Check if student exists
    $stmt = $conn->prepare("SELECT stud_id FROM student WHERE stud_id = ?");
    $stmt->bind_param("i", $stud_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Redirect back to admin dashboard with error message
        header("Location: ../admin_dashboard.php?error=student_not_found");
        exit();
    }

    // Handle file upload
    $target_dir = "../uploads/";
    $file_name_uploaded = basename($_FILES["document_file"]["name"]);
    $target_file = $target_dir . $file_name_uploaded;

    // Move the uploaded file
    if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $target_file)) {
        $sql = "INSERT INTO document (stud_id, file_name, doc_type, doc_desc, file, status, created_by, uploaded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssss", $stud_id, $file_name, $doc_type, $doc_desc, $target_file, $status, $created_by);

        if ($stmt->execute()) {
            header("Location: ../admin_dashboard.php?upload=success");
            exit();
        } else {
            echo "Database Error: " . $stmt->error;
        }
    } else {
        echo "File upload failed.";
    }
}

?>

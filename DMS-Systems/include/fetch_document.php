<?php
include('include/db.php');

if (isset($_GET['doc_id'])) {
    $doc_id = $_GET['doc_id'];

    // Fetch document details from the database
    $sql = "SELECT document.*, student.name 
            FROM document 
            JOIN student ON document.stud_id = student.stud_id 
            WHERE document.doc_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();

        // Display document details for the modal
        echo "<div><strong>Document Name:</strong> " . htmlspecialchars($document['file_name']) . "</div>";
        echo "<div><strong>Student Name:</strong> " . htmlspecialchars($document['name']) . "</div>";
        echo "<div><strong>Description:</strong> " . htmlspecialchars($document['doc_desc']) . "</div>";
        echo "<div><strong>Status:</strong> " . htmlspecialchars($document['status']) . "</div>";
        echo "<div><strong>Created By:</strong> " . htmlspecialchars($document['created_by']) . "</div>";
        echo "<div><strong>Created At:</strong> " . date('m/d/Y h:i A', strtotime($document['uploaded_at'])) . "</div>";
        echo "<div><strong>Last Updated:</strong> " . date('m/d/Y h:i A', strtotime($document['updated_at'])) . "</div>";
    } else {
        echo "<p>Document not found.</p>";
    }
} else {
    echo "<p>Invalid request.</p>";
}
?>

<?php
include('db.php');

if (isset($_GET['stud_id'])) {
    $stud_id = $_GET['stud_id'];

    // Fetch student details and documents
    $studentQuery = $conn->prepare("SELECT * FROM student WHERE stud_id = ?");
    $studentQuery->bind_param("s", $stud_id);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();

    if ($studentResult->num_rows > 0) {
        $student = $studentResult->fetch_assoc();

        // Fetch documents related to the student
        $documentQuery = $conn->prepare("SELECT * FROM documents WHERE stud_id = ?");
        $documentQuery->bind_param("s", $stud_id);
        $documentQuery->execute();
        $documentResult = $documentQuery->get_result();

        $documents = [];
        while ($doc = $documentResult->fetch_assoc()) {
            $documents[] = $doc;
        }

        // Return data as JSON
        echo json_encode([
            'eligibility_status' => $student['eligibility_status'],
            'requirements' => $student['requirements'],
            'documents' => $documents
        ]);
    } else {
        // No student found for the given stud_id
        echo json_encode(['error' => 'Student not found']);
    }
} else {
    // No stud_id provided in the request
    echo json_encode(['error' => 'Missing stud_id parameter']);
}
?>

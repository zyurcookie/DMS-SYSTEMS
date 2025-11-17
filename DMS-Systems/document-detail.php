<?php
session_start();
include('include/db.php');

// Restrict access to Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header('Location: landing.php');
    exit();
}

// Check if doc_id is provided
if (!isset($_GET['doc_id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

$doc_id = $_GET['doc_id'];

// Fetch document and student info
$sql = "SELECT document.*, student.name 
        FROM document 
        JOIN student ON document.stud_id = student.stud_id 
        WHERE document.doc_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // If no document found, redirect back
    header('Location: admin_dashboard.php');
    exit();
}

$document = $result->fetch_assoc();

// Fetch tags related to the document
$tagsSql = "SELECT tags.tag_name 
            FROM document_tags 
            JOIN tags ON document_tags.tag_id = tags.tag_id 
            WHERE document_tags.doc_id = ?";
$tagsStmt = $conn->prepare($tagsSql);
$tagsStmt->bind_param("i", $doc_id);
$tagsStmt->execute();
$tagsResult = $tagsStmt->get_result();

$tags = [];
while ($row = $tagsResult->fetch_assoc()) {
    $tags[] = $row['tag_name'];
}

// Fetch other documents by the same student except the current one
$stud_id = $document['stud_id'];
$otherDocsSql = "SELECT file_name, doc_type, uploaded_at, created_by 
                 FROM document 
                 WHERE stud_id = ? AND doc_id != ?";
$otherDocsStmt = $conn->prepare($otherDocsSql);
$otherDocsStmt->bind_param("ii", $stud_id, $doc_id);
$otherDocsStmt->execute();
$otherDocsResult = $otherDocsStmt->get_result();

$other_documents = [];
while ($row = $otherDocsResult->fetch_assoc()) {
    $other_documents[] = $row;
}

// Function to calculate relative time ago
function time_ago($datetime) {
    $now = new DateTime("now", new DateTimeZone('Asia/Singapore')); // Adjust timezone as needed
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Docu Dashboard</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
<?php include('include/header.php'); ?>
<div class="container">
<?php include('include/sidebar.php'); ?>
    <main>
      <div class="doc-header">
        <h1>Document <span><?= htmlspecialchars($document['name']) ?> Student File</span></h1>
        <div class="buttons">
          <button type="button"><i class="fas fa-download"></i> Download Zip</button>
          <button type="button"><i class="fas fa-edit"></i> Edit</button>
          <button type="button" class="delete"><i class="fas fa-trash-alt"></i> Delete</button>
        </div>
      </div>
      <div class="content">
        <section class="info-panel" aria-label="Document information">
          <div><span>Document Name:</span><span><?= htmlspecialchars($document['file_name']) ?></span></div>
          <div><span>Tags:</span>
              <span>
                  <?php 
                  foreach ($tags as $tag) {
                      echo '<span style="background:#7b2cbf;color:#fff;padding:2px 6px;border-radius:4px;font-size:9px;margin-right:4px;">' . htmlspecialchars($tag) . '</span>';
                  }
                  ?>
              </span>
          </div>
          <div><span>Description:</span><span><?= htmlspecialchars($document['doc_desc']) ?></span></div>
          <div><span>Status:</span><span class="status"><?= htmlspecialchars($document['status']) ?></span></div>
          <div><span>Created By:</span><span><?= htmlspecialchars($document['created_by']) ?></span></div>
          <div><span>Created At:</span><span><?= date('m/d/Y h:i A', strtotime($document['uploaded_at'])) ?></span></div>
          <div><span>Last Updated:</span><span><?= date('m/d/Y h:i A', strtotime($document['updated_at'])) ?></span></div>
        </section>
        <section class="files-panel" aria-label="Files, Verification, Activity, Permission tabs and content">
          <nav class="tabs" role="tablist">
            <button aria-current="page" role="tab" aria-selected="true" tabindex="0">Files</button>
            <button role="tab" aria-selected="false" tabindex="-1">Verification</button>
            <button role="tab" aria-selected="false" tabindex="-1">Activity</button>
            <button role="tab" aria-selected="false" tabindex="-1">Permission</button>
          </nav>
          <div class="files-grid" role="tabpanel">
            <?php if (count($other_documents) === 0): ?>
              <p>No other documents found for this student.</p>
            <?php else: ?>
              <?php foreach ($other_documents as $doc): ?>
                <article class="file-card" aria-label="<?= htmlspecialchars($doc['doc_type']) ?> file">
                  <div class="image-container">
                    <!-- You can replace the src with document-specific icons if available -->
                    <img src="https://storage.googleapis.com/a1aa/image/default-doc-icon.jpg" alt="<?= htmlspecialchars($doc['doc_type']) ?> icon" />
                  </div>
                  <div class="info">
                    <span class="uppercase"><?= htmlspecialchars($doc['doc_type']) ?></span>
                    <span><?= htmlspecialchars($doc['file_name']) ?></span>
                    <span style="font-size:10px;"><?= time_ago($doc['uploaded_at']) ?> by <?= htmlspecialchars($doc['created_by']) ?></span>
                    <i class="fas fa-info-circle" title="More info"></i>
                  </div>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <button class="add-files-btn" type="button">+ Add Files</button>
        </section>
      </div>
    </main>
  </div>
</body>
</html>

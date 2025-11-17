<?php
session_start();
include('include/db.php');

// Restrict access to Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header('Location: landing.php');
    exit();
}

// Fetch logged-in user's details
$email = $_SESSION['email'] ?? '';

if (!empty($email)) {
    $sql = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}
?>


<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FEU Roosevelt Dean's List</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include('include/header.php'); ?>
<div class="container">
<?php include('include/sidebar.php'); ?>
    <main>
    <div class="grid">
        <!-- Quick Upload -->
        <section class="grid-item">
        <h3>Quick Upload</h3>
        <input id="fileName" type="text" placeholder="File name" />
        <input id="fileInput" type="file" />
        <button id="uploadBtn" type="button">Upload</button>
        </section>

        <!-- Eligibility Verification -->
        <section id="eligibilitySection" class="grid-item">
        <h3>Eligibility Verification</h3>
        <div id="eligibilityStatus">78%</div>
        <div id="eligibilityText">Verified Students</div>
        </section>

        <!-- Verification Queue -->
        <section class="grid-item">
        <h3>Verification Queue</h3>
        <input type="text" readonly value="Ron Jacob Rodanilla" />
        <input type="text" readonly value="Jane Smith" />
        <input type="text" readonly value="John Doe" />
        </section>

        <!-- Recent Documents -->
        <section class="grid-item">
        <h3>Recent Documents</h3>
        <input type="text" readonly value="Student ID - Ron Jacob Rodanilla" />
        <input type="text" readonly value="Personal Statement - Jane Smith" />
        <input type="text" readonly value="Transcript - John Doe" />
        </section>
    </div>
    </main>
  </div>
</body>
</html>
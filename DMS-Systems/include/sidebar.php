<?php
include('db.php'); // Include database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

// Get user details if not already set
if (!isset($user)) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Get user role
$role = $_SESSION['role'] ?? 'Guest'; // Default to 'Guest' if role is not set

?>
<style>
aside {
    background-color: #d7defc;
    width: 224px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    color: #374151; /* gray-700 */
    font-size: 0.875rem; /* 14px */
    flex-shrink: 0;
    overflow-y: auto;
  }
  aside .profile {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
  }
  aside .profile .avatar {
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: linear-gradient(45deg, #3f51b5, #00bcd4);
    color: white;
    font-weight: 600;
    font-size: 0.75rem; /* 12px */
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
  }
  aside .search-wrapper {
    position: relative;
  }
  aside input[type="text"] {
    width: 100%;
    padding: 6px 32px 6px 12px;
    border: 1px solid #d1d5db; /* gray-300 */
    border-radius: 6px;
    font-size: 0.75rem; /* 12px */
    outline-offset: 2px;
    outline-color: #3f51b5;
  }
  aside .search-wrapper i {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af; /* gray-400 */
    font-size: 0.75rem; /* 12px */
    pointer-events: none;
  }
  aside nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  aside nav a,
  aside nav .settings {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    color: #374151; /* gray-700 */
    font-size: 0.875rem; /* 14px */
    user-select: none;
  }
  aside nav a:hover,
  aside nav .settings:hover {
    background-color: #c5cdfd;
    color: #3f51b5;
    font-weight: 600;
  }
  aside nav a.active {
    background-color: #c5cdfd;
    color: #3f51b5;
    font-weight: 600;
  }
  aside nav .settings {
    justify-content: space-between;
  }
  aside nav .settings i {
    font-size: 0.75rem; /* 12px */
    color: #374151;
  }
</style>

<aside>
  <div class="profile">
    <div class="avatar"><?= $user_initial ?></div>
    <div class="name"><?= htmlspecialchars($user_name) ?></div>
    <div class="role-badge"><?= $role ?></div>
  </div>
  
  <div class="search-wrapper">
    <input type="text" placeholder="Search..." />
    <i class="fas fa-search"></i>
  </div>

  <nav>
    <?php if ($role == 'Admin'): ?>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="students.php"><i class="fas fa-users"></i> Students</a>
        <a href="deans_list.php"><i class="fas fa-star"></i> Dean's List</a>
        <a href="scholarships.php"><i class="fas fa-award"></i> Scholarships</a>
        <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
        <a href="users.php"><i class="fas fa-user-cog"></i> Users</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    
    <?php elseif ($role == 'Dean'): ?>
        <a href="dean_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="deans_list_review.php"><i class="fas fa-star"></i> Dean's List Review</a>
        <a href="certificates.php"><i class="fas fa-certificate"></i> Certificates</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    
    <?php elseif ($role == 'Registrar'): ?>
        <a href="registrar_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="registrar_students.php"><i class="fas fa-user-graduate"></i> Students</a>
        <a href="registrar_enrollments.php"><i class="fas fa-clipboard-list"></i> Enrollments</a>
        <a href="registrar_grades.php"><i class="fas fa-edit"></i> Grades</a>
        <a href="registrar_deans_list.php"><i class="fas fa-star"></i> Dean's List</a>
        <a href="registrar_verification.php"><i class="fas fa-check-circle"></i> Verification</a>
        <a href="registrar_documents.php"><i class="fas fa-folder-open"></i> Documents</a>
        <a href="registrar_transcripts.php"><i class="fas fa-scroll"></i> Transcripts</a>
        <a href="registrar_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    
    <?php elseif ($role == 'Student'): ?>
        <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="my_documents.php"><i class="fas fa-folder"></i> My Documents</a>
        <a href="apply_scholarship.php"><i class="fas fa-award"></i> Apply Scholarship</a>
        <a href="my_applications.php"><i class="fas fa-clipboard-list"></i> My Applications</a>
        <a href="profile_settings.php"><i class="fas fa-user-cog"></i> Profile</a>
    <?php endif; ?>
    
    <div class="settings" onclick="window.location.href='settings.php'">
      <div><i class="fas fa-cog"></i> Settings</div>
      <i class="fas fa-chevron-down"></i>
    </div>
  </nav>
</aside>
<?php
session_start();
include('db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Registrar') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";
$importResults = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
    $import_type = $_POST['import_type'];
    $file = $_FILES['import_file'];
    
    // Validate file
    $allowed_extensions = ['csv', 'xlsx', 'xls'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $errorMessage = "Invalid file format. Please upload CSV or Excel file.";
    } else {
        $upload_path = "uploads/imports/" . time() . "_" . $file['name'];
        
        // Create directory if not exists
        if (!is_dir("uploads/imports")) {
            mkdir("uploads/imports", 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Process the file based on type
            if ($file_extension == 'csv') {
                $importResults = processCSV($upload_path, $import_type, $conn);
            } else {
                $importResults = processExcel($upload_path, $import_type, $conn);
            }
            
            if ($importResults['success'] > 0) {
                $successMessage = "Successfully imported {$importResults['success']} records!";
                if ($importResults['errors'] > 0) {
                    $successMessage .= " ({$importResults['errors']} errors)";
                }
            } else {
                $errorMessage = "Import failed. Please check your file format.";
            }
        } else {
            $errorMessage = "Failed to upload file.";
        }
    }
}

function processCSV($filepath, $import_type, $conn) {
    $results = ['success' => 0, 'errors' => 0, 'details' => []];
    
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        $row_number = 1;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            try {
                if ($import_type == 'students') {
                    importStudent($data, $conn);
                } elseif ($import_type == 'enrollments') {
                    importEnrollment($data, $conn);
                } elseif ($import_type == 'grades') {
                    importGrades($data, $conn);
                } elseif ($import_type == 'deans_list') {
                    importDeansList($data, $conn);
                }
                $results['success']++;
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = "Row $row_number: " . $e->getMessage();
            }
        }
        fclose($handle);
    }
    
    return $results;
}

function processExcel($filepath, $import_type, $conn) {
    // For Excel files, you would need PHPSpreadsheet library
    // For now, return instruction to convert to CSV
    return ['success' => 0, 'errors' => 1, 'details' => ['Please convert Excel to CSV format']];
}

function importStudent($data, $conn) {
    // Expected format: student_number, first_name, last_name, email, course, year_level
    $student_number = $data[0];
    $first_name = $data[1];
    $last_name = $data[2];
    $email = $data[3];
    $course = $data[4];
    $year_level = $data[5];
    
    // Check if user exists
    $check = $conn->query("SELECT user_id FROM user WHERE email='$email'");
    
    if ($check->num_rows == 0) {
        // Create user account
        $password = password_hash('FEU' . $student_number, PASSWORD_DEFAULT);
        $sql = "INSERT INTO user (email, password, role, status) VALUES ('$email', '$password', 'Student', 'Active')";
        $conn->query($sql);
        $user_id = $conn->insert_id;
        
        // Create profile
        $sql = "INSERT INTO profile (user_id, student_number, firstName, lastName, course, year_level) 
                VALUES ($user_id, '$student_number', '$first_name', '$last_name', '$course', '$year_level')";
        $conn->query($sql);
    } else {
        throw new Exception("Student with email $email already exists");
    }
}

function importEnrollment($data, $conn) {
    // Expected format: student_number, academic_year, semester, year_level, course, status
    $student_number = $data[0];
    $academic_year = $data[1];
    $semester = $data[2];
    $year_level = $data[3];
    $course = $data[4];
    $status = $data[5] ?? 'Enrolled';
    
    // Get student ID
    $result = $conn->query("SELECT user_id FROM profile WHERE student_number='$student_number'");
    if ($result->num_rows > 0) {
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Check if enrollment exists
        $check = $conn->query("SELECT * FROM student_enrollment 
                              WHERE student_id=$user_id 
                              AND academic_year='$academic_year' 
                              AND semester='$semester'");
        
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO student_enrollment (student_id, academic_year, semester, year_level, course, status, enrollment_date) 
                    VALUES ($user_id, '$academic_year', '$semester', '$year_level', '$course', '$status', NOW())";
            $conn->query($sql);
        } else {
            throw new Exception("Enrollment already exists for student $student_number");
        }
    } else {
        throw new Exception("Student not found: $student_number");
    }
}

function importGrades($data, $conn) {
    // Expected format: student_number, academic_year, semester, course_code, course_name, units, grade
    $student_number = $data[0];
    $academic_year = $data[1];
    $semester = $data[2];
    $course_code = $data[3];
    $course_name = $data[4];
    $units = $data[5];
    $grade = $data[6];
    
    // Get student ID
    $result = $conn->query("SELECT user_id FROM profile WHERE student_number='$student_number'");
    if ($result->num_rows > 0) {
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Insert grade
        $sql = "INSERT INTO grades (student_id, academic_year, semester, course_code, course_name, units, grade, encoded_by, encoded_date) 
                VALUES ($user_id, '$academic_year', '$semester', '$course_code', '$course_name', $units, '$grade', {$_SESSION['user_id']}, NOW())
                ON DUPLICATE KEY UPDATE grade='$grade', encoded_date=NOW()";
        $conn->query($sql);
    } else {
        throw new Exception("Student not found: $student_number");
    }
}

function importDeansList($data, $conn) {
    // Expected format: student_number, academic_year, semester, year_level, gpa
    $student_number = $data[0];
    $academic_year = $data[1];
    $semester = $data[2];
    $year_level = $data[3];
    $gpa = $data[4];
    
    // Get student ID
    $result = $conn->query("SELECT user_id FROM profile WHERE student_number='$student_number'");
    if ($result->num_rows > 0) {
        $user_id = $result->fetch_assoc()['user_id'];
        
        // Check if already exists
        $check = $conn->query("SELECT * FROM dean_list 
                              WHERE student_id=$user_id 
                              AND academic_year='$academic_year' 
                              AND semester='$semester'");
        
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO dean_list (student_id, academic_year, semester, year_level, gpa, status) 
                    VALUES ($user_id, '$academic_year', '$semester', '$year_level', $gpa, 'Pending')";
            $conn->query($sql);
        }
    } else {
        throw new Exception("Student not found: $student_number");
    }
}

$user_initial = substr($_SESSION['role'], 0, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FEU Roosevelt - Bulk Import</title>
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
    .import-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .import-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .import-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .import-card h3 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .import-card p {
      color: #718096;
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
    }
    .upload-area {
      border: 2px dashed #cbd5e0;
      border-radius: 10px;
      padding: 2rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #f7fafc;
    }
    .upload-area:hover {
      border-color: #059669;
      background: #d1fae5;
    }
    .upload-area i {
      font-size: 3rem;
      color: #059669;
      margin-bottom: 1rem;
    }
    .upload-area.dragging {
      border-color: #059669;
      background: #d1fae5;
    }
    .file-input {
      display: none;
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
      width: 100%;
      justify-content: center;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
    }
    .btn-secondary {
      background: #e2e8f0;
      color: #4a5568;
      font-size: 0.875rem;
      padding: 0.5rem 1rem;
    }
    .template-section {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .template-section h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .template-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 1rem;
    }
    .template-item {
      padding: 1rem;
      background: #f7fafc;
      border-radius: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 2px solid #e2e8f0;
    }
    .template-item:hover {
      border-color: #059669;
    }
    .template-info {
      flex: 1;
    }
    .template-name {
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 0.25rem;
    }
    .template-desc {
      font-size: 0.75rem;
      color: #718096;
    }
    .instructions {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .instructions h2 {
      font-size: 1.25rem;
      color: #2d3748;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .instructions ol {
      margin-left: 1.5rem;
      color: #4a5568;
    }
    .instructions li {
      margin-bottom: 0.75rem;
      line-height: 1.6;
    }
    .logout-btn {
      background: #fee2e2;
      color: #991b1b;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
    }
    .selected-file {
      margin-top: 1rem;
      padding: 0.75rem;
      background: #d1fae5;
      border-radius: 8px;
      font-size: 0.875rem;
      color: #065f46;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .error-details {
      margin-top: 1rem;
      padding: 1rem;
      background: #fff;
      border-radius: 8px;
      border-left: 4px solid #ef4444;
    }
    .error-details h4 {
      color: #991b1b;
      margin-bottom: 0.5rem;
    }
    .error-details ul {
      margin-left: 1.5rem;
      color: #4a5568;
      font-size: 0.875rem;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-graduation-cap"></i> FEU Roosevelt DMS - Registrar</h1>
    <div class="user-info">
      <div class="user-avatar"><?= $user_initial ?></div>
      <span><?= htmlspecialchars($_SESSION['role']) ?></span>
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
        <a href="registrar_documents.php" class="nav-item">
          <i class="fas fa-folder-open"></i> Documents
        </a>
        <a href="registrar_bulk_import.php" class="nav-item active">
          <i class="fas fa-file-import"></i> Bulk Import
        </a>
      </nav>
    </aside>
    
    <main class="main-content">
      <h1 class="page-title"><i class="fas fa-file-upload"></i> Bulk Import System</h1>
      
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
      
      <?php if(!empty($importResults['details'])): ?>
      <div class="error-details">
        <h4><i class="fas fa-exclamation-triangle"></i> Import Errors:</h4>
        <ul>
          <?php foreach($importResults['details'] as $detail): ?>
          <li><?= htmlspecialchars($detail) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      
      <!-- Download Templates -->
      <div class="template-section">
        <h2><i class="fas fa-download"></i> Download Templates</h2>
        <div class="template-grid">
          <div class="template-item">
            <div class="template-info">
              <div class="template-name">Students Template</div>
              <div class="template-desc">CSV template for student records</div>
            </div>
            <a href="templates/students_template.csv" class="btn btn-secondary" download>
              <i class="fas fa-download"></i> Download
            </a>
          </div>
          
          <div class="template-item">
            <div class="template-info">
              <div class="template-name">Enrollments Template</div>
              <div class="template-desc">CSV template for enrollments</div>
            </div>
            <a href="templates/enrollments_template.csv" class="btn btn-secondary" download>
              <i class="fas fa-download"></i> Download
            </a>
          </div>
          
          <div class="template-item">
            <div class="template-info">
              <div class="template-name">Grades Template</div>
              <div class="template-desc">CSV template for grades</div>
            </div>
            <a href="templates/grades_template.csv" class="btn btn-secondary" download>
              <i class="fas fa-download"></i> Download
            </a>
          </div>
          
          <div class="template-item">
            <div class="template-info">
              <div class="template-name">Dean's List Template</div>
              <div class="template-desc">CSV template for Dean's List</div>
            </div>
            <a href="templates/deans_list_template.csv" class="btn btn-secondary" download>
              <i class="fas fa-download"></i> Download
            </a>
          </div>
        </div>
      </div>
      
      <!-- Import Cards -->
      <div class="import-grid">
        <!-- Students Import -->
        <div class="import-card">
          <h3><i class="fas fa-user-graduate"></i> Import Students</h3>
          <p>Upload CSV file with student information to create new student accounts</p>
          <form method="POST" enctype="multipart/form-data" id="form_students">
            <input type="hidden" name="import_type" value="students">
            <div class="upload-area" onclick="document.getElementById('file_students').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload or drag and drop</p>
              <small>CSV files only</small>
            </div>
            <input type="file" name="import_file" id="file_students" class="file-input" accept=".csv" onchange="showFileName(this, 'students')">
            <div id="selected_students"></div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-upload"></i> Upload Students
            </button>
          </form>
        </div>
        
        <!-- Enrollments Import -->
        <div class="import-card">
          <h3><i class="fas fa-clipboard-list"></i> Import Enrollments</h3>
          <p>Upload CSV file with enrollment records for students</p>
          <form method="POST" enctype="multipart/form-data" id="form_enrollments">
            <input type="hidden" name="import_type" value="enrollments">
            <div class="upload-area" onclick="document.getElementById('file_enrollments').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload or drag and drop</p>
              <small>CSV files only</small>
            </div>
            <input type="file" name="import_file" id="file_enrollments" class="file-input" accept=".csv" onchange="showFileName(this, 'enrollments')">
            <div id="selected_enrollments"></div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-upload"></i> Upload Enrollments
            </button>
          </form>
        </div>
        
        <!-- Grades Import -->
        <div class="import-card">
          <h3><i class="fas fa-chart-line"></i> Import Grades</h3>
          <p>Upload CSV file with student grades and course information</p>
          <form method="POST" enctype="multipart/form-data" id="form_grades">
            <input type="hidden" name="import_type" value="grades">
            <div class="upload-area" onclick="document.getElementById('file_grades').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload or drag and drop</p>
              <small>CSV files only</small>
            </div>
            <input type="file" name="import_file" id="file_grades" class="file-input" accept=".csv" onchange="showFileName(this, 'grades')">
            <div id="selected_grades"></div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-upload"></i> Upload Grades
            </button>
          </form>
        </div>
        
        <!-- Dean's List Import -->
        <div class="import-card">
          <h3><i class="fas fa-star"></i> Import Dean's List</h3>
          <p>Upload CSV file with Dean's List qualifiers and GPA</p>
          <form method="POST" enctype="multipart/form-data" id="form_deans">
            <input type="hidden" name="import_type" value="deans_list">
            <div class="upload-area" onclick="document.getElementById('file_deans').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload or drag and drop</p>
              <small>CSV files only</small>
            </div>
            <input type="file" name="import_file" id="file_deans" class="file-input" accept=".csv" onchange="showFileName(this, 'deans')">
            <div id="selected_deans"></div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-upload"></i> Upload Dean's List
            </button>
          </form>
        </div>
      </div>
      
      <!-- Instructions -->
      <div class="instructions">
        <h2><i class="fas fa-info-circle"></i> Import Instructions</h2>
        <ol>
          <li><strong>Download the appropriate template</strong> from the templates section above</li>
          <li><strong>Fill in your data</strong> following the column headers exactly as shown in the template</li>
          <li><strong>Save as CSV format</strong> (not Excel format)</li>
          <li><strong>Upload the file</strong> using the corresponding import card</li>
          <li><strong>Review results</strong> - successful imports and any errors will be displayed</li>
        </ol>
        
        <h3 style="margin-top: 2rem; margin-bottom: 1rem; font-size: 1.1rem;">CSV Format Requirements:</h3>
        <ul style="margin-left: 1.5rem; color: #4a5568;">
          <li><strong>Students:</strong> student_number, first_name, last_name, email, course, year_level</li>
          <li><strong>Enrollments:</strong> student_number, academic_year, semester, year_level, course, status</li>
          <li><strong>Grades:</strong> student_number, academic_year, semester, course_code, course_name, units, grade</li>
          <li><strong>Dean's List:</strong> student_number, academic_year, semester, year_level, gpa</li>
        </ul>
      </div>
    </main>
  </div>
  
  <script>
    function showFileName(input, type) {
      const container = document.getElementById('selected_' + type);
      if (input.files.length > 0) {
        const fileName = input.files[0].name;
        container.innerHTML = `
          <div class="selected-file">
            <i class="fas fa-file-csv"></i>
            <span>${fileName}</span>
          </div>
        `;
      }
    }
    
    // Drag and drop functionality
    document.querySelectorAll('.upload-area').forEach(area => {
      area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragging');
      });
      
      area.addEventListener('dragleave', () => {
        area.classList.remove('dragging');
      });
      
      area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragging');
        
        const fileInput = area.parentElement.querySelector('.file-input');
        fileInput.files = e.dataTransfer.files;
        
        // Trigger change event
        const event = new Event('change');
        fileInput.dispatchEvent(event);
      });
    });
  </script>
</body>
</html>
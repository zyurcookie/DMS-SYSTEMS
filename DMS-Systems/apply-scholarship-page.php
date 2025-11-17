<?php
session_start();
include('include/db.php');

// Restrict access to Students only
check_role(['Student']);

$user_id = $_SESSION['user_id'];

// Get student profile and details
$sql_profile = "SELECT p.*, u.email, u.stud_id FROM profile p 
                INNER JOIN user u ON p.user_id = u.user_id 
                WHERE p.user_id = ?";
$stmt = $conn->prepare($sql_profile);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    $_SESSION['errorMessage'] = "Profile not found. Please contact administrator.";
    header('Location: student_dashboard.php');
    exit();
}

// Get current enrollment
$sql_enrollment = "SELECT * FROM student_enrollment 
                   WHERE student_id = ? AND status = 'Enrolled' 
                   ORDER BY enrollment_date DESC LIMIT 1";
$stmt = $conn->prepare($sql_enrollment);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();

// Get dean's list status
$sql_deans = "SELECT * FROM dean_list 
              WHERE student_id = ? AND status = 'Verified'
              ORDER BY academic_year DESC, semester DESC LIMIT 1";
$stmt = $conn->prepare($sql_deans);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deans_list = $stmt->get_result()->fetch_assoc();

// Get current academic settings
$current_year = get_setting('academic_year_current', '2024-2025');
$current_semester = get_setting('semester_current', '1st Semester');

// Check if already applied for current term
$sql_check = "SELECT * FROM scholarship_application 
              WHERE student_id = ? AND academic_year = ? AND semester = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("iss", $user_id, $current_year, $current_semester);
$stmt->execute();
$existing_application = $stmt->get_result()->fetch_assoc();

$successMessage = "";
$errorMessage = "";

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $academic_year = sanitize_input($_POST['academic_year']);
    $semester = sanitize_input($_POST['semester']);
    $scholarship_type = sanitize_input($_POST['scholarship_type']);
    $amount_requested = floatval($_POST['amount_requested'] ?? 0);
    
    // Validate inputs
    if (empty($academic_year) || empty($semester) || empty($scholarship_type)) {
        $errorMessage = "All fields are required.";
    } else {
        // Check if already applied for this term
        $sql_check = "SELECT * FROM scholarship_application 
                      WHERE student_id = ? AND academic_year = ? AND semester = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("iss", $user_id, $academic_year, $semester);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $errorMessage = "You have already applied for a scholarship in $academic_year $semester.";
        } else {
            // Check if on dean's list for dean's list scholarship
            $is_deans_scholar = false;
            if ($scholarship_type == "Dean's List Scholarship") {
                $sql_verify = "SELECT * FROM dean_list 
                              WHERE student_id = ? AND academic_year = ? AND semester = ? AND status = 'Verified'";
                $stmt_verify = $conn->prepare($sql_verify);
                $stmt_verify->bind_param("iss", $user_id, $academic_year, $semester);
                $stmt_verify->execute();
                
                if ($stmt_verify->get_result()->num_rows == 0) {
                    $errorMessage = "You must be on the Dean's List to apply for this scholarship.";
                } else {
                    $is_deans_scholar = true;
                }
            }
            
            if (empty($errorMessage)) {
                $sql = "INSERT INTO scholarship_application 
                        (student_id, academic_year, semester, scholarship_type, amount_requested, status, application_date, is_deans_list_scholar) 
                        VALUES (?, ?, ?, ?, ?, 'Submitted', NOW(), ?)";
                $stmt = $conn->prepare($sql);
                $status = 'Submitted';
                $stmt->bind_param("isssdi", $user_id, $academic_year, $semester, $scholarship_type, $amount_requested, $is_deans_scholar);
                
                if ($stmt->execute()) {
                    $app_id = $conn->insert_id;
                    
                    // Log audit
                    log_audit($user_id, 'Scholarship Application Submitted', 'scholarship_application', $app_id);
                    
                    // Create notification
                    create_notification($user_id, 'Scholarship Application Submitted', 
                        "Your scholarship application for $scholarship_type has been submitted successfully.", 
                        'success', 'scholarship', $app_id);
                    
                    $_SESSION['successMessage'] = "Scholarship application submitted successfully!";
                    header('Location: student_dashboard.php');
                    exit();
                } else {
                    $errorMessage = "Error submitting application. Please try again.";
                }
            }
        }
    }
}

$user_name = $student['firstName'] . ' ' . $student['lastName'];
$user_initial = substr($student['firstName'], 0, 1);

// Available scholarship types
$scholarship_types = [
    "Dean's List Scholarship" => [
        'description' => 'For students who achieved Dean\'s List status with GPA of 3.50 or higher.',
        'amount' => '₱30,000 - ₱50,000',
        'requirements' => ['Dean\'s List Status', 'GPA ≥ 3.50', 'Good Moral Character']
    ],
    "Academic Excellence Scholarship" => [
        'description' => 'For students with outstanding academic performance.',
        'amount' => '₱40,000 - ₱80,000',
        'requirements' => ['GPA ≥ 3.75', 'Leadership Activities', 'Recommendation Letter']
    ],
    "Merit-Based Scholarship" => [
        'description' => 'For students demonstrating exceptional abilities in academics or extracurricular activities.',
        'amount' => '₱20,000 - ₱60,000',
        'requirements' => ['GPA ≥ 3.00', 'Active Participation', 'Essay Submission']
    ],
    "Need-Based Scholarship" => [
        'description' => 'For students with demonstrated financial need.',
        'amount' => '₱25,000 - ₱75,000',
        'requirements' => ['Financial Documents', 'GPA ≥ 2.75', 'Personal Statement']
    ],
    "Leadership Scholarship" => [
        'description' => 'For students with proven leadership experience.',
        'amount' => '₱30,000 - ₱70,000',
        'requirements' => ['Leadership Position', 'GPA ≥ 3.25', 'Community Service']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FEU Roosevelt - Apply for Scholarship</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .back-btn, .logout-btn {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover, .logout-btn:hover {
            background: #cbd5e0;
        }
        
        .logout-btn {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .logout-btn:hover {
            background: #fecaca;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-title {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 1rem;
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
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .content-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .content-card h2 {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group .hint {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 0.5rem;
        }
        
        .scholarship-selector {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .scholarship-option {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .scholarship-option:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }
        
        .scholarship-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        
        .scholarship-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .scholarship-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .scholarship-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .scholarship-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        .scholarship-amount {
            font-size: 0.9rem;
            color: #667eea;
            font-weight: 600;
        }
        
        .scholarship-description {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 0.75rem;
        }
        
        .scholarship-requirements {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .requirement-tag {
            background: #f7fafc;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .info-card h3 {
            font-size: 1rem;
            color: #2d3748;
            margin-bottom: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #718096;
            font-size: 0.875rem;
        }
        
        .info-value {
            color: #2d3748;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-graduation-cap"></i>
            FEU Roosevelt Student Portal
        </h1>
        <div class="user-info">
            <a href="student
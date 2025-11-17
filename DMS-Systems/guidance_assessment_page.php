<?php
session_start();
include('db.php');

// Restrict access to Guidance only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Guidance') {
    header('Location: landing.php');
    exit();
}

$successMessage = "";
$errorMessage = "";

// Get application ID
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

if ($app_id <= 0) {
    header('Location: guidance_review.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $financial_need_score = intval($_POST['financial_need_score'] ?? 0);
    $character_score = intval($_POST['character_score'] ?? 0);
    $leadership_score = intval($_POST['leadership_score'] ?? 0);
    $academic_score = intval($_POST['academic_score'] ?? 0);
    $community_involvement = intval($_POST['community_involvement'] ?? 0);
    
    $financial_notes = trim($_POST['financial_notes'] ?? '');
    $character_notes = trim($_POST['character_notes'] ?? '');
    $leadership_notes = trim($_POST['leadership_notes'] ?? '');
    $overall_notes = trim($_POST['overall_notes'] ?? '');
    $overall_recommendation = $_POST['overall_recommendation'] ?? '';
    
    $guidance_user_id = $_SESSION['user_id'];
    
    // Calculate total score
    $total_score = $financial_need_score + $character_score + $leadership_score + $academic_score + $community_involvement;
    $average_score = $total_score / 5;
    
    try {
        // Check if assessment already exists
        $check_stmt = $conn->prepare("SELECT assessment_id FROM guidance_assessment WHERE app_id = ?");
        $check_stmt->bind_param("i", $app_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            // Update existing assessment
            $stmt = $conn->prepare("UPDATE guidance_assessment SET 
                financial_need_score = ?,
                character_score = ?,
                leadership_score = ?,
                academic_score = ?,
                community_involvement = ?,
                financial_notes = ?,
                character_notes = ?,
                leadership_notes = ?,
                overall_notes = ?,
                overall_recommendation = ?,
                total_score = ?,
                average_score = ?,
                assessed_by = ?,
                assessment_date = NOW()
                WHERE app_id = ?");
            $stmt->bind_param("iiiiisssssdiii", 
                $financial_need_score, $character_score, $leadership_score, 
                $academic_score, $community_involvement,
                $financial_notes, $character_notes, $leadership_notes, 
                $overall_notes, $overall_recommendation,
                $total_score, $average_score, $guidance_user_id, $app_id);
        } else {
            // Insert new assessment
            $stmt = $conn->prepare("INSERT INTO guidance_assessment 
                (app_id, financial_need_score, character_score, leadership_score, 
                academic_score, community_involvement, financial_notes, character_notes, 
                leadership_notes, overall_notes, overall_recommendation, 
                total_score, average_score, assessed_by, assessment_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiiisssssddii",
                $app_id, $financial_need_score, $character_score, $leadership_score,
                $academic_score, $community_involvement,
                $financial_notes, $character_notes, $leadership_notes,
                $overall_notes, $overall_recommendation,
                $total_score, $average_score, $guidance_user_id);
        }
        
        if ($stmt->execute()) {
            $successMessage = "Assessment saved successfully!";
        } else {
            $errorMessage = "Error saving assessment: " . $conn->error;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Fetch application details
$query = "
    SELECT sa.*, 
           p.name as student_name,
           p.student_number,
           p.course,
           p.year_level
    FROM scholarship_application sa
    LEFT JOIN profile p ON sa.stud_id = p.stud_id
    WHERE sa.app_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: guidance_review.php');
    exit();
}

$application = $result->fetch_assoc();
$stmt->close();

// Fetch existing assessment if any
$assessment_query = "SELECT * FROM guidance_assessment WHERE app_id = ?";
$assessment_stmt = $conn->prepare($assessment_query);
$assessment_stmt->bind_param("i", $app_id);
$assessment_stmt->execute();
$assessment = $assessment_stmt->get_result()->fetch_assoc();
$assessment_stmt->close();

$user_name = $_SESSION['user_name'] ?? 'Guidance Counselor';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Assessment - Guidance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #7c3aed;
            --primary-light: #8b5cf6;
            --background: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --hover-bg: #f7fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }
        
        .container-fluid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
        }
        
        .content-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .assessment-section {
            margin-bottom: 2.5rem;
            padding-bottom: 2.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .assessment-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .score-input-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .score-slider {
            flex: 1;
            height: 8px;
            border-radius: 4px;
            outline: none;
            -webkit-appearance: none;
            background: linear-gradient(to right, #ef4444 0%, #f59e0b 50%, #10b981 100%);
        }
        
        .score-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-purple);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
        }
        
        .score-slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-purple);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
        }
        
        .score-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-purple);
            min-width: 60px;
            text-align: center;
            background: var(--hover-bg);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-purple);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9375rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            color: white;
        }
        
        .alert {
            padding: 1rem 1.25rem;
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
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .score-legend {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .total-score-card {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-top: 2rem;
        }
        
        .total-score-value {
            font-size: 3rem;
            font-weight: 700;
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .score-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .score-display {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-hands-helping"></i>
            FEU Roosevelt DMS
            <span class="role-badge">Guidance</span>
        </h1>
        <div class="user-info">
            <div class="user-avatar"><?= $user_initial ?></div>
            <span><?= htmlspecialchars($user_name) ?></span>
            <a href="guidance_review_detail.php?app_id=<?= $app_id ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="page-header">
            <h2>Student Assessment</h2>
            <p class="page-subtitle">
                Comprehensive evaluation for <?= htmlspecialchars($application['student_name']) ?> • 
                Application #<?= $app_id ?>
            </p>
        </div>
        
        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($successMessage) ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>Rate each criterion on a scale of 0-10, where 0 is lowest and 10 is highest. Provide detailed notes for each section.</span>
        </div>
        
        <form method="POST" action="">
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    Assessment Criteria
                </div>
                <div class="card-body">
                    <!-- Financial Need -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-hand-holding-usd" style="color: var(--primary-purple);"></i>
                            Financial Need Assessment
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Score (0-10)</label>
                            <div class="score-input-group">
                                <input type="range" class="score-slider" name="financial_need_score" 
                                       min="0" max="10" value="<?= $assessment['financial_need_score'] ?? 5 ?>" 
                                       oninput="updateScore(this, 'financial_display')">
                                <div class="score-display" id="financial_display"><?= $assessment['financial_need_score'] ?? 5 ?></div>
                            </div>
                            <div class="score-legend">
                                <span>0 - No Need</span>
                                <span>5 - Moderate Need</span>
                                <span>10 - Critical Need</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Financial Need Notes</label>
                            <textarea name="financial_notes" class="form-control" 
                                      placeholder="Document family income, number of dependents, special circumstances, etc."><?= htmlspecialchars($assessment['financial_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Character Assessment -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-check" style="color: var(--primary-purple);"></i>
                            Character & Behavior Assessment
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Score (0-10)</label>
                            <div class="score-input-group">
                                <input type="range" class="score-slider" name="character_score" 
                                       min="0" max="10" value="<?= $assessment['character_score'] ?? 5 ?>" 
                                       oninput="updateScore(this, 'character_display')">
                                <div class="score-display" id="character_display"><?= $assessment['character_score'] ?? 5 ?></div>
                            </div>
                            <div class="score-legend">
                                <span>0 - Poor</span>
                                <span>5 - Good</span>
                                <span>10 - Excellent</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Character Assessment Notes</label>
                            <textarea name="character_notes" class="form-control" 
                                      placeholder="Document attitude, discipline, interpersonal skills, integrity, etc."><?= htmlspecialchars($assessment['character_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Leadership -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-medal" style="color: var(--primary-purple);"></i>
                            Leadership & Initiative
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Score (0-10)</label>
                            <div class="score-input-group">
                                <input type="range" class="score-slider" name="leadership_score" 
                                       min="0" max="10" value="<?= $assessment['leadership_score'] ?? 5 ?>" 
                                       oninput="updateScore(this, 'leadership_display')">
                                <div class="score-display" id="leadership_display"><?= $assessment['leadership_score'] ?? 5 ?></div>
                            </div>
                            <div class="score-legend">
                                <span>0 - None</span>
                                <span>5 - Moderate</span>
                                <span>10 - Outstanding</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Leadership Notes</label>
                            <textarea name="leadership_notes" class="form-control" 
                                      placeholder="Document leadership positions, initiatives, projects, etc."><?= htmlspecialchars($assessment['leadership_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Academic Performance -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-graduation-cap" style="color: var(--primary-purple);"></i>
                            Academic Performance
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Score (0-10)</label>
                            <div class="score-input-group">
                                <input type="range" class="score-slider" name="academic_score" 
                                       min="0" max="10" value="<?= $assessment['academic_score'] ?? 5 ?>" 
                                       oninput="updateScore(this, 'academic_display')">
                                <div class="score-display" id="academic_display"><?= $assessment['academic_score'] ?? 5 ?></div>
                            </div>
                            <div class="score-legend">
                                <span>0 - Below Standard</span>
                                <span>5 - Satisfactory</span>
                                <span>10 - Exceptional</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Community Involvement -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-users" style="color: var(--primary-purple);"></i>
                            Community Involvement
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Score (0-10)</label>
                            <div class="score-input-group">
                                <input type="range" class="score-slider" name="community_involvement" 
                                       min="0" max="10" value="<?= $assessment['community_involvement'] ?? 5 ?>" 
                                       oninput="updateScore(this, 'community_display')">
                                <div class="score-display" id="community_display"><?= $assessment['community_involvement'] ?? 5 ?></div>
                            </div>
                            <div class="score-legend">
                                <span>0 - None</span>
                                <span>5 - Active</span>
                                <span>10 - Highly Active</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overall Assessment -->
                    <div class="assessment-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-check" style="color: var(--primary-purple);"></i>
                            Overall Assessment
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Overall Recommendation</label>
                            <select name="overall_recommendation" class="form-select" required>
                                <option value="">Select recommendation...</option>
                                <option value="Strongly Recommended" <?= ($assessment['overall_recommendation'] ?? '') == 'Strongly Recommended' ? 'selected' : '' ?>>Strongly Recommended</option>
                                <option value="Recommended" <?= ($assessment['overall_recommendation'] ?? '') == 'Recommended' ? 'selected' : '' ?>>Recommended</option>
                                <option value="Conditionally Recommended" <?= ($assessment['overall_recommendation'] ?? '') == 'Conditionally Recommended' ? 'selected' : '' ?>>Conditionally Recommended</option>
                                <option value="Not Recommended" <?= ($assessment['overall_recommendation'] ?? '') == 'Not Recommended' ? 'selected' : '' ?>>Not Recommended</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Overall Notes & Recommendations</label>
                            <textarea name="overall_notes" class="form-control" rows="5" required
                                      placeholder="Provide your comprehensive assessment, key strengths, areas of concern, and final recommendation..."><?= htmlspecialchars($assessment['overall_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <a href="guidance_review_detail.php?app_id=<?= $app_id ?>" class="btn" style="background: #6b7280; color: white;">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            Save Assessment
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function updateScore(slider, displayId) {
            document.getElementById(displayId).textContent = slider.value;
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-danger');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
<?php
session_start();
include('include/db.php');

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Admin':
            header('Location: admin_dashboard.php');
            exit();
        case 'Dean':
            header('Location: dean_dashboard.php');
            exit();
        case 'Registrar':
            header('Location: registrar_dashboard.php');
            exit();
        case 'Student':
            header('Location: student_dashboard.php');
            exit();
        case 'Guidance':
            header('Location: guidance_dashboard.php');
                    break;
    }
}

// Retrieve messages from session and clear them
$invalidPassword = $_SESSION['invalidPassword'] ?? "";
$noUserFound = $_SESSION['noUserFound'] ?? "";
$lockMessage = $_SESSION['lockMessage'] ?? "";
$successMessage = $_SESSION['successMessage'] ?? "";
unset($_SESSION['invalidPassword'], $_SESSION['noUserFound'], $_SESSION['lockMessage'], $_SESSION['successMessage']);

$lockDuration = 30; // Lock duration in seconds
$maxAttempts = 3;

// Reset lockout if duration has expired
if (isset($_SESSION['lockout_time']) && time() >= $_SESSION['lockout_time']) {
    unset($_SESSION['lockout_time']);
    unset($_SESSION['lockout']);
    $_SESSION['failed_attempts'] = 0;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']) {
        $_SESSION['lockMessage'] = "Too many failed login attempts. Please try again later.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $sql = "SELECT * FROM user WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] != 'Active') {
            $_SESSION['invalidPassword'] = "Your account is " . strtolower($user['status']) . ". Please contact the administrator.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
        if (password_verify($password, $user['password'])) {
            // Successful login
            unset($_SESSION['failed_attempts']);
            unset($_SESSION['lockout_time']);
            unset($_SESSION['lockout']);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_name'] = $user['user_name'];
            
            // Update last login
            $update_sql = "UPDATE user SET last_login = NOW() WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            
            // Log login
            logAudit($conn, $user['user_id'], 'User Login', 'user', $user['user_id']);

            // Redirect based on role
            switch ($user['role']) {
                case 'Admin':
                    header('Location: admin_dashboard.php');
                    break;
                case 'Dean':
                    header('Location: dean_dashboard.php');
                    break;
                case 'Registrar':
                    header('Location: registrar_dashboard.php');
                    break;
                case 'Student':
                    header('Location: student_dashboard.php');
                    break;
                case 'Guidance':
                    header('Location: guidance_dashboard.php');
                    break;
            }
            exit();
            
        } else {
            $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;

            if ($_SESSION['failed_attempts'] >= $maxAttempts) {
                $_SESSION['lockout_time'] = time() + $lockDuration;
                $_SESSION['lockMessage'] = "Too many failed login attempts. Please try again in $lockDuration seconds.";
            } else {
                $_SESSION['invalidPassword'] = "Invalid password. You have " . ($maxAttempts - $_SESSION['failed_attempts']) . " attempts left.";
            }
        }
    } else {
        $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;

        if ($_SESSION['failed_attempts'] >= $maxAttempts) {
            $_SESSION['lockout_time'] = time() + $lockDuration;
            $_SESSION['lockMessage'] = "Too many failed login attempts. Please try again in $lockDuration seconds.";
        } else {
            $_SESSION['noUserFound'] = "No user found with that email. You have " . ($maxAttempts - $_SESSION['failed_attempts']) . " attempts left.";
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FEU Roosevelt DMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }
        
        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(11, 102, 35, 0.9), rgba(102, 126, 234, 0.9));
            z-index: -1;
        }
        
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-section h1 {
            color: #0B6623;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .logo-section p {
            color: #718096;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #0B6623;
            box-shadow: 0 0 0 0.2rem rgba(11, 102, 35, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0B6623, #0d7a2a);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 102, 35, 0.4);
            background: linear-gradient(135deg, #0d7a2a, #0B6623);
        }
        
        .btn-login:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            font-size: 0.9rem;
        }
        
        .cooldown-timer {
            font-size: 1.2rem;
            color: #ef4444;
            font-weight: 700;
        }
        
        .input-group-text {
            background: #f7fafc;
            border-right: none;
        }
        
        .form-control {
            border-left: none;
        }
        
        .form-control:focus + .input-group-text {
            border-color: #0B6623;
        }
    </style>
</head>
<body>
    <div class="video-container">
        <video loop autoplay muted>
            <source src="assets/background.mp4" type="video/mp4">
        </video>
        <div class="overlay"></div>
    </div>

    <div class="login-container">
        <div class="logo-section">
            <i class="fas fa-graduation-cap" style="font-size: 3rem; color: #0B6623; margin-bottom: 1rem;"></i>
            <h1>FEU Roosevelt DMS</h1>
            <p>Dean's List & Scholarship Management System</p>
        </div>

        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($invalidPassword)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($invalidPassword) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($noUserFound)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($noUserFound) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($lockMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-lock me-2"></i><?= htmlspecialchars($lockMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']): ?>
        <div class="text-center mb-3">
            <p class="text-danger fw-bold mb-2">Account Locked</p>
            <p class="text-muted">Try again in <span class="cooldown-timer" id="cooldownTimer">--</span> seconds</p>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="email" class="form-control" 
                           placeholder="Enter your email" required
                           <?= isset($_SESSION['lockout_time']) ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Enter your password" required
                           <?= isset($_SESSION['lockout_time']) ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <button type="submit" name="login" class="btn btn-login w-100"
                    <?= isset($_SESSION['lockout_time']) ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> For support, contact the registrar's office
            </small>
        </div>
    </div>

    <?php if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']): ?>
    <script>
        const lockoutEndTime = <?= $_SESSION['lockout_time'] ?> * 1000;

        function updateCooldownTimer() {
            const now = new Date().getTime();
            const distance = lockoutEndTime - now;

            if (distance > 0) {
                const seconds = Math.ceil(distance / 1000);
                document.getElementById("cooldownTimer").textContent = seconds;
            } else {
                location.reload();
            }
        }

        updateCooldownTimer();
        setInterval(updateCooldownTimer, 1000);
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
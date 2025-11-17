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

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_account'])) {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $stud_id = trim($_POST['stud_id']);

    // Validate required fields
    if (empty($stud_id)) {
        $errorMessage = "Student ID is required.";
    } elseif (empty($email)) {
        $errorMessage = "Email is required.";
    } elseif (empty($username)) {
        $errorMessage = "Username is required.";
    } elseif (empty($password)) {
        $errorMessage = "Password is required.";
    }

    // Validate if email already exists
    if (empty($errorMessage)) {
        $sql = "SELECT * FROM user WHERE email=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errorMessage = "Email already exists.";
        }
    }

    // Validate if Student ID already exists
    if (empty($errorMessage)) {
        $sql = "SELECT * FROM user WHERE stud_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $stud_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errorMessage = "Student ID already exists.";
        }
    }

    // If no errors, insert new user
    if (empty($errorMessage)) {
        $sql = "INSERT INTO user (stud_id, email, user_name, password, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $stud_id, $email, $username, $password, $role);

        if ($stmt->execute()) {
            $successMessage = "User created successfully!";
        } else {
            $errorMessage = "Error creating user.";
        }
    }
}

// Pagination setup
$limit = 10;
$page = $_GET['page'] ?? 1;
$page = max(1, intval($page));
$offset = ($page - 1) * $limit;

// Role filtering
$roleFilter = $_GET['filter_role'] ?? '';
$filterQuery = "";
$params = [];
$paramTypes = "";

if (!empty($roleFilter) && in_array($roleFilter, ['Admin', 'Student', 'Dean'])) {
    $filterQuery = " WHERE role = ?";
    $params[] = $roleFilter;
    $paramTypes .= "s";
}

// Count total records
$countSql = "SELECT COUNT(*) AS total FROM user" . $filterQuery;
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$total_rows = $countResult->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch user records
$sql = "SELECT * FROM user" . $filterQuery . " ORDER BY stud_id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$users = $stmt->get_result();

?>

<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FEU Roosevelt Dean's List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="css/global.css">
</head>

<body>
    <?php include('include/header.php'); ?>
    <div class="layout-wrapper">
        <?php include('include/sidebar.php'); ?>
        <div class="main-content flex-grow-1">
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show w-100" role="alert" id="registerAlert"
                style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1050;">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php elseif (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show w-100" role="alert" id="registerAlert"
                style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1050;">
                <?= $errorMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <div class="d-flex">
                <div class="user-left-container">
                    <main class="content-area">
                        <section class="search-user">
                            <h2 class="text-center mb-4">Search User</h2>
                            <input type="text" id="userSearch" class="form-control mb-3"
                                placeholder="Search Name or Email">
                        </section>
                        <section class="user-registration">
                            <h2 class="text-center mb-4">User Registration</h2>
                            <form method="POST" action="">
                                <div class="mb-3" id="studIdField">
                                    <label for="stud_id" class="form-label">Student ID</label>
                                    <input type="text" name="stud_id" id="stud_id" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email address</label>
                                    <input type="email" name="email" id="email" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" name="username" id="username" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" name="password" id="password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select name="role" id="role" class="form-select" required>
                                        <option value="">Select role</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Dean">Dean</option>
                                        <option value="Student">Student</option>
                                    </select>
                                </div>

                                <button type="submit" name="create_account" class="btn btn-primary">Create
                                    Account</button>
                            </form>


                        </section>

                    </main>
                </div>

                <div class="user-right-container">
                    <main class="content-area">
                        <section class="registered-users">
                            <h2 class="text-center mb-4">Registered Users</h2>

                            <!-- Role Filter Dropdown -->
                            <form method="GET" class="d-flex mb-3 justify-content-end">
                                <select name="filter_role" class="form-select w-auto me-2"
                                    onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <option value="Admin" <?= ($roleFilter === 'Admin') ? 'selected' : '' ?>>Admin
                                    </option>
                                    <option value="Student" <?= ($roleFilter === 'Student') ? 'selected' : '' ?>>Student
                                    </option>
                                    <option value="Dean" <?= ($roleFilter === 'Dean') ? 'selected' : '' ?>>Dean</option>
                                </select>
                                <input type="hidden" name="page" value="1">
                            </form>

                            <!-- User Table -->
                            <div class="table-responsive">
                                <table class="table" id="userTable">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">ID Number</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Role</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userTableBody">
                                        <?php $rowNum = $offset + 1; ?>
                                        <?php while ($row = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $rowNum++ ?></td>
                                            <td><?= htmlspecialchars($row['stud_id'] ?? '-') ?></td>
                                            <td class="username"><?= htmlspecialchars($row['user_name']) ?></td>
                                            <td class="email"><?= htmlspecialchars($row['email']) ?></td>
                                            <td>
                                                <span
                                                    class="badge <?= $row['role'] === 'Admin' ? 'bg-primary' : 'bg-success' ?>">
                                                    <?= htmlspecialchars($row['role']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>


                            <!-- Pagination Controls -->
                            <div class="d-flex justify-content-center mt-3">
                                <nav aria-label="Page navigation example">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                        <li class="page-item"><a class="page-link"
                                                href="?page=<?php echo $page - 1; ?>">&laquo;
                                                Prev</a></li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                        <li class="page-item"><a class="page-link"
                                                href="?page=<?php echo $page + 1; ?>">Next
                                                &raquo;</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>
        <script>
            
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const tableBody = document.getElementById('userTableBody');

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.toLowerCase();
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const username = row.querySelector('.username')?.textContent
            .toLowerCase() || '';
            const email = row.querySelector('.email')?.textContent.toLowerCase() || '';

            if (username.includes(query) || email.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

document.addEventListener("DOMContentLoaded", function() {
    // Check if the alert exists
    const alertBox = document.getElementById('registerAlert');
    if (alertBox) {
        // Remove the upload=success parameter from the URL
        history.replaceState(null, '', window.location.pathname);

        // Automatically dismiss the alert after 3 seconds
        setTimeout(function() {
            alertBox.classList.remove('show');
            alertBox.classList.add('fade');
        }, 3000); // 3 seconds
    }
});
        </script>
</body>

</html>
<?php
// Fetch user info from the database (make sure user is logged in)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT user_name FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}
?>

<style>
    /* Keep your existing header CSS */
    header {
        background-color: #3f51b5;
        color: white;
        height: 56px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 24px;
        font-weight: 600;
        font-size: 1.125rem;
        flex-shrink: 0;
    }

    header .user {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.875rem;
        cursor: pointer;
        position: relative;
    }

    /* Minimal dropdown styling */
    .dropdown {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        color: #333;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 8px;
        min-width: 120px;
        z-index: 1000;
    }

    .dropdown a {
        display: block;
        padding: 8px 12px;
        text-decoration: none;
        color: inherit;
    }

    .dropdown a:hover {
        background-color: #f0f0f0;
    }

    .user-menu {
        position: relative;
    }
</style>

<header>
    <div onclick="window.location.href='landing.php'">Digi Docu</div>

    <div class="user-menu">
        <div class="user" onclick="toggleDropdown()">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($user['user_name'] ?? 'User'); ?></span>
        </div>
        <div id="dropdown" class="dropdown">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</header>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById("dropdown");
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

document.addEventListener("click", function(event) {
    const userMenu = document.querySelector(".user-menu");
    const dropdown = document.getElementById("dropdown");
    if (!userMenu.contains(event.target)) {
        dropdown.style.display = "none";
    }
});
</script>
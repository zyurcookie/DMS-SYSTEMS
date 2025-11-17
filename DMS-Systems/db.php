<?php
/**
 * FEU Roosevelt Complete DMS - Database Configuration
 */

if (!defined('DB_CONFIG_LOADED')) {
    define('DB_CONFIG_LOADED', true);
} else {
    return; // Prevent loading twice
}

// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "feu_roosevelt_dms";

// Connecting to database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');


/* ============================================================
   SAFE executeQuery()
============================================================ */
if (!function_exists('executeQuery')) {
    function executeQuery($conn, $sql, $types = "", $params = []) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }

        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            return false;
        }

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}


/* ============================================================
   Student Details
============================================================ */
if (!function_exists('getStudentDetails')) {
    function getStudentDetails($conn, $id, $type = 'user_id') {
        $column = ($type === 'stud_id') ? 'p.stud_id' : 'u.user_id';

        $sql = "SELECT u.*, p.*, s.qpa, s.min_grade, s.is_regular_student,
                s.took_only_curriculum_courses, s.has_incomplete_grade,
                s.has_dropped_or_failed, s.violated_rules, s.attendance_percent,
                s.eligible, s.eligibility_status, s.requirements
                FROM user u
                INNER JOIN profile p ON u.user_id = p.user_id
                LEFT JOIN student s ON p.stud_id = s.stud_id
                WHERE $column = ?";

        $result = executeQuery($conn, $sql, "i", [$id]);

        return $result && $result->num_rows > 0
            ? $result->fetch_assoc()
            : null;
    }
}


/* ============================================================
   Student Documents
============================================================ */
if (!function_exists('getStudentDocuments')) {
    function getStudentDocuments($conn, $student_id, $related_type = null) {
        $sql = "SELECT d.*, 
                GROUP_CONCAT(t.tag_name SEPARATOR ', ') AS tags
                FROM document d
                LEFT JOIN document_tags dt ON d.doc_id = dt.doc_id
                LEFT JOIN tags t ON dt.tag_id = t.tag_id
                WHERE d.student_id = ?";

        $params = [$student_id];
        $types = "i";

        if ($related_type) {
            $sql .= " AND d.related_type = ?";
            $params[] = $related_type;
            $types .= "s";
        }

        $sql .= " GROUP BY d.doc_id ORDER BY d.upload_date DESC";

        $result = executeQuery($conn, $sql, $types, $params);

        $docs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $docs[] = $row;
            }
        }
        return $docs;
    }
}


/* ============================================================
   Dean's List Records
============================================================ */
if (!function_exists('getDeansListRecords')) {
    function getDeansListRecords($conn, $student_id, $status = null) {
        $sql = "SELECT dl.*, 
                CONCAT(p.firstName, ' ', p.lastName) AS student_name,
                v.user_name AS verified_by_name
                FROM dean_list dl
                INNER JOIN profile p ON dl.student_id = p.user_id
                LEFT JOIN user v ON dl.verified_by = v.user_id
                WHERE dl.student_id = ?";

        $params = [$student_id];
        $types = "i";

        if ($status) {
            $sql .= " AND dl.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $sql .= " ORDER BY dl.academic_year DESC, dl.semester DESC";

        $result = executeQuery($conn, $sql, $types, $params);

        $records = [];
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $records[] = $r;
            }
        }
        return $records;
    }
}


/* ============================================================
   Scholarship Applications
============================================================ */
if (!function_exists('getScholarshipApplications')) {
    function getScholarshipApplications($conn, $student_id, $status = null) {
        $sql = "SELECT sa.*, r.user_name AS reviewed_by_name
                FROM scholarship_application sa
                LEFT JOIN user r ON sa.reviewed_by = r.user_id
                WHERE sa.student_id = ?";

        $params = [$student_id];
        $types = "i";

        if ($status) {
            $sql .= " AND sa.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $sql .= " ORDER BY sa.application_date DESC";

        $result = executeQuery($conn, $sql, $types, $params);

        $apps = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $apps[] = $row;
            }
        }
        return $apps;
    }
}


/* ============================================================
   Eligibility Percentage
============================================================ */
if (!function_exists('calculateEligibilityPercentage')) {
    function calculateEligibilityPercentage($studentData) {
        if (!$studentData) return 0;

        $total = 8;
        $ok = 0;

        if ($studentData['qpa'] >= 3.5) $ok++;
        if ($studentData['min_grade'] > 3.0) $ok++;
        if ($studentData['is_regular_student'] == 1) $ok++;
        if ($studentData['took_only_curriculum_courses'] == 1) $ok++;
        if ($studentData['has_incomplete_grade'] == 0) $ok++;
        if ($studentData['has_dropped_or_failed'] == 0) $ok++;
        if ($studentData['violated_rules'] == 0) $ok++;
        if ($studentData['attendance_percent'] >= 80) $ok++;

        return intval(($ok / $total) * 100);
    }
}


/* ============================================================
   Status Badge
============================================================ */
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($value, $expected = null, $isLessThan = false) {
        if (is_null($value)) {
            return '<span class="badge bg-warning">Pending</span>';
        }

        if (!is_null($expected)) {
            if ($isLessThan) {
                return $value > $expected
                    ? '<span class="badge bg-success">Approved</span>'
                    : '<span class="badge bg-danger">Declined</span>';
            }
            return $value >= $expected
                ? '<span class="badge bg-success">Approved</span>'
                : '<span class="badge bg-danger">Declined</span>';
        }

        return $value >= 80
            ? '<span class="badge bg-success">Approved</span>'
            : '<span class="badge bg-danger">Declined</span>';
    }
}


/* ============================================================
   Audit Trail Logging
============================================================ */
if (!function_exists('logAudit')) {
    function logAudit($conn, $user_id, $action, $table_name = null, $record_id = null, $ip_address = null) {
        if (!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }

        $sql = "INSERT INTO audit_trail (user_id, action, table_name, record_id, ip_address)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issis", $user_id, $action, $table_name, $record_id, $ip_address);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}


/* ============================================================
   Time Ago Formatter
============================================================ */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now = new DateTime("now", new DateTimeZone('Asia/Manila'));
        $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        $diff = $now->diff($ago);

        if ($diff->y) return $diff->y . ' year(s) ago';
        if ($diff->m) return $diff->m . ' month(s) ago';
        if ($diff->d) return $diff->d . ' day(s) ago';
        if ($diff->h) return $diff->h . ' hour(s) ago';
        if ($diff->i) return $diff->i . ' minute(s) ago';
        return "just now";
    }
}


/* ============================================================
   HTML Escape
============================================================ */
if (!function_exists('escape')) {
    function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}


// Development Errors
if ($_SERVER["SERVER_NAME"] === "localhost") {
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
} else {
    error_reporting(0);
    ini_set("display_errors", 0);
}

?>

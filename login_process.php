
<?php
session_start();

require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$student_id = $_POST["student_id"] ?? "";
$password = $_POST["password"] ?? "";

if (
    !is_string($student_id) ||
    !is_string($password) ||
    trim($student_id) === "" ||
    $password === ""
) {
    http_response_code(400);
    exit("Student ID and password are required.");
}

$student_id = trim($student_id);

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            student_id,
            full_name,
            role,
            status,
            password,
            must_change_pass
        FROM users
        WHERE student_id = :student_id
        LIMIT 1
    ");

    $stmt->execute([
        "student_id" => $student_id
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to sign in right now. Please try again.");
}

if (
    !$user ||
    !password_verify($password, $user["password"])
) {
    http_response_code(401);
    exit("Invalid Student ID or password.");
}

if ($user["status"] !== "active") {
    http_response_code(403);
    exit("Your account is currently inactive.");
}

if (!in_array($user["role"], ["admin", "voter"], true)) {
    http_response_code(403);
    exit("This account does not have an authorised role.");
}

/*
 * Rotate the session ID after successful authentication.
 * This helps prevent session fixation.
 */
if (!session_regenerate_id(true)) {
    http_response_code(500);
    exit("Unable to establish a secure session. Please try again.");
}

/*
 * Remove stale authentication and voting-session data before
 * creating the new authenticated session.
 */
unset(
    $_SESSION["user_id"],
    $_SESSION["student_id"],
    $_SESSION["full_name"],
    $_SESSION["role"],
    $_SESSION["password_change_token"],
    $_SESSION["must_change_password"],
    $_SESSION["vote_selections"],
    $_SESSION["vote_completed"],
    $_SESSION["current_election_id"]
);

$_SESSION["user_id"] = $user["id"];
$_SESSION["student_id"] = $user["student_id"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["role"] = $user["role"];

/*
 * Administrators continue to their existing dashboard.
 */
if ($user["role"] === "admin") {
    header("Location: admin/dashboard.php");
    exit;
}

/*
 * Voters with temporary passwords are directed to the
 * existing password-change page.
 */
if ((int)$user["must_change_pass"] === 1) {
    $_SESSION["must_change_password"] = true;

    header("Location: voter/change_password.php");
    exit;
}

$_SESSION["must_change_password"] = false;

header("Location: voter/dashboard.php");
exit;

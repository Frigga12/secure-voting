
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Require an authenticated voter.
 */
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "voter"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

try {
    $stmt = $pdo->prepare("
        SELECT status, must_change_pass
        FROM users
        WHERE id = :id AND role = 'voter'
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $_SESSION["user_id"]
    ]);

    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to verify your account. Please try again.");
}

/*
 * Reject missing or inactive accounts.
 */
if (!$voter || $voter["status"] !== "active") {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../login.php");
    exit;
}

/*
 * Force voters with temporary passwords to change them first.
 * The password-change page itself remains accessible.
 */
if (
    (int)$voter["must_change_pass"] === 1 &&
    basename($_SERVER["SCRIPT_NAME"]) !== "change_password.php"
) {
    header("Location: change_password.php");
    exit;
}

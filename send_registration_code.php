<?php
session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/Mailer.php";

$mailConfig = Mailer::getConfig();

// Determine if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

function respond(bool $success, string $message, array $extra = []): void
{
    global $isAjax;

    if ($isAjax) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($success ? 200 : 400);
        echo json_encode(array_merge([
            "status"  => $success ? "success" : "error",
            "message" => $message,
        ], $extra));
        exit;
    }

    if (!$success) {
        $_SESSION["signup_error"] = $message;
        $_SESSION["signup_old"] = $_POST;
        header("Location: signup.php");
        exit;
    }

    $_SESSION["signup_success"] = $message;
    header("Location: verify_registration.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.");
}

// 1. Verify CSRF Token
$sessionToken = (string)($_SESSION["signup_csrf_token"] ?? "");
$postedToken  = (string)($_POST["csrf_token"] ?? "");

if ($sessionToken === "" || $postedToken === "" || !hash_equals($sessionToken, $postedToken)) {
    respond(false, "Your session has expired. Please refresh the page and submit again.");
}

$action = trim((string)($_POST["action"] ?? "send"));

// RESEND ACTION
if ($action === "resend") {
    $pending = $_SESSION["pending_registration"] ?? null;
    if (!$pending || empty($pending["id"])) {
        respond(false, "No active registration in progress. Please fill out the registration form.");
    }

    $verificationId = (int)$pending["id"];

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM registration_verifications
            WHERE id = :id AND is_verified = 0
            LIMIT 1
        ");
        $stmt->execute(["id" => $verificationId]);
        $record = $stmt->fetch();

        if (!$record) {
            respond(false, "Verification session expired or already used. Please register again.");
        }

        // Cooldown check
        $cooldownSeconds = (int)($mailConfig['resend_cooldown_seconds'] ?? 60);
        $stmtCooldown = $pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
            FROM registration_verifications
            WHERE id = :id
        ");
        $stmtCooldown->execute(["id" => $verificationId]);
        $cooldownRow = $stmtCooldown->fetch();
        $elapsed = (int)($cooldownRow['elapsed'] ?? 0);

        if ($elapsed < $cooldownSeconds) {
            $remaining = $cooldownSeconds - $elapsed;
            respond(false, "Please wait {$remaining} seconds before requesting a new code.");
        }

        // Generate new OTP and reset attempts & expiry
        $newOtp = sprintf("%06d", random_int(100000, 999999));
        $expiryMinutes = (int)($mailConfig['otp_expiry_minutes'] ?? 10);

        $stmtUpdate = $pdo->prepare("
            UPDATE registration_verifications
            SET otp_code = :otp_code,
                attempts = 0,
                created_at = NOW(),
                expires_at = DATE_ADD(NOW(), INTERVAL :expiry MINUTE)
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            "otp_code" => $newOtp,
            "expiry"   => $expiryMinutes,
            "id"       => $verificationId,
        ]);

        $mailResult = Mailer::sendVerificationCode(
            $record["email"],
            $record["full_name"],
            $newOtp,
            $expiryMinutes
        );

        if (!$mailResult['success']) {
            respond(false, $mailResult['message'] ?? "Failed to resend confirmation email.");
        }

        $_SESSION["pending_registration"]["expires_at"] = time() + ($expiryMinutes * 60);
        if (!empty($mailResult['debug_otp'])) {
            $_SESSION["dev_debug_otp"] = $mailResult['debug_otp'];
        }

        respond(true, "A new confirmation code has been sent to {$record['email']}.", [
            "redirect"   => "verify_registration.php",
            "email"      => $record["email"],
            "expires_in" => $expiryMinutes * 60,
        ]);

    } catch (PDOException $e) {
        error_log("Database error during resend: " . $e->getMessage());
        respond(false, "Could not resend confirmation code. Please try again.");
    }
}

// FULL REGISTRATION SUBMISSION
$full_name         = trim((string)($_POST["full_name"] ?? ""));
$student_id        = strtoupper(trim((string)($_POST["student_id"] ?? ""))); // Matric number
$department        = trim((string)($_POST["department"] ?? ""));
$level             = (int)($_POST["level"] ?? 100);
$email             = strtolower(trim((string)($_POST["email"] ?? "")));
$password          = (string)($_POST["password"] ?? "");
$confirm_password  = (string)($_POST["confirm_password"] ?? "");

// Validation checks
if ($full_name === "" || $student_id === "" || $department === "" || $email === "" || $password === "") {
    respond(false, "Please fill in all required fields.");
}

if (strlen($full_name) < 3 || strlen($full_name) > 150) {
    respond(false, "Please provide a valid full name (3 to 150 characters).");
}

if (strlen($student_id) < 4 || strlen($student_id) > 30) {
    respond(false, "Matric number must be between 4 and 30 characters.");
}

// Matric number format validation (alphanumeric with optional / - . _)
if (!preg_match('/^[A-Z0-9\/\-\.]+$/i', $student_id)) {
    respond(false, "Matric number contains invalid characters.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    respond(false, "Please enter a valid email address.");
}

// Check allowed email domains if restricted
if (!empty($mailConfig['allowed_email_domains'])) {
    $emailDomain = substr(strrchr($email, "@"), 1);
    if (!in_array($emailDomain, $mailConfig['allowed_email_domains'], true)) {
        respond(false, "Registration is restricted to institutional email addresses (" . implode(", ", $mailConfig['allowed_email_domains']) . ").");
    }
}

// Level validation
$allowedLevels = [100, 200, 300, 400, 500, 600, 700];
if (!in_array($level, $allowedLevels, true)) {
    $level = 100;
}

// Password validation
if (strlen($password) < 6) {
    respond(false, "Password must be at least 6 characters long.");
}

if ($password !== $confirm_password) {
    respond(false, "Passwords do not match.");
}

try {
    // Check uniqueness against existing users
    $stmtCheck = $pdo->prepare("
        SELECT id, student_id, email
        FROM users
        WHERE student_id = :student_id OR email = :email
        LIMIT 1
    ");
    $stmtCheck->execute([
        "student_id" => $student_id,
        "email"      => $email,
    ]);
    $existingUser = $stmtCheck->fetch();

    if ($existingUser) {
        if (strtoupper($existingUser["student_id"]) === $student_id) {
            respond(false, "This Matric Number is already registered. Please go to the sign in page to access your account.");
        }
        if (strtolower($existingUser["email"] ?? "") === $email) {
            respond(false, "This email address is already linked to a registered student account.");
        }
    }

    // Genuine Student Verification (Optional Pre-Approved Roster check)
    if (!empty($mailConfig['enforce_student_roster'])) {
        $stmtRoster = $pdo->prepare("
            SELECT id, full_name, is_registered
            FROM eligible_students
            WHERE student_id = :student_id
            LIMIT 1
        ");
        $stmtRoster->execute(["student_id" => $student_id]);
        $rosterStudent = $stmtRoster->fetch();

        if (!$rosterStudent) {
            respond(false, "Matric Number {$student_id} was not found on the official student roster. Please contact the election administrator.");
        }

        if ((int)$rosterStudent["is_registered"] === 1) {
            respond(false, "An account has already been claimed for this Matric Number.");
        }
    }

    // Resend protection / Rate Limiting (cooldown)
    $cooldownSeconds = (int)($mailConfig['resend_cooldown_seconds'] ?? 60);
    $stmtCooldown = $pdo->prepare("
        SELECT id, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
        FROM registration_verifications
        WHERE (email = :email OR student_id = :student_id)
          AND is_verified = 0
          AND created_at >= (NOW() - INTERVAL :cooldown SECOND)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtCooldown->execute([
        "email"      => $email,
        "student_id" => $student_id,
        "cooldown"   => $cooldownSeconds,
    ]);
    $recentAttempt = $stmtCooldown->fetch();

    if ($recentAttempt) {
        $elapsed = (int)$recentAttempt['elapsed'];
        $waitSeconds = max(1, $cooldownSeconds - $elapsed);
        respond(false, "A verification code was recently sent. Please wait {$waitSeconds} seconds before requesting a new code.");
    }

    // Generate 6-digit confirmation code
    $otpCode = sprintf("%06d", random_int(100000, 999999));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $expiryMinutes = (int)($mailConfig['otp_expiry_minutes'] ?? 10);
    $maxAttempts = (int)($mailConfig['otp_max_attempts'] ?? 5);

    // Delete previous unverified attempts for this email or matric number
    $stmtClean = $pdo->prepare("
        DELETE FROM registration_verifications
        WHERE (email = :email OR student_id = :student_id)
          AND is_verified = 0
    ");
    $stmtClean->execute([
        "email"      => $email,
        "student_id" => $student_id,
    ]);

    // Insert new verification record
    $stmtInsert = $pdo->prepare("
        INSERT INTO registration_verifications (
            student_id,
            full_name,
            department,
            level,
            email,
            password_hash,
            otp_code,
            attempts,
            max_attempts,
            expires_at,
            created_at,
            is_verified
        ) VALUES (
            :student_id,
            :full_name,
            :department,
            :level,
            :email,
            :password_hash,
            :otp_code,
            0,
            :max_attempts,
            DATE_ADD(NOW(), INTERVAL :expiry MINUTE),
            NOW(),
            0
        )
    ");
    $stmtInsert->execute([
        "student_id"    => $student_id,
        "full_name"     => $full_name,
        "department"    => $department,
        "level"         => $level,
        "email"         => $email,
        "password_hash" => $hashedPassword,
        "otp_code"      => $otpCode,
        "max_attempts"  => $maxAttempts,
        "expiry"        => $expiryMinutes,
    ]);

    $verificationId = (int)$pdo->lastInsertId();

    // Send the email with the code
    $mailResult = Mailer::sendVerificationCode($email, $full_name, $otpCode, $expiryMinutes);

    if (!$mailResult['success']) {
        $stmtRollback = $pdo->prepare("DELETE FROM registration_verifications WHERE id = :id");
        $stmtRollback->execute(["id" => $verificationId]);

        respond(false, $mailResult['message'] ?? "Failed to dispatch verification email. Please check your address or contact support.");
    }

    // Save pending verification state in session
    $_SESSION["pending_registration"] = [
        "id"          => $verificationId,
        "student_id"  => $student_id,
        "email"       => $email,
        "full_name"   => $full_name,
        "created_at"  => time(),
        "expires_at"  => time() + ($expiryMinutes * 60),
    ];

    if (!empty($mailResult['debug_otp'])) {
        $_SESSION["dev_debug_otp"] = $mailResult['debug_otp'];
    } else {
        unset($_SESSION["dev_debug_otp"]);
    }

    respond(true, "A 6-digit confirmation code has been sent to {$email}.", [
        "redirect"    => "verify_registration.php",
        "email"       => $email,
        "student_id"  => $student_id,
        "expires_in"  => $expiryMinutes * 60,
        "mode"        => $mailResult['mode'],
    ]);

} catch (PDOException $e) {
    error_log("Database error in send_registration_code.php: " . $e->getMessage());
    respond(false, "A database error occurred. Please try again.");
} catch (Exception $e) {
    error_log("Unexpected error in send_registration_code.php: " . $e->getMessage());
    respond(false, "An unexpected error occurred. Please try again.");
}

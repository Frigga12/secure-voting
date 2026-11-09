<?php
session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/Mailer.php";

$mailConfig = Mailer::getConfig();

// Helper function for HTML escaping
function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Generate CSRF token if missing
if (empty($_SESSION["verify_csrf_token"])) {
    $_SESSION["verify_csrf_token"] = bin2hex(random_bytes(32));
}

// Check if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

$error = "";
$success = "";

// Retrieve pending registration session
$pending = $_SESSION["pending_registration"] ?? null;

// Handle POST Verification
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sessionToken = (string)($_SESSION["verify_csrf_token"] ?? "");
    $postedToken  = (string)($_POST["csrf_token"] ?? "");

    if ($sessionToken === "" || $postedToken === "" || !hash_equals($sessionToken, $postedToken)) {
        if ($isAjax) {
            header("Content-Type: application/json; charset=UTF-8");
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Session expired. Please refresh the page."]);
            exit;
        }
        $error = "Your form session has expired. Please refresh and try again.";
    } else {
        // Collect entered OTP code
        $otp = trim((string)($_POST["otp"] ?? ""));
        if ($otp === "" && isset($_POST["otp_digits"]) && is_array($_POST["otp_digits"])) {
            $otp = implode("", $_POST["otp_digits"]);
        }
        $otp = preg_replace('/\D/', '', $otp);

        $verificationId = (int)($_POST["verification_id"] ?? ($pending["id"] ?? 0));

        if (strlen($otp) !== 6) {
            $error = "Please enter the complete 6-digit confirmation code.";
        } elseif ($verificationId <= 0) {
            $error = "No active registration found. Please return to the sign-up page.";
        } else {
            try {
                // Fetch verification record
                $stmt = $pdo->prepare("
                    SELECT *,
                           TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
                    FROM registration_verifications
                    WHERE id = :id AND is_verified = 0
                    LIMIT 1
                ");
                $stmt->execute(["id" => $verificationId]);
                $record = $stmt->fetch();

                if (!$record) {
                    $error = "This verification session is invalid or has already been used. Please sign up again.";
                } elseif ((int)$record["seconds_left"] <= 0) {
                    $error = "This confirmation code has expired (10 minutes elapsed). Please request a new code.";
                } elseif ((int)$record["attempts"] >= (int)$record["max_attempts"]) {
                    $error = "Maximum confirmation attempts exceeded. For your security, please request a new code.";
                } elseif (!hash_equals($record["otp_code"], $otp)) {
                    // Increment failed attempt counter
                    $stmtInc = $pdo->prepare("
                        UPDATE registration_verifications
                        SET attempts = attempts + 1
                        WHERE id = :id
                    ");
                    $stmtInc->execute(["id" => $verificationId]);

                    $remainingAttempts = (int)$record["max_attempts"] - ((int)$record["attempts"] + 1);
                    if ($remainingAttempts <= 0) {
                        $error = "Incorrect code. You have exceeded the maximum allowed attempts. Please request a new code.";
                    } else {
                        $error = "Incorrect confirmation code. You have {$remainingAttempts} attempt" . ($remainingAttempts === 1 ? "" : "s") . " remaining.";
                    }
                } else {
                    // Code matches! Create voter account
                    $pdo->beginTransaction();

                    // Re-verify uniqueness in users table before insertion
                    $stmtCheck = $pdo->prepare("
                        SELECT id FROM users
                        WHERE student_id = :student_id OR email = :email
                        LIMIT 1
                    ");
                    $stmtCheck->execute([
                        "student_id" => $record["student_id"],
                        "email"      => $record["email"],
                    ]);

                    if ($stmtCheck->fetch()) {
                        $pdo->rollBack();
                        $error = "An account with this Matric Number or Email already exists. Please proceed to sign in.";
                    } else {
                        // Insert new voter
                        $stmtUser = $pdo->prepare("
                            INSERT INTO users (
                                student_id,
                                email,
                                full_name,
                                department,
                                level,
                                password,
                                role,
                                status,
                                must_change_pass,
                                created_at
                            ) VALUES (
                                :student_id,
                                :email,
                                :full_name,
                                :department,
                                :level,
                                :password,
                                'voter',
                                'active',
                                0,
                                NOW()
                            )
                        ");

                        $stmtUser->execute([
                            "student_id" => $record["student_id"],
                            "email"      => $record["email"],
                            "full_name"  => $record["full_name"],
                            "department" => $record["department"],
                            "level"      => (int)$record["level"],
                            "password"   => $record["password_hash"],
                        ]);

                        // Mark verification as complete
                        $stmtDone = $pdo->prepare("
                            UPDATE registration_verifications
                            SET is_verified = 1
                            WHERE id = :id
                        ");
                        $stmtDone->execute(["id" => $verificationId]);

                        // If student roster is in use, mark registered
                        $stmtRoster = $pdo->prepare("
                            UPDATE eligible_students
                            SET is_registered = 1, email = :email
                            WHERE student_id = :student_id
                        ");
                        $stmtRoster->execute([
                            "email"      => $record["email"],
                            "student_id" => $record["student_id"],
                        ]);

                        $pdo->commit();

                        // Clear pending session
                        unset($_SESSION["pending_registration"], $_SESSION["dev_debug_otp"]);
                        $_SESSION["login_flash_success"] = "Account created successfully! You can now sign in using your Matric Number and password.";

                        if ($isAjax) {
                            header("Content-Type: application/json; charset=UTF-8");
                            echo json_encode([
                                "status"   => "success",
                                "message"  => "Account created successfully!",
                                "redirect" => "login.php?registered=1",
                            ]);
                            exit;
                        }

                        header("Location: login.php?registered=1");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Verification error: " . $e->getMessage());
                $error = "A system error occurred while verifying your account. Please try again.";
            }
        }
    }

    if ($isAjax) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }
}

// Fallback if accessed directly without pending registration
if (!$pending) {
    // If not in session, check if there's old info or redirect to signup
    header("Location: signup.php");
    exit;
}

$pendingEmail = (string)($pending["email"] ?? "");
$pendingMatric = (string)($pending["student_id"] ?? "");
$pendingName = (string)($pending["full_name"] ?? "Student");
$verificationId = (int)($pending["id"] ?? 0);

// Calculate remaining seconds for countdown
$expiresAt = (int)($pending["expires_at"] ?? (time() + 600));
$remainingSeconds = max(0, $expiresAt - time());
$remainingMinutes = floor($remainingSeconds / 60);
$remainingSecs = $remainingSeconds % 60;

$debugOtp = $_SESSION["dev_debug_otp"] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Registration | Secure Online Voting System</title>

<style>
:root {
    /* 60% DOMINANT: Clean Neutral Canvas */
    --page: #f8fafc;
    --surface: #ffffff;
    --border: #e2e8f0;

    /* 30% SECONDARY: Solid Deep Navy Structure & Typography */
    --navy: #0f2038;
    --muted: #64748b;

    /* 10% ACCENT: Solid Royal Blue Interactive Elements */
    --blue: #2563eb;
    --blue-hover: #1d4ed8;

    /* Semantic Alerts */
    --danger: #b42318;
    --danger-bg: #fff1f0;
    --danger-border: #fecaca;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--navy);
    background: var(--page);
}

button, input {
    font: inherit;
}

.page-wrapper {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.08fr .92fr;
}

/* LEFT VISUAL PANEL (30% Solid Navy Structure) */
.visual-panel {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 7%;
    color: white;
    background: var(--navy);
}

.visual-panel::before,
.visual-panel::after {
    content: "";
    position: absolute;
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 50%;
    pointer-events: none;
}

.visual-panel::before {
    width: 480px;
    height: 480px;
    top: 14%;
    left: -220px;
}

.visual-panel::after {
    width: 390px;
    height: 390px;
    right: -220px;
    bottom: -100px;
}

.brand-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--blue);
    display: grid;
    place-items: center;
    color: white;
    box-shadow: 0 6px 18px rgba(37,99,235,.25);
}

.brand-icon svg {
    width: 22px;
    height: 22px;
}

.brand-title {
    font-size: 19px;
    font-weight: 750;
    letter-spacing: -.3px;
}

.brand-sub {
    font-size: 11px;
    color: #93c5fd;
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

.visual-center {
    margin: 40px 0;
    max-width: 440px;
}

.visual-center h1 {
    font-size: clamp(26px, 3.2vw, 34px);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -.6px;
    margin-bottom: 14px;
}

.visual-center p {
    font-size: 14px;
    color: #cbd5e1;
    line-height: 1.7;
    margin-bottom: 28px;
}

.steps-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.step-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 14px 16px;
}

.step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}

.step-num.done {
    background: var(--blue);
    color: white;
}

.step-num.active {
    background: var(--blue);
    color: white;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.3);
}

.step-content h3 {
    font-size: 13px;
    font-weight: 700;
    color: white;
    margin-bottom: 2px;
}

.step-content p {
    font-size: 12px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.4;
}

.visual-footer {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #94a3b8;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 20px;
}

/* RIGHT FORM PANEL */
.verify-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 45px 10%;
    background: var(--page);
}

.verify-content {
    width: 100%;
    max-width: 440px;
}

.verify-top-icon {
    width: 55px;
    height: 55px;
    display: grid;
    place-items: center;
    margin-bottom: 24px;
    border-radius: 16px;
    color: white;
    background: var(--blue);
    box-shadow: 0 8px 18px rgba(37,99,235,.2);
}

.verify-top-icon svg {
    width: 27px;
    height: 27px;
}

.verify-heading .eyebrow {
    display: block;
    margin-bottom: 8px;
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}

.verify-heading h2 {
    margin-bottom: 10px;
    color: var(--navy);
    font-size: clamp(26px, 3vw, 32px);
    line-height: 1.2;
    letter-spacing: -.8px;
}

.verify-heading p {
    margin-bottom: 24px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

.email-pill {
    display: inline-block;
    padding: 3px 8px;
    background: #e0f2fe;
    color: var(--blue);
    border-radius: 6px;
    font-weight: 700;
    word-break: break-all;
}

.timer-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 22px;
    font-size: 12px;
}

.timer-label {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--muted);
    font-weight: 600;
}

.timer-countdown {
    color: var(--blue);
    font-weight: 800;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

.timer-countdown.expiring {
    color: var(--danger);
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 15px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.alert-danger {
    background: var(--danger-bg);
    border: 1px solid var(--danger-border);
    color: var(--danger);
}

.alert-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: var(--blue);
}

.alert-dev {
    background: #fefce8;
    border: 1px solid #fef08a;
    color: #854d0e;
    font-size: 12px;
}

/* 6-DIGIT OTP INPUT WRAPPER */
.otp-inputs-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 22px;
}

.otp-box {
    width: 100%;
    height: 56px;
    text-align: center;
    font-size: 24px;
    font-weight: 800;
    color: var(--navy);
    border: 2px solid var(--border);
    border-radius: 10px;
    background: white;
    transition: all .15s ease;
}

.otp-box:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(37,99,235,.15);
    background: #ffffff;
}

.otp-box.filled {
    border-color: #94a3b8;
    background: #f8fafc;
}

.submit-btn {
    width: 100%;
    min-height: 50px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: 0;
    border-radius: 9px;
    color: white;
    background: var(--blue);
    box-shadow: 0 6px 16px rgba(37,99,235,.18);
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: background .15s, transform .15s, box-shadow .15s;
}

.submit-btn:hover {
    background: var(--blue-hover);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(37,99,235,.25);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.resend-section {
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 12px;
}

.resend-btn {
    background: transparent;
    border: 0;
    color: var(--blue);
    font-weight: 750;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 5px;
    transition: color .15s;
}

.resend-btn:hover:not(:disabled) {
    color: var(--blue-dark);
    text-decoration: underline;
}

.resend-btn:disabled {
    color: var(--muted);
    cursor: not-allowed;
}

.back-link {
    color: var(--muted);
    text-decoration: none;
}

.back-link:hover {
    color: var(--navy);
    text-decoration: underline;
}

@media (max-width: 900px) {
    .page-wrapper {
        grid-template-columns: 1fr;
    }
    .visual-panel {
        min-height: auto;
        padding: 30px 6%;
    }
    .verify-panel {
        padding: 35px 6%;
    }
}
</style>
</head>
<body>

<div class="page-wrapper">

    <!-- LEFT VISUAL PANEL -->
    <section class="visual-panel">
        <header class="brand-wrap">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div>
                <div class="brand-title">LASUSTECH</div>
                <div class="brand-sub">Secure Voting System</div>
            </div>
        </header>

        <div class="visual-center">
            <h1>Email Security Verification</h1>
            <p>
                To ensure every voter is unique and legitimate, please enter the single-use 6-digit confirmation code delivered to your registered email address.
            </p>

            <div class="steps-list">
                <div class="step-item">
                    <div class="step-num done">✓</div>
                    <div class="step-content">
                        <h3>Personal Details</h3>
                        <p><?= e($pendingName) ?> &bull; <?= e($pendingMatric) ?></p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-num active">2</div>
                    <div class="step-content">
                        <h3>Verify Confirmation Code</h3>
                        <p>Code dispatched to <?= e($pendingEmail) ?></p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h3>Vote & Participate</h3>
                        <p>Access active faculty and departmental ballots</p>
                    </div>
                </div>
            </div>
        </div>

        <footer class="visual-footer">
            <span>LASUSTECH · Student Election Portal</span>
            <span>Zero-Trust Verification</span>
        </footer>
    </section>

    <!-- RIGHT: VERIFICATION FORM PANEL -->
    <main class="verify-panel">
        <div class="verify-content">

            <div class="verify-top-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>

            <div class="verify-heading">
                <span class="eyebrow">Identity confirmation</span>
                <h2>Enter Security Code</h2>
                <p>
                    We sent a 6-digit confirmation code to <span class="email-pill"><?= e($pendingEmail) ?></span>.
                </p>
            </div>

            <?php if (!empty($debugOtp)): ?>
                <div class="alert alert-dev">
                    <strong>Dev Mode Alert:</strong> SMTP credentials not configured yet. Your verification code is <strong><?= e($debugOtp) ?></strong> (also saved in <code>logs/mail.log</code>).
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger" id="errorMessage" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <div class="timer-card">
                <div class="timer-label">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Code Expiration</span>
                </div>
                <div class="timer-countdown" id="timerDisplay">
                    <?= sprintf("%02d:%02d", $remainingMinutes, $remainingSecs) ?>
                </div>
            </div>

            <form action="verify_registration.php" method="POST" id="verifyForm">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["verify_csrf_token"]) ?>">
                <input type="hidden" name="verification_id" value="<?= $verificationId ?>">
                <input type="hidden" name="otp" id="combinedOtp" value="">

                <div class="otp-inputs-grid" id="otpGrid">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" autocomplete="one-time-code" autofocus required>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" required>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" required>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" required>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" required>
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-box" required>
                </div>

                <button type="submit" class="submit-btn" id="verifyBtn">
                    <span>Confirm & Create Account</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </form>

            <div class="resend-section">
                <div>
                    <button type="button" class="resend-btn" id="resendBtn" disabled>
                        Resend Code (<span id="resendTimer">60</span>s)
                    </button>
                </div>
                <div>
                    <a href="signup.php" class="back-link">&larr; Change Details</a>
                </div>
            </div>

        </div>
    </main>

</div>

<script>
// OTP 6-box input navigation & auto-advance
const otpBoxes = Array.from(document.querySelectorAll(".otp-box"));
const combinedInput = document.getElementById("combinedOtp");
const verifyForm = document.getElementById("verifyForm");
const verifyBtn = document.getElementById("verifyBtn");
const resendBtn = document.getElementById("resendBtn");
const resendTimerSpan = document.getElementById("resendTimer");
const timerDisplay = document.getElementById("timerDisplay");

function updateCombinedOtp() {
    const code = otpBoxes.map(b => b.value.trim()).join("");
    combinedInput.value = code;
    return code;
}

otpBoxes.forEach((box, idx) => {
    box.addEventListener("input", (e) => {
        const val = box.value.replace(/\D/g, "");
        box.value = val ? val[0] : "";
        if (box.value) {
            box.classList.add("filled");
            if (idx < otpBoxes.length - 1) {
                otpBoxes[idx + 1].focus();
            }
        } else {
            box.classList.remove("filled");
        }
        updateCombinedOtp();
    });

    box.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !box.value && idx > 0) {
            otpBoxes[idx - 1].focus();
            otpBoxes[idx - 1].value = "";
            otpBoxes[idx - 1].classList.remove("filled");
            updateCombinedOtp();
        }
    });

    // Handle full paste (e.g. user pastes 6-digit code)
    box.addEventListener("paste", (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData("text").replace(/\D/g, "");
        if (pasted.length >= 6) {
            for (let i = 0; i < 6; i++) {
                otpBoxes[i].value = pasted[i];
                otpBoxes[i].classList.add("filled");
            }
            otpBoxes[5].focus();
            updateCombinedOtp();
        }
    });
});

// Form submit
verifyForm.addEventListener("submit", function (e) {
    const code = updateCombinedOtp();
    if (code.length !== 6) {
        e.preventDefault();
        alert("Please enter all 6 digits of your confirmation code.");
        return;
    }
    verifyBtn.disabled = true;
    verifyBtn.querySelector("span").textContent = "Verifying...";
});

// Expiry countdown timer
let secondsLeft = <?= (int)$remainingSeconds ?>;
const expiryInterval = setInterval(() => {
    if (secondsLeft <= 0) {
        clearInterval(expiryInterval);
        timerDisplay.textContent = "Expired";
        timerDisplay.classList.add("expiring");
        alert("Your 10-minute verification code has expired. Please request a new code.");
        return;
    }
    secondsLeft--;
    const mins = Math.floor(secondsLeft / 60);
    const secs = secondsLeft % 60;
    timerDisplay.textContent = String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
    if (secondsLeft < 60) {
        timerDisplay.classList.add("expiring");
    }
}, 1000);

// Resend countdown (60 seconds)
let resendSeconds = 60;
const resendInterval = setInterval(() => {
    resendSeconds--;
    if (resendSeconds <= 0) {
        clearInterval(resendInterval);
        resendBtn.disabled = false;
        resendBtn.textContent = "Resend Code";
    } else {
        resendTimerSpan.textContent = resendSeconds;
    }
}, 1000);

resendBtn.addEventListener("click", function () {
    resendBtn.disabled = true;
    resendBtn.textContent = "Sending...";

    const fd = new FormData();
    fd.append("action", "resend");
    fd.append("csrf_token", "<?= e($_SESSION['signup_csrf_token'] ?? '') ?>");
    fd.append("is_ajax", "1");

    fetch("send_registration_code.php", {
        method: "POST",
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || "Could not resend code.");
            resendBtn.disabled = false;
            resendBtn.textContent = "Resend Code";
        }
    })
    .catch(() => {
        alert("An error occurred trying to resend code.");
        resendBtn.disabled = false;
        resendBtn.textContent = "Resend Code";
    });
});
</script>

</body>
</html>


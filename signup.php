<?php
session_start();

// Redirect to dashboard if already authenticated
if (isset($_SESSION["user_id"])) {
    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: admin/dashboard.php");
        exit;
    }
    header("Location: voter/dashboard.php");
    exit;
}

// Generate CSRF token for signup
if (empty($_SESSION["signup_csrf_token"])) {
    $_SESSION["signup_csrf_token"] = bin2hex(random_bytes(32));
}

// Helper function for HTML escaping
function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$error = $_SESSION["signup_error"] ?? "";
unset($_SESSION["signup_error"]);

$old = $_SESSION["signup_old"] ?? [];
unset($_SESSION["signup_old"]);

$fullName   = $old["full_name"] ?? "";
$studentId  = $old["student_id"] ?? "";
$department = $old["department"] ?? "";
$level      = (int)($old["level"] ?? 100);
$email      = $old["email"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Self-Registration | Secure Online Voting System</title>

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

    /* Semantic Alert */
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

button, input, select {
    font: inherit;
}

.page-wrapper {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.05fr .95fr;
}

/* LEFT VISUAL PANEL (30% Solid Navy Structure) */
.visual-panel {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 35px 7%;
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
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--blue);
    display: grid;
    place-items: center;
    color: white;
    box-shadow: 0 6px 18px rgba(37,99,235,.25);
}

.brand-icon svg {
    width: 24px;
    height: 24px;
}

.brand-title {
    font-size: 20px;
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
    margin: 30px 0;
    max-width: 480px;
}

.visual-center h1 {
    font-size: clamp(26px, 3.2vw, 36px);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -.6px;
    margin-bottom: 12px;
}

.visual-center p {
    font-size: 14px;
    color: #cbd5e1;
    line-height: 1.7;
    margin-bottom: 26px;
}

.features-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.feature-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 14px 16px;
}

.feature-card svg {
    width: 22px;
    height: 22px;
    color: #60a5fa;
    margin-bottom: 8px;
}

.feature-card h3 {
    font-size: 13px;
    font-weight: 750;
    color: white;
    margin-bottom: 3px;
}

.feature-card p {
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.4;
    margin: 0;
}

.visual-footer {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #94a3b8;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 20px;
}

/* RIGHT: FORM PANEL */
.signup-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 9%;
    background: var(--page);
    overflow-y: auto;
}

.signup-content {
    width: 100%;
    max-width: 480px;
}

.signup-top-icon {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    margin-bottom: 20px;
    border-radius: 15px;
    color: white;
    background: var(--blue);
    box-shadow: 0 8px 18px rgba(37,99,235,.2);
}

.signup-top-icon svg {
    width: 25px;
    height: 25px;
}

.signup-heading .eyebrow {
    display: block;
    margin-bottom: 6px;
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}

.signup-heading h2 {
    margin-bottom: 8px;
    color: var(--navy);
    font-size: clamp(26px, 2.8vw, 32px);
    line-height: 1.2;
    letter-spacing: -.8px;
}

.signup-heading p {
    margin-bottom: 22px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.alert-danger {
    background: var(--danger-bg);
    border: 1px solid var(--danger-border);
    color: var(--danger);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    margin-bottom: 6px;
    color: var(--navy);
    font-size: 12.5px;
    font-weight: 750;
}

.required {
    color: #dc2626;
}

.input-wrap {
    position: relative;
}

input, select {
    width: 100%;
    min-height: 46px;
    padding: 11px 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    color: var(--navy);
    font-size: 13.5px;
    transition: border-color .15s, box-shadow .15s;
}

input::placeholder {
    color: #a0acbc;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

.password-input {
    padding-right: 44px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    padding: 6px;
    border: 0;
    border-radius: 6px;
    color: var(--muted);
    background: transparent;
    cursor: pointer;
}

.toggle-password:hover {
    color: var(--blue);
    background: #eff6ff;
}

.toggle-password svg {
    width: 18px;
    height: 18px;
}

.submit-btn {
    width: 100%;
    min-height: 48px;
    margin-top: 8px;
    padding: 12px 16px;
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
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(37,99,235,.26);
}

.submit-btn:active {
    transform: translateY(0);
}

.submit-btn svg {
    width: 17px;
    height: 17px;
    transition: transform .2s;
}

.submit-btn:hover svg {
    transform: translateX(3px);
}

.submit-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}

.signin-prompt {
    margin-top: 20px;
    text-align: center;
    font-size: 13px;
    color: var(--muted);
}

.signin-prompt a {
    color: var(--blue);
    font-weight: 750;
    text-decoration: none;
}

.signin-prompt a:hover {
    text-decoration: underline;
}

.security-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 22px;
    color: var(--muted);
    font-size: 11px;
}

.security-badge svg {
    width: 15px;
    height: 15px;
    color: var(--blue);
}

@media (max-width: 900px) {
    .page-wrapper {
        grid-template-columns: 1fr;
    }
    .visual-panel {
        min-height: auto;
        padding: 30px 6%;
    }
    .signup-panel {
        padding: 30px 6%;
    }
    .features-grid {
        grid-template-columns: 1fr;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
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
            <h1>Student Self-Registration</h1>
            <p>
                Create your student voter account in seconds. After entering your academic details, you'll receive a secure single-use 6-digit confirmation code via email.
            </p>

            <div class="features-grid">
                <div class="feature-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <h3>1 Matric = 1 Account</h3>
                    <p>Strict uniqueness prevents duplicate or proxy voting accounts.</p>
                </div>

                <div class="feature-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <h3>10-Minute OTP</h3>
                    <p>Time-sensitive confirmation codes expire automatically.</p>
                </div>

                <div class="feature-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <h3>Password Security</h3>
                    <p>Protected by modern, salted cryptographic hashing.</p>
                </div>

                <div class="feature-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <h3>Anti-Spam Shield</h3>
                    <p>Resend cooldown & attempt limiting safeguard the platform.</p>
                </div>
            </div>
        </div>

        <footer class="visual-footer">
            <span>LASUSTECH · Student Election Portal</span>
            <span>Make your voice count.</span>
        </footer>
    </section>

    <!-- RIGHT: REGISTRATION FORM PANEL -->
    <main class="signup-panel">
        <div class="signup-content">

            <div class="signup-top-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
            </div>

            <div class="signup-heading">
                <span class="eyebrow">Student Onboarding</span>
                <h2>Create Your Account</h2>
                <p>Register using your official matric number and email address to participate in university elections.</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="send_registration_code.php" method="POST" id="signupForm">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["signup_csrf_token"]) ?>">

                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= e($fullName) ?>"
                        placeholder="e.g. John Doe"
                        maxlength="150"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id">Matric Number <span class="required">*</span></label>
                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            value="<?= e($studentId) ?>"
                            placeholder="e.g. 220404010013"
                            maxlength="30"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="level">Level <span class="required">*</span></label>
                        <select id="level" name="level" required>
                            <?php foreach ([100, 200, 300, 400, 500, 600, 700] as $lvl): ?>
                                <option value="<?= $lvl ?>" <?= $level === $lvl ? "selected" : "" ?>>
                                    <?= $lvl ?> Level
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="department">Department <span class="required">*</span></label>
                    <input
                        type="text"
                        id="department"
                        name="department"
                        value="<?= e($department) ?>"
                        placeholder="e.g. Computer Engineering"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        placeholder="e.g. student@example.com"
                        maxlength="190"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="password-input"
                                placeholder="At least 6 characters"
                                minlength="6"
                                required
                            >
                            <button
                                type="button"
                                class="toggle-password"
                                data-target="password"
                                aria-label="Toggle password"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <div class="input-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="password-input"
                                placeholder="Re-enter password"
                                minlength="6"
                                required
                            >
                            <button
                                type="button"
                                class="toggle-password"
                                data-target="confirm_password"
                                aria-label="Toggle password"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <span>Send Confirmation Code</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="signin-prompt">
                Already registered? <a href="login.php">Sign In to your account</a>
            </div>

            <div class="security-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Protected Student Election Environment
            </div>

        </div>
    </main>

</div>

<script>
// Toggle password visibility
document.querySelectorAll(".toggle-password").forEach(btn => {
    btn.addEventListener("click", () => {
        const targetId = btn.getAttribute("data-target");
        const input = document.getElementById(targetId);
        if (!input) return;
        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
    });
});

// Client-side quick validation on submit
const signupForm = document.getElementById("signupForm");
const submitBtn = document.getElementById("submitBtn");

signupForm.addEventListener("submit", (e) => {
    const p1 = document.getElementById("password").value;
    const p2 = document.getElementById("confirm_password").value;

    if (p1 !== p2) {
        e.preventDefault();
        alert("Passwords do not match. Please ensure both passwords match.");
        return;
    }

    if (p1.length < 6) {
        e.preventDefault();
        alert("Password must contain at least 6 characters.");
        return;
    }

    submitBtn.disabled = true;
    submitBtn.querySelector("span").textContent = "Sending Confirmation Code...";
});
</script>

</body>
</html>


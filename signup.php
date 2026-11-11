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
    background: var(--navy);
}

button, input, select {
    font: inherit;
}

.page-wrapper {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.05fr .95fr;
}

/* LEFT VISUAL PANEL */
.visual-panel {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 35px 7%;
    color: var(--navy);
    background: var(--page);
}

.visual-panel::before,
.visual-panel::after {
    content: "";
    position: absolute;
    border: 1px solid rgba(15,32,56,.08);
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
    color: var(--navy);
}

.brand-sub {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

/* LEFT TEXT & ANIMATION STAGE (Identical aesthetic to login.php, zero gradients) */
.visual-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 530px;
    margin: auto 0;
    padding: 30px 0;
    animation: contentReveal .9s ease both;
}

.visual-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    padding: 8px 14px;
    border: 1px solid rgba(37,99,235,.3);
    border-radius: 30px;
    background: rgba(37,99,235,.08);
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--blue);
    box-shadow: 0 0 0 4px rgba(37,99,235,.2);
    animation: dotPulse 2s infinite;
}

@keyframes dotPulse {
    50% { box-shadow: 0 0 0 8px rgba(37,99,235,0); }
}

.visual-content h1 {
    max-width: 500px;
    margin-bottom: 14px;
    color: var(--navy);
    font-size: clamp(32px,3.6vw,48px);
    line-height: 1.15;
    letter-spacing: -1.5px;
}

.visual-content h1 span {
    color: var(--blue);
}

.visual-description {
    max-width: 420px;
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.8;
}

.animation-stage {
    position: relative;
    width: min(100%, 420px);
    height: 290px;
    margin: 25px auto 0;
    display: grid;
    place-items: center;
    perspective: 900px;
}

.orbit {
    position: absolute;
    width: 280px;
    height: 280px;
    border: 1px solid rgba(15,32,56,.12);
    border-radius: 50%;
    animation: orbitRotate 25s linear infinite;
}

.orbit::before {
    content: "";
    position: absolute;
    width: 8px;
    height: 8px;
    top: 35px;
    right: 30px;
    border-radius: 50%;
    background: var(--blue);
    box-shadow: 0 0 10px rgba(37,99,235,.6);
}

.orbit-two {
    width: 340px;
    height: 220px;
    transform: rotate(-25deg);
    border-style: dashed;
    border-color: rgba(15,32,56,.14);
    animation: orbitReverse 32s linear infinite;
}

@keyframes orbitRotate {
    to { transform: rotate(360deg); }
}

@keyframes orbitReverse {
    to { transform: rotate(-385deg); }
}

/* FLOATING CREDENTIAL CARD */
.credential-card {
    position: relative;
    z-index: 2;
    width: 270px;
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface);
    box-shadow: 0 20px 45px rgba(0,0,0,.22);
    color: var(--navy);
    animation: credentialFloat 5s ease-in-out infinite;
    transform-style: preserve-3d;
}

@keyframes credentialFloat {
    0%,100% { transform: translateY(0) rotate(-2deg); }
    50% { transform: translateY(-13px) rotate(1.5deg); }
}

.card-header-mini {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.card-title-mini {
    font-size: 10.5px;
    font-weight: 900;
    letter-spacing: .6px;
    color: var(--navy);
}

.card-badge-mini {
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    color: white;
    background: var(--blue);
}

.card-badge-mini svg {
    width: 15px;
    height: 15px;
}

.credential-user-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.user-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #dbeafe;
    color: var(--blue);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.user-avatar-circle svg {
    width: 20px;
    height: 20px;
}

.user-meta-lines {
    flex: 1;
}

.meta-line {
    border-radius: 20px;
    background: #cbd5e1;
}

.meta-wide {
    width: 90px;
    height: 7px;
    margin-bottom: 5px;
    background: var(--navy);
}

.meta-short {
    width: 60px;
    height: 5px;
}

.credential-status-box {
    background: var(--page);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
}

.status-lbl {
    color: var(--muted);
    font-weight: 750;
}

.status-val {
    font-weight: 800;
}

.status-val.verified {
    color: var(--blue);
}

.status-val.active-otp {
    color: #0284c7;
}

.card-footer-mini {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    color: var(--muted);
    font-size: 9px;
    font-weight: 750;
    letter-spacing: .4px;
}

/* FLOATING BADGES */
.float-icon {
    position: absolute;
    z-index: 3;
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(37,99,235,.14);
    border-radius: 12px;
    color: var(--blue);
    background: rgba(37,99,235,.06);
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
}

.float-icon svg {
    width: 20px;
    height: 20px;
}

.float-user {
    top: 20px;
    right: 28px;
    animation: iconFloat 4s ease-in-out infinite;
}

.float-shield {
    bottom: 24px;
    left: 22px;
    animation: iconFloat 5s ease-in-out infinite reverse;
}

@keyframes iconFloat {
    0%,100% { transform: translateY(0) rotate(0); }
    50% { transform: translateY(-12px) rotate(7deg); }
}

@keyframes contentReveal {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes formReveal {
    from { opacity: 0; transform: translateX(25px); }
    to { opacity: 1; transform: translateX(0); }
}

.visual-footer {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--muted);
    border-top: 1px solid rgba(15,32,56,.1);
    padding-top: 20px;
}

.visual-footer span:last-child {
    color: var(--blue);
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

/* Animated full-screen sign-up design */
#network-animation {
    position: fixed;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.page-wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 28px 16px;
}

.visual-panel {
    display: none;
}

.signup-panel {
    width: 100%;
    min-height: 100vh;
    padding: 0;
    overflow: visible;
    background: transparent;
}

.signup-content {
    max-width: 520px;
    padding: 30px;
    border: 1px solid rgba(255,255,255,.55);
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(0,0,0,.4);
    animation: contentReveal .8s ease both;
}

.portal-tagline {
    margin: -30px -30px 22px;
    padding: 18px 22px;
    border-radius: 11px 11px 0 0;
    background: var(--blue);
    text-align: center;
}

.portal-tagline h1 {
    margin: 0;
    color: #fff;
    font-size: clamp(18px, 5vw, 23px);
    font-weight: 850;
    letter-spacing: .3px;
    line-height: 1.25;
}

.portal-tagline h1 span {
    color: #fff;
}

.portal-tagline p {
    margin-top: 5px;
    color: rgba(255,255,255,.9);
    font-size: 12px;
    line-height: 1.6;
}

.signup-top-icon {
    display: none;
}

.signup-heading {
    margin: 0 0 20px;
}

.signup-heading .eyebrow {
    margin-bottom: 6px;
    color: var(--blue);
}

.signup-heading h2 {
    margin-bottom: 6px;
    color: var(--navy);
    font-size: 21px;
    letter-spacing: -.4px;
}

.signup-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.signup-content input,
.signup-content select {
    min-height: 44px;
}

@media (max-width: 900px) {
    .page-wrapper {
        display: flex;
        min-height: 100vh;
        padding: 20px 14px;
    }

    .signup-panel {
        min-height: 100vh;
        padding: 0;
    }

    .signup-content {
        max-width: 520px;
        padding: 25px;
    }

    .portal-tagline {
        margin: -25px -25px 22px;
        padding: 18px 25px;
    }
}
</style>
</head>
<body>

<canvas id="network-animation" aria-hidden="true"></canvas>
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

        <div class="visual-content">

            <div class="visual-tag">
                <span class="live-dot"></span>
                Student Self-Registration
            </div>

            <h1>
                CLAIM YOUR<br>
                STUDENT <span>VOICE.</span>
            </h1>

            <p class="visual-description">
                Register your verified academic credentials to access your voter dashboard, review candidates, and participate in university governance.
            </p>

            <div class="animation-stage">

                <div class="orbit"></div>
                <div class="orbit orbit-two"></div>

                <div class="float-icon float-user">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <line x1="20" y1="8" x2="20" y2="14"/>
                        <line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                </div>

                <div class="float-icon float-shield">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>

                <!-- FLOATING DIGITAL VOTER CREDENTIAL CARD -->
                <div class="credential-card">
                    <div class="card-header-mini">
                        <div class="card-title-mini">STUDENT VOTER PASS</div>
                        <div class="card-badge-mini">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                    </div>

                    <div class="credential-user-row">
                        <div class="user-avatar-circle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="user-meta-lines">
                            <div class="meta-line meta-wide"></div>
                            <div class="meta-line meta-short"></div>
                        </div>
                    </div>

                    <div class="credential-status-box">
                        <div class="status-item">
                            <span class="status-lbl">MATRIC CHECK</span>
                            <span class="status-val verified">✓ UNIQUE</span>
                        </div>
                        <div class="status-item">
                            <span class="status-lbl">SECURITY OTP</span>
                            <span class="status-val active-otp">10 MIN EXPIRY</span>
                        </div>
                    </div>

                    <div class="card-footer-mini">
                        <span>LASUSTECH PORTAL</span>
                        <span style="color:var(--blue); font-weight:800;">VERIFIED</span>
                    </div>
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

            <div class="portal-tagline">
                <h1>CLAIM YOUR<br>STUDENT <span>VOICE.</span></h1>
                <p>Register to take part in student elections.</p>
            </div>

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
<script>
(() => {
    const canvas = document.getElementById("network-animation");
    const context = canvas.getContext("2d");
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let width = 0;
    let height = 0;
    let frame = 0;

    function resize() {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function draw() {
        context.clearRect(0, 0, width, height);
        const time = reducedMotion ? 0 : frame * 0.012;
        const center = height * 0.58;
        const strands = Math.max(24, Math.min(42, Math.round(width / 30)));

        for (let strand = 0; strand < strands; strand++) {
            const offset = (strand - (strands - 1) / 2) * (height * 0.006);
            const phase = strand * 0.39 + time;

            context.beginPath();
            for (let x = 0; x <= width; x += 8) {
                const y = center + offset
                    + Math.sin(x * 0.008 + phase) * height * 0.13
                    + Math.sin(x * 0.0025 - phase * 0.7) * height * 0.055;
                if (x === 0) context.moveTo(x, y);
                else context.lineTo(x, y);
            }
            context.strokeStyle = `rgba(37, 99, 235, .22)`;
            context.lineWidth = strand % 7 === 0 ? 1.2 : .65;
            context.shadowColor = "rgba(0, 210, 220, .45)";
            context.shadowBlur = strand % 7 === 0 ? 8 : 2;
            context.stroke();
            context.shadowBlur = 0;

            for (let point = 0; point < 5; point++) {
                const x = ((point * 0.23 + strand * 0.137 + time * 0.025) % 1) * width;
                const y = center + offset
                    + Math.sin(x * 0.008 + phase) * height * 0.13
                    + Math.sin(x * 0.0025 - phase * 0.7) * height * 0.055;
                context.beginPath();
                context.arc(x, y, point === 2 ? 2 : 1.25, 0, Math.PI * 2);
                context.fillStyle = "rgba(37, 99, 235, .9)";
                context.shadowColor = context.fillStyle;
                context.shadowBlur = 10;
                context.fill();
                context.shadowBlur = 0;
            }
        }

        if (!reducedMotion) {
            frame++;
            requestAnimationFrame(draw);
        }
    }

    resize();
    window.addEventListener("resize", resize);
    draw();
})();
</script>

</body>
</html>

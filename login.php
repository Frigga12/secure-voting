<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flashSuccess = $_SESSION['login_flash_success'] ?? "";
unset($_SESSION['login_flash_success']);

if (isset($_GET['registered']) && $_GET['registered'] === '1' && empty($flashSuccess)) {
    $flashSuccess = "Registration successful! You can now log in using your Matric Number and password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Secure Online Voting System</title>

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

button,
input {
    font: inherit;
}

/* Main split-screen layout */

.page-wrapper {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.08fr .92fr;
}

/* LEFT BRAND PANEL (Solid Navy 30% Structural Presence) */

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

/* BRAND */

.brand {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-logo {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 12px;
    color: white;
    background: var(--blue);
}

.brand-logo svg {
    width: 25px;
    height: 25px;
}

.brand-name {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: .2px;
}

.brand-subtitle {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 10px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
}

/* LEFT TEXT */

.visual-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 530px;
    margin: auto 0;
    padding: 40px 0;
    animation: contentReveal .9s ease both;
}

.visual-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 22px;
    padding: 8px 14px;
    border: 1px solid rgba(37,99,235,.4);
    border-radius: 30px;
    background: rgba(37,99,235,.15);
    color: #93c5fd;
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
    margin-bottom: 17px;
    color: white;
    font-size: clamp(35px,4vw,55px);
    line-height: 1.13;
    letter-spacing: -1.7px;
}

.visual-content h1 span {
    color: #60a5fa;
}

.visual-description {
    max-width: 410px;
    color: #cbd5e1;
    font-size: 14px;
    line-height: 1.9;
}

/* ANIMATED BALLOT ILLUSTRATION */

.animation-stage {
    position: relative;
    width: min(100%, 440px);
    height: 300px;
    margin: 30px auto 0;
    display: grid;
    place-items: center;
    perspective: 900px;
}

.orbit {
    position: absolute;
    width: 290px;
    height: 290px;
    border: 1px solid rgba(148,210,226,.18);
    border-radius: 50%;
    animation: orbitRotate 25s linear infinite;
}

.orbit::before {
    content: "";
    position: absolute;
    width: 9px;
    height: 9px;
    top: 40px;
    right: 32px;
    border-radius: 50%;
    background: var(--blue);
    box-shadow: 0 0 10px rgba(37,99,235,.6);
}

.orbit-two {
    width: 350px;
    height: 230px;
    transform: rotate(-25deg);
    border-style: dashed;
    border-color: rgba(255,255,255,.12);
    animation: orbitReverse 32s linear infinite;
}

@keyframes orbitRotate {
    to { transform: rotate(360deg); }
}

@keyframes orbitReverse {
    to { transform: rotate(-385deg); }
}

.ballot-card {
    position: relative;
    z-index: 2;
    width: 265px;
    padding: 19px;
    border: 1px solid var(--border);
    border-radius: 17px;
    background: var(--surface);
    box-shadow: 0 20px 45px rgba(0,0,0,.22);
    color: var(--navy);
    animation: ballotFloat 5s ease-in-out infinite;
    transform-style: preserve-3d;
}

@keyframes ballotFloat {
    0%,100% { transform: translateY(0) rotate(-2deg); }
    50% { transform: translateY(-13px) rotate(1.5deg); }
}

.ballot-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 13px;
    margin-bottom: 13px;
    border-bottom: 1px solid var(--border);
}

.ballot-heading {
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .5px;
}

.ballot-mini-logo {
    width: 27px;
    height: 27px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    color: white;
    background: var(--blue);
}

.ballot-mini-logo svg {
    width: 16px;
    height: 16px;
}

.ballot-subtitle {
    margin-bottom: 12px;
    color: var(--muted);
    font-size: 9px;
}

.candidate-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px;
    margin-bottom: 8px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--page);
}

.candidate-row:nth-of-type(3) {
    border-color: var(--blue);
    background: #eff6ff;
}

.candidate-avatar {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 9px;
    color: var(--blue);
    background: #dbeafe;
    font-size: 10px;
    font-weight: 800;
}

.candidate-detail {
    flex: 1;
    min-width: 0;
}

.candidate-line {
    width: 72px;
    height: 6px;
    margin-bottom: 5px;
    border-radius: 20px;
    background: #94a3b8;
}

.candidate-small-line {
    width: 46px;
    height: 4px;
    border-radius: 20px;
    background: var(--border);
}

.candidate-check {
    width: 19px;
    height: 19px;
    display: grid;
    place-items: center;
    border: 1.5px solid #cbd5e1;
    border-radius: 50%;
    color: white;
    font-size: 11px;
}

.candidate-row:nth-of-type(3) .candidate-check {
    background: var(--blue);
    border-color: var(--blue);
}

.ballot-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    color: var(--muted);
    font-size: 9px;
    font-weight: 700;
}

.verified {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--blue);
}

.verified svg {
    width: 13px;
    height: 13px;
}

/* FLOATING ICONS */

.float-icon {
    position: absolute;
    z-index: 3;
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 13px;
    color: #93c5fd;
    background: rgba(255,255,255,.08);
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
}

.float-icon svg {
    width: 21px;
    height: 21px;
}

.float-lock {
    top: 25px;
    right: 32px;
    animation: iconFloat 4s ease-in-out infinite;
}

.float-check {
    bottom: 29px;
    left: 26px;
    animation: iconFloat 5s ease-in-out infinite reverse;
}

@keyframes iconFloat {
    0%,100% { transform: translateY(0) rotate(0); }
    50% { transform: translateY(-12px) rotate(7deg); }
}

/* BOTTOM NOTE */

.visual-footer {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    gap: 15px;
    color: #94a3b8;
    font-size: 10px;
}

.visual-footer span:last-child {
    color: #93c5fd;
}

/* RIGHT LOGIN PANEL */

.login-panel {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 45px 10%;
    background: #fbfcfe;
}

.login-content {
    width: 100%;
    max-width: 405px;
    animation: formReveal 1s .12s ease both;
}

@keyframes contentReveal {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes formReveal {
    from { opacity: 0; transform: translateX(25px); }
    to { opacity: 1; transform: translateX(0); }
}

.login-top-icon {
    width: 55px;
    height: 55px;
    display: grid;
    place-items: center;
    margin-bottom: 25px;
    border-radius: 16px;
    color: white;
    background: var(--blue);
    box-shadow: 0 8px 18px rgba(37,99,235,.2);
}

.login-top-icon svg {
    width: 27px;
    height: 27px;
}

.login-heading .eyebrow {
    display: block;
    margin-bottom: 9px;
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}

.login-heading h2 {
    margin-bottom: 10px;
    color: var(--navy);
    font-size: clamp(29px,3vw,35px);
    line-height: 1.2;
    letter-spacing: -1px;
}

.login-heading p {
    margin-bottom: 31px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.8;
}

.form-group {
    margin-bottom: 21px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: var(--navy);
    font-size: 13px;
    font-weight: 750;
}

.input-wrap {
    position: relative;
}

input {
    width: 100%;
    min-height: 50px;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    color: #253449;
    font-size: 14px;
    transition: border-color .2s, box-shadow .2s;
}

input::placeholder {
    color: #a0acbc;
}

input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(37,99,235,.11);
}

#password {
    padding-right: 49px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 10px;   
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    padding: 7px;
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
    width: 19px;
    height: 19px;
}

.login-button {
    width: 100%;
    min-height: 50px;
    margin-top: 7px;
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

.login-button:hover {
    background: var(--blue-hover);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(37,99,235,.25);
}

.login-button:active {
    transform: translateY(0);
}

.login-button svg {
    width: 18px;
    height: 18px;
    transition: transform .2s;
}

.login-button:hover svg {
    transform: translateX(4px);
}

.security-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    color: #8090a3;
    font-size: 11px;
}

.security-note svg {
    width: 16px;
    height: 16px;
    color: var(--blue);
}

.login-footer {
    margin-top: 46px;
    color: #9aa6b6;
    font-size: 11px;
    text-align: center;
}

/* TABLET */

@media (max-width: 900px) {
    .page-wrapper {
        grid-template-columns: 1fr 1fr;
    }

    .visual-panel {
        padding: 28px 6%;
    }

    .visual-content h1 {
        font-size: 39px;
    }

    .animation-stage {
        transform: scale(.88);
        transform-origin: center;
        margin-top: 5px;
    }

    .login-panel {
        padding: 35px 7%;
    }
}

/* MOBILE */

@media (max-width: 680px) {
    .page-wrapper {
        grid-template-columns: 1fr;
    }

    .visual-panel {
        min-height: auto;
        padding: 23px 7% 30px;
    }

    .visual-content {
        padding: 34px 0 0;
    }

    .visual-content h1 {
        max-width: 430px;
        font-size: clamp(32px,8vw,43px);
    }

    .visual-description {
        font-size: 13px;
    }

    .animation-stage {
        height: 255px;
        max-width: 390px;
        margin-top: 4px;
        transform: scale(.86);
    }

    .visual-footer {
        padding-top: 17px;
        border-top: 1px solid rgba(255,255,255,.12);
        font-size: 9px;
    }

    .login-panel {
        min-height: auto;
        padding: 45px 7%;
    }

    .login-content {
        max-width: 440px;
    }

    .login-footer {
        margin-top: 30px;
    }
}

@media (max-width: 380px) {
    .animation-stage {
        transform: scale(.73);
        margin-top: -20px;
        margin-bottom: -20px;
    }

    .visual-content h1 {
        font-size: 31px;
    }

    .visual-footer {
        flex-direction: column;
        gap: 6px;
    }
}

/* Accessibility */

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>
</head>

<body>

<div class="page-wrapper">

    <!-- LEFT: BRAND & BALLOT VISUAL -->
    <section class="visual-panel">

        <header class="brand">
            <div class="brand-logo">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3L20 6.5V11.5C20 16.4 16.5 20 12 21
                             C7.5 20 4 16.4 4 11.5V6.5L12 3Z"/>
                    <path d="M8.5 12L10.8 14.3L15.8 9.3"/>
                </svg>
            </div>

            <div>
                <div class="brand-name">Secure Voting System</div>
                <span class="brand-subtitle">Cybersecurity and Networking</span>
            </div>
        </header>

        <div class="visual-content">

            <div class="visual-tag">
                <span class="live-dot"></span>
                Student Election Portal
            </div>

            <h1>
                YOUR VOTE.<br>
                YOUR <span>VOICE.</span>
            </h1>

            <p class="visual-description">
                Participate in student elections through a guided
                digital voting experience. Make your choice and
                let your voice be represented.
            </p>

            <div class="animation-stage">

                <div class="orbit"></div>
                <div class="orbit orbit-two"></div>

                <div class="float-icon float-lock">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="10" width="14" height="11" rx="2"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    </svg>
                </div>

                <div class="float-icon float-check">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.5L10 17L19 7"/>
                    </svg>
                </div>

                <div class="ballot-card">

                    <div class="ballot-header">
                        <div class="ballot-heading">
                            STUDENT ELECTION
                        </div>

                        <div class="ballot-mini-logo">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12.5L10 17L19 7"/>
                            </svg>
                        </div>
                    </div>

                    <div class="ballot-subtitle">
                        Choose your preferred candidate
                    </div>

                    <div class="candidate-row">
                        <div class="candidate-avatar">A</div>
                        <div class="candidate-detail">
                            <div class="candidate-line"></div>
                            <div class="candidate-small-line"></div>
                        </div>
                        <div class="candidate-check"></div>
                    </div>

                    <div class="candidate-row">
                        <div class="candidate-avatar">B</div>
                        <div class="candidate-detail">
                            <div class="candidate-line"></div>
                            <div class="candidate-small-line"></div>
                        </div>
                        <div class="candidate-check"></div>
                    </div>

                    <div class="candidate-row">
                        <div class="candidate-avatar">C</div>
                        <div class="candidate-detail">
                            <div class="candidate-line"></div>
                            <div class="candidate-small-line"></div>
                        </div>
                        <div class="candidate-check">✓</div>
                    </div>

                    <div class="ballot-footer">
                        <span>YOUR SELECTION</span>
                        <span class="verified">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M8 12L11 15L16 9"/>
                            </svg>
                            CONFIRMED
                        </span>
                    </div>

                </div>

            </div>
        </div>

        <footer class="visual-footer">
            <span>LASUSTECH · Student Election Portal</span>
            <span>Make your voice count.</span>
        </footer>

    </section>


    <!-- RIGHT: LOGIN FORM -->
    <main class="login-panel">

        <div class="login-content">

            <div class="login-top-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="10" width="16" height="11" rx="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    <path d="M12 14V17"/>
                </svg>
            </div>

            <div class="login-heading">
                <span class="eyebrow">Account access</span>
                <h2>Welcome back.</h2>
                <p>
                    Sign in using your registered student ID
                    and password to continue to your election portal.
                </p>
            </div>

            <?php if (!empty($flashSuccess)): ?>
                <div style="background: #e8f8ef; border: 1px solid #a7f3d0; border-radius: 9px; padding: 13px 15px; margin-bottom: 22px; color: #167548; font-size: 13px; line-height: 1.5; display: flex; align-items: center; gap: 9px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12L11 15L16 9"/>
                    </svg>
                    <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, "UTF-8") ?></span>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">

                <div class="form-group">
                    <label for="student_id">Student ID</label>

                    <input
                        type="text"
                        id="student_id"
                        name="student_id"
                        placeholder="Enter your Student ID"
                        autocomplete="username"
                        maxlength="30"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <div class="input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5
                                         S21.5 12 21.5 12
                                         S18 18.5 12 18.5
                                         S2.5 12 2.5 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-button">
                    Sign In

                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M5 12H19M12 5L19 12L12 19"/>
                    </svg>
                </button>

            </form>

            <div style="margin-top: 18px; text-align: center; font-size: 13px; color: var(--muted);">
                Don't have an account? <a href="signup.php" style="color: var(--blue); font-weight: 750; text-decoration: none;">Create student account</a>
            </div>

            <div class="security-note">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="10" width="14" height="11" rx="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                </svg>

                Student account access
            </div>

            <footer class="login-footer">
                Secure Online Voting System &copy; <?= date("Y") ?>
            </footer>

        </div>
    </main>

</div>

<script>
const passwordInput = document.getElementById("password");
const toggleButton = document.getElementById("togglePassword");

toggleButton.addEventListener("click", function () {
    const shouldShow = passwordInput.type === "password";

    passwordInput.type = shouldShow ? "text" : "password";

    toggleButton.setAttribute(
        "aria-pressed",
        String(shouldShow)
    );

    toggleButton.setAttribute(
        "aria-label",
        shouldShow ? "Hide password" : "Show password"
    );
});
</script>

</body>
</html>

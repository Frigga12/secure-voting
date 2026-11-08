<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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
    --navy: #102541;
    --navy-light: #1b4168;
    --blue: #2563eb;
    --teal: #0d9488;
    --white: #ffffff;
    --muted: #74839a;
    --border: #dce5ef;
    --page: #f3f6fb;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    font-family: "Segoe UI", Arial, sans-serif;
    color: #253449;
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

/* LEFT ANIMATION PANEL */

.visual-panel {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 35px 7%;
    color: white;
    background:
        radial-gradient(circle at 85% 15%, rgba(13,148,136,.35), transparent 31%),
        radial-gradient(circle at 5% 90%, rgba(37,99,235,.28), transparent 38%),
        linear-gradient(145deg, #102541 0%, #15395d 55%, #102e4b 100%);
}

.visual-panel::before,
.visual-panel::after {
    content: "";
    position: absolute;
    border: 1px solid rgba(255,255,255,.09);
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

.glow {
    position: absolute;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(13,148,136,.16);
    filter: blur(70px);
    pointer-events: none;
    animation: glowMove 9s ease-in-out infinite alternate;
}

.glow-one {
    top: 15%;
    right: 10%;
}

.glow-two {
    bottom: 5%;
    left: 10%;
    background: rgba(37,99,235,.22);
    animation-delay: -4s;
}

@keyframes glowMove {
    from { transform: translate(0,0) scale(.9); }
    to { transform: translate(35px,-30px) scale(1.2); }
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
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 13px;
    color: #7ff3df;
    background: rgba(255,255,255,.11);
    border: 1px solid rgba(255,255,255,.16);
    box-shadow: 0 5px 20px rgba(0,0,0,.12);
}

.brand-logo svg {
    width: 27px;
    height: 27px;
}

.brand-name {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: .2px;
}

.brand-subtitle {
    display: block;
    margin-top: 3px;
    color: #afc7dd;
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
    padding: 8px 13px;
    border: 1px solid rgba(94,234,212,.3);
    border-radius: 30px;
    background: rgba(13,148,136,.13);
    color: #9df0e3;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #5eead4;
    box-shadow: 0 0 0 4px rgba(94,234,212,.13);
    animation: dotPulse 2s infinite;
}

@keyframes dotPulse {
    50% { box-shadow: 0 0 0 8px rgba(94,234,212,0); }
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
    color: #5eead4;
}

.visual-description {
    max-width: 410px;
    color: #c1d3e6;
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
    background: #5eead4;
    box-shadow: 0 0 15px rgba(94,234,212,.7);
}

.orbit-two {
    width: 350px;
    height: 230px;
    transform: rotate(-25deg);
    border-style: dashed;
    border-color: rgba(148,210,226,.15);
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
    border: 1px solid rgba(255,255,255,.7);
    border-radius: 17px;
    background: rgba(255,255,255,.97);
    box-shadow: 0 25px 55px rgba(0,0,0,.28);
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
    border-bottom: 1px solid #e8eef5;
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
    background: linear-gradient(135deg,var(--blue),var(--teal));
}

.ballot-mini-logo svg {
    width: 16px;
    height: 16px;
}

.ballot-subtitle {
    margin-bottom: 12px;
    color: #8190a4;
    font-size: 9px;
}

.candidate-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px;
    margin-bottom: 8px;
    border: 1px solid #e5ebf3;
    border-radius: 9px;
    background: #f9fbfe;
}

.candidate-row:nth-of-type(3) {
    animation: selectedGlow 3s ease-in-out infinite;
}

@keyframes selectedGlow {
    0%,100% {
        border-color: #dce7f3;
        background: #f9fbfe;
    }
    50% {
        border-color: #59cbb8;
        background: #ecfaf7;
    }
}

.candidate-avatar {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 9px;
    color: #315a83;
    background: #dceaff;
    font-size: 10px;
    font-weight: 800;
}

.candidate-avatar.teal {
    color: #087e75;
    background: #d7f5ef;
}

.candidate-avatar.purple {
    color: #7252ac;
    background: #eee5ff;
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
    background: #9cafc5;
}

.candidate-small-line {
    width: 46px;
    height: 4px;
    border-radius: 20px;
    background: #d3deea;
}

.candidate-check {
    width: 19px;
    height: 19px;
    display: grid;
    place-items: center;
    border: 1.5px solid #c2cedd;
    border-radius: 50%;
    color: white;
    font-size: 11px;
}

.candidate-row:nth-of-type(3) .candidate-check {
    background: var(--teal);
    border-color: var(--teal);
    animation: checkPop 3s ease-in-out infinite;
}

@keyframes checkPop {
    0%,30%,100% { transform: scale(.8); opacity: .5; }
    45%,80% { transform: scale(1); opacity: 1; }
}

.ballot-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    color: #728298;
    font-size: 9px;
    font-weight: 700;
}

.verified {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #07816e;
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
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 13px;
    color: #a6f2e5;
    background: rgba(37,75,109,.85);
    box-shadow: 0 10px 25px rgba(0,0,0,.16);
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
    color: #a5f3c9;
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
    color: #a9bfd5;
    font-size: 10px;
}

.visual-footer span:last-child {
    color: #71dfce;
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
    background: linear-gradient(135deg,var(--blue),var(--teal));
    box-shadow: 0 9px 20px rgba(37,99,235,.2);
}

.login-top-icon svg {
    width: 27px;
    height: 27px;
}

.login-heading .eyebrow {
    display: block;
    margin-bottom: 9px;
    color: var(--teal);
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
    border: 0;
    border-radius: 5px;
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
    background: linear-gradient(105deg,#2563eb,#1d4ed8);
    box-shadow: 0 7px 16px rgba(37,99,235,.17);
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, filter .2s;
}

.login-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 11px 23px rgba(37,99,235,.25);
    filter: brightness(1.05);
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
    color: var(--teal);
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

    <!-- LEFT: ANIMATED ELECTION VISUAL -->
    <section class="visual-panel">

        <div class="glow glow-one"></div>
        <div class="glow glow-two"></div>

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
                        <div class="candidate-avatar teal">B</div>
                        <div class="candidate-detail">
                            <div class="candidate-line"></div>
                            <div class="candidate-small-line"></div>
                        </div>
                        <div class="candidate-check"></div>
                    </div>

                    <div class="candidate-row">
                        <div class="candidate-avatar purple">C</div>
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

            <form action="login_process.php" method="POST">

                <div class="form-group">
                    <label for="student_id">Student ID</label>

                    <input
                        type="text"
                        id="student_id"
                        name="student_id"
                        placeholder="Enter your Student ID"
                        autocomplete="username"
                        maxlength="20"
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

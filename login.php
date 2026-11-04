
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Secure Online Voting System</title>

<style>
:root {
    --navy: #142B4A;
    --blue: #2563EB;
    --teal: #0D9488;
    --page: #EDF2F8;
    --text: #253449;
    --muted: #68778B;
    --border: #D9E2ED;
}

* {
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    margin: 0;
    display: flex;
    flex-direction: column;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background: var(--page);
}

.topbar {
    min-height: 69px;
    padding: 13px 6%;
    display: flex;
    align-items: center;
    gap: 11px;
    color: #fff;
    background: var(--navy);
    border-bottom: 3px solid var(--teal);
}

.brand-mark {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    color: #8CF3E5;
    background: #254568;
    border-radius: 9px;
}

.brand-mark svg {
    width: 24px;
    height: 24px;
}

.brand-name {
    font-size: 16px;
    font-weight: 750;
}

.brand-name small {
    display: block;
    margin-top: 2px;
    color: #C7D5E7;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .4px;
}

main {
    flex: 1;
    width: min(92%, 430px);
    margin: auto;
    padding: 38px 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-card {
    width: 100%;
    padding: 34px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 9px 28px rgba(20, 43, 74, .07);
}

.login-heading {
    margin-bottom: 26px;
}

.login-heading .eyebrow {
    display: block;
    margin-bottom: 8px;
    color: var(--teal);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

h1 {
    margin: 0 0 9px;
    color: var(--navy);
    font-size: 28px;
    letter-spacing: -.5px;
}

.login-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 7px;
    color: var(--navy);
    font-size: 13px;
    font-weight: 700;
}

.input-wrap {
    position: relative;
}

input {
    width: 100%;
    min-height: 47px;
    padding: 12px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    background: #FCFDFF;
    color: var(--text);
    font: inherit;
    font-size: 14px;
}

input::placeholder {
    color: #94A3B8;
}

input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

#password {
    padding-right: 46px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    padding: 6px;
    display: grid;
    place-items: center;
    color: var(--muted);
    background: transparent;
    border: 0;
    border-radius: 5px;
    cursor: pointer;
}

.toggle-password:hover {
    color: var(--blue);
    background: #EFF6FF;
}

.toggle-password svg {
    width: 19px;
    height: 19px;
}

.login-button {
    width: 100%;
    min-height: 47px;
    margin-top: 4px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    color: #fff;
    background: var(--blue);
    border: 0;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
    font-weight: 750;
    cursor: pointer;
    transition: background .15s ease;
}

.login-button:hover {
    background: #1D4ED8;
}

.login-button svg {
    width: 18px;
    height: 18px;
}

.footer {
    padding: 0 16px 22px;
    color: var(--muted);
    text-align: center;
    font-size: 11px;
}

@media (max-width: 480px) {
    main {
        width: 92%;
        padding: 25px 0;
    }

    .login-card {
        padding: 25px 21px;
    }

    h1 {
        font-size: 25px;
    }

    .topbar {
        padding: 12px 4%;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3L20 6.5V11.5C20 16.4 16.5 20 12 21C7.5 20 4 16.4 4 11.5V6.5L12 3Z"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linejoin="round"/>
            <path d="M8.5 12L10.8 14.3L15.8 9.3"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    <div class="brand-name">
        Secure Voting System
        <small>Cybersecurity and Networking</small>
    </div>
</header>

<main>
    <section class="login-card">
        <div class="login-heading">
            <span class="eyebrow">Account access</span>
            <h1>Sign in</h1>
            <p>Enter your registered student ID and password to continue.</p>
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
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z"
                                  stroke="currentColor" stroke-width="1.7"/>
                            <circle cx="12" cy="12" r="3"
                                    stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-button">
                Sign In
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12H19M12 5L19 12L12 19"
                          stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
    </section>
</main>

<footer class="footer">
    Secure Online Voting System &copy; <?= date("Y") ?>
</footer>

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

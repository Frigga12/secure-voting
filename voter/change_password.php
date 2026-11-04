
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "voter"
) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$error = "";
$success = "";

if (empty($_SESSION["password_change_token"])) {
    $_SESSION["password_change_token"] = bin2hex(random_bytes(32));
}

/*
 * This is a small blocklist, not a complete common-password database.
 * Server-side validation is authoritative; JavaScript is only a preview.
 */
$commonPasswords = [
    "password123",
    "password1234",
    "123456789012",
    "qwerty123456",
    "admin123456",
    "letmein12345",
    "welcome12345",
    "iloveyou123",
    "123456789",
    "qwerty123",
    "password",
    "12345678"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["password_change_token"], $token)
    ) {
        $error = "Your session could not be verified. Refresh the page and try again.";
    } else {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            !is_string($currentPassword) ||
            !is_string($newPassword) ||
            !is_string($confirmPassword)
        ) {
            $error = "Invalid form data. Please try again.";
        } elseif (
            $currentPassword === "" ||
            $newPassword === "" ||
            $confirmPassword === ""
        ) {
            $error = "Complete all three password fields.";
        } elseif (strlen($newPassword) > 4096) {
            $error = "Your new password is too long.";
        } else {
            $passwordLength = function_exists("mb_strlen")
                ? mb_strlen($newPassword, "UTF-8")
                : strlen($newPassword);

            $normalisedPassword = strtolower(trim($newPassword));

            if ($passwordLength < 12) {
                $error = "Your new password must contain at least 12 characters.";
            } elseif (!preg_match('/[a-z]/i', $newPassword)) {
                $error = "Your new password must contain at least one letter.";
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $error = "Your new password must contain at least one number.";
            } elseif (in_array($normalisedPassword, $commonPasswords, true)) {
                $error = "Choose a less common password.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "Your new password and confirmation do not match.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        SELECT password
                        FROM users
                        WHERE id = :id AND role = 'voter'
                        LIMIT 1
                    ");

                    $stmt->execute([
                        "id" => $_SESSION["user_id"]
                    ]);

                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (
                        !$user ||
                        !password_verify($currentPassword, $user["password"])
                    ) {
                        $error = "Your current password is incorrect.";
                    } elseif (
                        password_verify($newPassword, $user["password"])
                    ) {
                        $error = "Your new password must be different from your current password.";
                    } else {
                        $hashedPassword = password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );

                        $update = $pdo->prepare("
                            UPDATE users
                            SET password = :password,
                                must_change_pass = 0
                            WHERE id = :id AND role = 'voter'
                        ");

                        $update->execute([
                            "password" => $hashedPassword,
                            "id" => $_SESSION["user_id"]
                        ]);

                        /*
                         * Verify the saved password instead of relying only
                         * on rowCount(), which can vary with database settings.
                         */
                        $verify = $pdo->prepare("
                            SELECT password, must_change_pass
                            FROM users
                            WHERE id = :id AND role = 'voter'
                            LIMIT 1
                        ");

                        $verify->execute([
                            "id" => $_SESSION["user_id"]
                        ]);

                        $savedUser = $verify->fetch(PDO::FETCH_ASSOC);

                        if (
                            $savedUser &&
                            (int)$savedUser["must_change_pass"] === 0 &&
                            password_verify(
                                $newPassword,
                                $savedUser["password"]
                            )
                        ) {
                            /*
                             * Rotate the current session ID.
                             * This alone does NOT revoke the user's other
                             * active sessions on other devices.
                             */
                            session_regenerate_id(true);

                            $_SESSION["password_change_token"] =
                                bin2hex(random_bytes(32));

                            $success = "Password changed successfully.";
                        } else {
                            $error = "The password could not be confirmed as saved. Please try again.";
                        }
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "A database error prevented the password update. Please try again.";
                }
            }
        }
    }
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | Secure Voting</title>

<style>
:root {
    --navy: #142B4A;
    --blue: #2563EB;
    --teal: #0D9488;
    --page: #EDF2F8;
    --text: #253449;
    --muted: #68778B;
    --border: #D9E2ED;
    --green: #15803D;
    --red: #B42318;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);
    background: var(--page);
}

.topbar {
    min-height: 69px;
    padding: 13px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    color: #fff;
    background: var(--navy);
    border-bottom: 3px solid var(--teal);
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    font-weight: 750;
    font-size: 16px;
}

.brand-mark {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    color: #8CF3E5;
    background: #254568;
}

.brand-mark svg {
    width: 23px;
    height: 23px;
}

.brand small {
    display: block;
    margin-top: 2px;
    color: #C7D5E7;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .35px;
}

main {
    width: min(92%, 520px);
    margin: 35px auto 25px;
}

.breadcrumb {
    margin-bottom: 17px;
    color: var(--muted);
    font-size: 13px;
}

.breadcrumb a {
    color: var(--blue);
    text-decoration: none;
    font-weight: 650;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb span {
    padding: 0 8px;
    color: #94A3B8;
}

.card {
    padding: 31px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: 0 8px 26px rgba(20, 43, 74, .06);
}

.card-heading {
    margin-bottom: 25px;
}

.card-heading .eyebrow {
    display: block;
    margin-bottom: 7px;
    color: var(--teal);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .9px;
    text-transform: uppercase;
}

h1 {
    margin: 0 0 8px;
    color: var(--navy);
    font-size: 27px;
    letter-spacing: -.45px;
}

.intro {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

.message {
    margin-bottom: 18px;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.message.error {
    color: var(--red);
    background: #FFF1F0;
    border: 1px solid #FECACA;
}

.message.success {
    color: #166534;
    background: #ECFDF3;
    border: 1px solid #BBF7D0;
}

.form-group {
    margin-bottom: 19px;
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

input[type="password"],
input[type="text"] {
    width: 100%;
    min-height: 46px;
    padding: 11px 45px 11px 12px;
    color: var(--text);
    background: #FCFDFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
}

input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

input::placeholder {
    color: #94A3B8;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    padding: 6px;
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

.rules {
    margin: -2px 0 20px;
    padding: 14px;
    background: #F8FAFD;
    border: 1px solid #E1E8F1;
    border-radius: 9px;
}

.rules-title {
    margin-bottom: 10px;
    color: var(--navy);
    font-size: 12px;
    font-weight: 750;
}

.rule-list {
    display: grid;
    gap: 9px;
    padding: 0;
    margin: 0;
    list-style: none;
}

.rule {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #64748B;
    font-size: 12px;
}

.rule-mark {
    width: 17px;
    height: 17px;
    flex: 0 0 17px;
    display: grid;
    place-items: center;
    border: 1px solid #CBD5E1;
    border-radius: 50%;
    background: #fff;
    color: transparent;
    font-size: 11px;
    font-weight: 800;
}

.rule.met {
    color: #166534;
}

.rule.met .rule-mark {
    color: #fff;
    background: var(--green);
    border-color: var(--green);
}

.strength {
    margin-top: 15px;
}

.strength-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 7px;
    color: var(--muted);
    font-size: 11px;
}

.strength-label {
    color: #64748B;
    font-weight: 750;
}

.strength-track {
    height: 5px;
    overflow: hidden;
    border-radius: 10px;
    background: #E2E8F0;
}

.strength-fill {
    width: 0;
    height: 100%;
    background: #94A3B8;
    border-radius: inherit;
    transition: width .18s ease, background .18s ease;
}

.actions {
    display: grid;
    gap: 10px;
    margin-top: 22px;
}

.button {
    min-height: 45px;
    padding: 12px 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: background .15s ease, transform .15s ease;
}

.button:active {
    transform: translateY(1px);
}

.button-primary {
    color: white;
    background: var(--blue);
}

.button-primary:hover {
    background: #1D4ED8;
}

.button-secondary {
    color: var(--navy);
    background: #EAF0F7;
    border: 1px solid #D9E2ED;
}

.button-secondary:hover {
    background: #DFE8F3;
}

.footer {
    margin-top: 20px;
    color: var(--muted);
    text-align: center;
    font-size: 11px;
}

@media (max-width: 480px) {
    main {
        width: 94%;
        margin-top: 22px;
    }

    .card {
        padding: 23px 19px;
    }

    h1 {
        font-size: 24px;
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
    <div class="brand">
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
        <div>
            Secure Voting System
            <small>Voter account</small>
        </div>
    </div>
</header>

<main>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span>/</span>
        <strong>Change Password</strong>
    </nav>

    <section class="card">
        <div class="card-heading">
            <span class="eyebrow">Account settings</span>
            <h1>Change Password</h1>
            <p class="intro">Enter your current password and choose a new one.</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="message success" role="status"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="change_password.php" id="passwordForm">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["password_change_token"]) ?>"
            >

            <div class="form-group">
                <label for="current_password">Current password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        placeholder="Enter current password"
                        required
                    >
                    <button type="button" class="toggle-password"
                            data-target="current_password"
                            aria-label="Show current password">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z"
                                  stroke="currentColor" stroke-width="1.7"/>
                            <circle cx="12" cy="12" r="3"
                                    stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        autocomplete="new-password"
                        placeholder="Create a new password"
                        minlength="12"
                        maxlength="4096"
                        required
                    >
                    <button type="button" class="toggle-password"
                            data-target="new_password"
                            aria-label="Show new password">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z"
                                  stroke="currentColor" stroke-width="1.7"/>
                            <circle cx="12" cy="12" r="3"
                                    stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                        placeholder="Enter the new password again"
                        minlength="12"
                        maxlength="4096"
                        required
                    >
                    <button type="button" class="toggle-password"
                            data-target="confirm_password"
                            aria-label="Show password confirmation">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5S21.5 12 21.5 12S18 18.5 12 18.5S2.5 12 2.5 12Z"
                                  stroke="currentColor" stroke-width="1.7"/>
                            <circle cx="12" cy="12" r="3"
                                    stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <section class="rules" aria-label="Password requirements">
                <p class="rules-title">Password requirements</p>

                <ul class="rule-list">
                    <li class="rule" id="rule-length">
                        <span class="rule-mark">✓</span>
                        <span>At least 12 characters</span>
                    </li>
                    <li class="rule" id="rule-letter">
                        <span class="rule-mark">✓</span>
                        <span>At least one letter</span>
                    </li>
                    <li class="rule" id="rule-number">
                        <span class="rule-mark">✓</span>
                        <span>At least one number</span>
                    </li>
                    <li class="rule" id="rule-common">
                        <span class="rule-mark">✓</span>
                        <span>Not on the common-password blocklist</span>
                    </li>
                    <li class="rule" id="rule-different">
                        <span class="rule-mark">✓</span>
                        <span>Different from your current password</span>
                    </li>
                </ul>

                <div class="strength">
                    <div class="strength-head">
                        <span>Password strength</span>
                        <span class="strength-label" id="strengthLabel">Not entered</span>
                    </div>
                    <div class="strength-track" aria-hidden="true">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                </div>
            </section>

            <div class="actions">
                <button type="submit" class="button button-primary">
                    Update password
                </button>
                <a href="dashboard.php" class="button button-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </section>

    <footer class="footer">
        Secure Voting System &copy; <?= date("Y") ?>
    </footer>
</main>

<script>
(function () {
    const currentInput = document.getElementById("current_password");
    const newInput = document.getElementById("new_password");
    const confirmInput = document.getElementById("confirm_password");
    const strengthFill = document.getElementById("strengthFill");
    const strengthLabel = document.getElementById("strengthLabel");

    const commonPasswords = new Set([
        "password123",
        "password1234",
        "123456789012",
        "qwerty123456",
        "admin123456",
        "letmein12345",
        "welcome12345",
        "iloveyou123",
        "123456789",
        "qwerty123",
        "password",
        "12345678"
    ]);

    function passwordLength(value) {
        return Array.from(value).length;
    }

    function setRule(id, passed) {
        const element = document.getElementById(id);
        element.classList.toggle("met", passed);
    }

    function updatePasswordFeedback() {
        const value = newInput.value;
        const current = currentInput.value;
        const lower = value.trim().toLowerCase();

        const rules = {
            length: passwordLength(value) >= 12,
            letter: /[a-z]/i.test(value),
            number: /[0-9]/.test(value),
            common: value.length > 0 && !commonPasswords.has(lower),
            different: value.length > 0 && current.length > 0 && value !== current
        };

        setRule("rule-length", rules.length);
        setRule("rule-letter", rules.letter);
        setRule("rule-number", rules.number);
        setRule("rule-common", rules.common);
        setRule("rule-different", rules.different);

        const score = Object.values(rules).filter(Boolean).length;
        const hasMixedCase = /[a-z]/.test(value) && /[A-Z]/.test(value);
        const hasSymbol = /[^a-zA-Z0-9]/.test(value);

        let strength = 0;
        if (value.length > 0) {
            strength = score;
            if (hasMixedCase) strength++;
            if (hasSymbol) strength++;
        }

        const percentage = value.length === 0 ? 0 : Math.min(100, strength / 7 * 100);
        strengthFill.style.width = percentage + "%";

        if (value.length === 0) {
            strengthLabel.textContent = "Not entered";
            strengthFill.style.background = "#94A3B8";
        } else if (strength <= 2) {
            strengthLabel.textContent = "Weak";
            strengthFill.style.background = "#DC2626";
        } else if (strength <= 4) {
            strengthLabel.textContent = "Fair";
            strengthFill.style.background = "#D97706";
        } else if (strength <= 5) {
            strengthLabel.textContent = "Good";
            strengthFill.style.background = "#2563EB";
        } else {
            strengthLabel.textContent = "Strong";
            strengthFill.style.background = "#15803D";
        }

        confirmInput.setCustomValidity(
            confirmInput.value !== "" && confirmInput.value !== value
                ? "The new passwords do not match."
                : ""
        );
    }

    [currentInput, newInput, confirmInput].forEach(function (input) {
        input.addEventListener("input", updatePasswordFeedback);
    });

    document.querySelectorAll(".toggle-password").forEach(function (button) {
        button.addEventListener("click", function () {
            const input = document.getElementById(button.dataset.target);
            const showing = input.type === "password";

            input.type = showing ? "text" : "password";
            button.setAttribute(
                "aria-label",
                (showing ? "Hide " : "Show ") +
                input.labels[0].textContent.toLowerCase()
            );
        });
    });

    document.getElementById("passwordForm").addEventListener("submit", function (event) {
        updatePasswordFeedback();

        const allValid =
            passwordLength(newInput.value) >= 12 &&
            /[a-z]/i.test(newInput.value) &&
            /[0-9]/.test(newInput.value) &&
            !commonPasswords.has(newInput.value.trim().toLowerCase()) &&
            newInput.value !== currentInput.value &&
            newInput.value === confirmInput.value;

        if (!allValid) {
            event.preventDefault();
            newInput.focus();
        }
    });

    updatePasswordFeedback();
})();
</script>
</body>
</html>

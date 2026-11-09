
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$student_id = "";
$full_name = "";
$department = "";
$level = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim($_POST["student_id"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $level = trim($_POST["level"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    $sessionToken = (string)($_SESSION["csrf_token"] ?? "");
    $postedToken = (string)($_POST["csrf_token"] ?? "");

    if (
        $sessionToken === "" ||
        $postedToken === "" ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        $error = "Your form session has expired. Refresh the page and try again.";
    } elseif (
        $student_id === "" ||
        $full_name === "" ||
        $department === "" ||
        $level === "" ||
        $password === ""
    ) {
        $error = "Please complete every required field.";
    } elseif (strlen($student_id) > 20) {
        $error = "The Student ID must not exceed 20 characters.";
    } elseif (strlen($full_name) > 150 || strlen($department) > 150) {
        $error = "The name or department is too long.";
    } elseif (
        !ctype_digit($level) ||
        (int)$level < 100 ||
        (int)$level > 700 ||
        (int)$level % 100 !== 0
    ) {
        $error = "Select a valid level between 100 and 700.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "The temporary password must contain at least 6 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT student_id, email
                FROM users
                WHERE student_id = :student_id OR (:email != '' AND email = :email)
                LIMIT 1
            ");
            $stmt->execute([
                "student_id" => $student_id,
                "email"      => $email,
            ]);
            $existing = $stmt->fetch();

            if ($existing) {
                if (strcasecmp($existing["student_id"], $student_id) === 0) {
                    $error = "A user with this Student ID already exists.";
                } else {
                    $error = "A user with this Email address already exists.";
                }
            } else {
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        student_id,
                        email,
                        full_name,
                        department,
                        level,
                        password,
                        role,
                        status,
                        must_change_pass
                    ) VALUES (
                        :student_id,
                        :email,
                        :full_name,
                        :department,
                        :level,
                        :password,
                        'voter',
                        'active',
                        1
                    )
                ");

                $stmt->execute([
                    "student_id" => $student_id,
                    "email"      => $email !== "" ? $email : null,
                    "full_name"  => $full_name,
                    "department" => $department,
                    "level"      => (int)$level,
                    "password"   => $hashed_password
                ]);

                header("Location: voters.php?registered=1");
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === "23000") {
                $error = "This Student ID is already registered.";
            } else {
                error_log("Voter registration failed: " . $exception->getMessage());
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Voter | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --background: #edf3fb;
            --white: #ffffff;
            --text: #182b45;
            --muted: #64748b;
            --border: #dbe5f1;
            --shadow: 0 12px 35px rgba(20, 43, 74, .08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 17px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 4px 16px rgba(20, 43, 74, .12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 750;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #0d9488);
            color: white;
            font-size: 20px;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
            font-weight: 400;
        }

        .top-link {
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 9px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
        }

        .top-link:hover {
            background: rgba(255, 255, 255, .1);
        }

        .container {
            width: min(100% - 32px, 900px);
            margin: 32px auto 50px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--blue);
            font-weight: 650;
            text-decoration: none;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 9px;
            color: var(--navy);
            font-size: clamp(25px, 4vw, 32px);
            letter-spacing: -.5px;
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .heading-icon {
            width: 54px;
            height: 54px;
            flex: 0 0 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: #dce9ff;
            color: var(--blue);
            font-size: 25px;
        }

        .form-card {
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .card-heading {
            padding: 23px 28px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(110deg, #e3edff, #e2f6f2);
        }

        .card-heading h2 {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 18px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .form-content {
            padding: 28px;
        }

        .error {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 14px 16px;
            margin-bottom: 24px;
            border: 1px solid #fecaca;
            border-radius: 11px;
            background: #fff1f0;
            color: #b42318;
            font-size: 13px;
            line-height: 1.6;
        }

        .error-mark {
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #fee2e2;
            font-weight: 800;
        }

        .section-title {
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--navy);
            font-size: 15px;
            font-weight: 750;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 20px;
        }

        .form-group {
            min-width: 0;
            margin-bottom: 23px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .required {
            color: #dc2626;
        }

        input, select {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #cbd8e8;
            border-radius: 10px;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .help-text {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 85px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 9px;
            transform: translateY(-50%);
            border: none;
            background: #e8eef7;
            color: var(--navy);
            padding: 7px 9px;
            border-radius: 7px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 750;
            cursor: pointer;
        }

        .password-toggle:hover {
            background: #dbe5f1;
        }

        .security-note {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            padding: 15px 16px;
            margin: 0 0 25px;
            border: 1px solid #b8e9df;
            border-radius: 11px;
            background: #e5f6f3;
            color: #11675f;
            font-size: 12px;
            line-height: 1.7;
        }

        .security-note strong {
            flex-shrink: 0;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 18px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, transform .15s;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #e8eef7;
            color: var(--navy);
        }

        .btn-secondary:hover {
            background: #dbe5f1;
        }

        .footer {
            margin-top: 24px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 14px 16px;
            }

            .brand {
                font-size: 14px;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
            }

            .top-link {
                padding: 9px 10px;
                font-size: 12px;
            }

            .container {
                margin-top: 24px;
            }

            .form-content {
                padding: 20px;
            }

            .card-heading {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-heading {
                align-items: flex-start;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">S</div>
        <div>
            Secure Voting System
            <span class="brand-subtitle">Administration Portal</span>
        </div>
    </div>

    <a class="top-link" href="voters.php">← Back to Voters</a>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;/&nbsp;
        <a href="voters.php">Voters</a>
        &nbsp;/&nbsp;
        Register Voter
    </div>

    <div class="page-heading">
        <div>
            <h1>Register Voter</h1>
            <p>Create a student account and assign its initial login password.</p>
        </div>
        <div class="heading-icon" aria-hidden="true">＋</div>
    </div>

    <section class="form-card">
        <div class="card-heading">
            <h2>Student Account Information</h2>
            <p>Complete the student's details below. Fields marked with <span class="required">*</span> are required.</p>
        </div>

        <div class="form-content">
            <?php if ($error !== ""): ?>
                <div class="error" role="alert">
                    <span class="error-mark">!</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_voter.php" id="voterForm">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["csrf_token"]) ?>"
                >

                <h3 class="section-title">Personal and Academic Details</h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">
                            Student ID <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            value="<?= e($student_id) ?>"
                            maxlength="20"
                            placeholder="Enter student ID"
                            autocomplete="off"
                            required
                        >
                        <span class="help-text">Use the student's unique school identification number.</span>
                    </div>

                    <div class="form-group">
                        <label for="full_name">
                            Full Name <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= e($full_name) ?>"
                            maxlength="150"
                            placeholder="Enter student's full name"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="department">
                            Department <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="department"
                            name="department"
                            value="<?= e($department) ?>"
                            maxlength="150"
                            placeholder="e.g. Computer Engineering"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="level">
                            Student Level <span class="required">*</span>
                        </label>
                        <select id="level" name="level" required>
                            <option value="">Select level</option>
                            <?php foreach ([100, 200, 300, 400, 500, 600, 700] as $studentLevel): ?>
                                <option
                                    value="<?= $studentLevel ?>"
                                    <?= (string)$level === (string)$studentLevel ? "selected" : "" ?>
                                >
                                    <?= $studentLevel ?> Level
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="email">
                            Email Address <span style="color:var(--muted); font-weight:normal;">(Optional, for notifications)</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e($email) ?>"
                            maxlength="190"
                            placeholder="e.g. student@example.com"
                        >
                    </div>
                </div>

                <h3 class="section-title">Initial Login Password</h3>

                <div class="form-group">
                    <label for="password">
                        Temporary Password <span class="required">*</span>
                    </label>

                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="6"
                            autocomplete="new-password"
                            placeholder="Create a temporary password"
                            required
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-controls="password"
                            aria-pressed="false"
                        >Show</button>
                    </div>

                    <span class="help-text">
                        Use at least 6 characters. Give the temporary password
                        securely to the registered student.
                    </span>
                </div>

                <div class="security-note">
                    <strong aria-hidden="true">i</strong>
                    <span>
                        The password will be stored as a password hash. This account
                        is created with the temporary-password flag enabled. The
                        login flow must enforce that flag for a password change to
                        be required.
                    </span>
                </div>

                <div class="actions">
                    <a href="voters.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        Register Voter &nbsp; →
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="footer">
        Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
    </div>
</main>

<script>
const passwordInput = document.getElementById("password");
const passwordToggle = document.getElementById("togglePassword");

passwordToggle.addEventListener("click", function () {
    const showingPassword = passwordInput.type === "text";

    passwordInput.type = showingPassword ? "password" : "text";
    passwordToggle.textContent = showingPassword ? "Show" : "Hide";
    passwordToggle.setAttribute("aria-pressed", String(!showingPassword));
});
</script>
</body>
</html>

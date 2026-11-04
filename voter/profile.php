
<?php

require_once __DIR__ . "/session_guard.php";

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function displayValue($value): string
{
    if ($value === null || trim((string)$value) === "") {
        return "Not provided";
    }

    return e($value);
}

$stmt = $pdo->prepare("
    SELECT student_id, full_name, department, level, status
    FROM users
    WHERE id = :id
      AND role = 'voter'
    LIMIT 1
");

$stmt->execute(["id" => $_SESSION["user_id"]]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voter) {
    http_response_code(404);
    exit("Voter account not found. Please contact the administrator.");
}

$name = trim((string)($voter["full_name"] ?? ""));

if ($name === "") {
    $name = "Student";
}

$initial = strtoupper(substr($name, 0, 1));

$accountStatus = strtolower(trim((string)($voter["status"] ?? "")));

if ($accountStatus === "active") {
    $statusClass = "status-active";
    $statusLabel = "Active";
} elseif (in_array($accountStatus, ["inactive", "disabled"], true)) {
    $statusClass = "status-inactive";
    $statusLabel = ucfirst($accountStatus);
} else {
    $statusClass = "status-other";
    $statusLabel = $accountStatus !== "" ? ucfirst($accountStatus) : "Unknown";
}

$level = ($voter["level"] !== null && $voter["level"] !== "")
    ? displayValue($voter["level"]) . " Level"
    : "Not provided";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-light: #1d4775;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --bg: #edf3fb;
            --white: #ffffff;
            --text: #182b45;
            --muted: #64748b;
            --border: #dce5f0;
            --green: #087a55;
            --green-bg: #ddf7ec;
            --red: #b42318;
            --red-bg: #fee2e2;
            --grey: #53647a;
            --grey-bg: #e9eef5;
            --shadow: 0 8px 25px rgba(20,43,74,.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 15px 5%;
            background: var(--navy);
            border-bottom: 4px solid var(--teal);
            color: white;
            box-shadow: 0 5px 18px rgba(20,43,74,.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .brand-title {
            color: white;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 9px 13px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 9px;
            background: #274567;
            color: white;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: background .2s;
        }

        .header-link:hover {
            background: #365b85;
        }

        .container {
            width: min(1000px, calc(100% - 40px));
            margin: 30px auto 0;
            flex: 1;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--blue);
            font-weight: 650;
        }

        .breadcrumb strong {
            color: var(--navy);
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .eyebrow {
            margin-bottom: 7px;
            color: var(--teal);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            color: var(--navy);
            font-size: clamp(25px, 3vw, 32px);
            line-height: 1.3;
            letter-spacing: -.5px;
        }

        .page-heading p {
            margin-top: 7px;
            color: var(--muted);
            font-size: 13px;
        }

        .heading-icon {
            display: grid;
            place-items: center;
            width: 55px;
            height: 55px;
            flex: 0 0 55px;
            border-radius: 16px;
            background: #dce9ff;
            color: var(--blue);
        }

        .heading-icon svg {
            width: 27px;
            height: 27px;
        }

        .profile-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: white;
            box-shadow: var(--shadow);
        }

        .profile-top {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 21px;
            padding: 29px;
            background: linear-gradient(115deg,#142b4a 0%,#1d4775 68%,#0d9488 140%);
            color: white;
        }

        .profile-top::after {
            content: "";
            position: absolute;
            top: -100px;
            right: -55px;
            width: 210px;
            height: 210px;
            border: 30px solid rgba(255,255,255,.07);
            border-radius: 50%;
            pointer-events: none;
        }

        .avatar {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 22px;
            background: rgba(255,255,255,.15);
            color: white;
            font-size: 31px;
            font-weight: 800;
        }

        .profile-heading {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .profile-heading h2 {
            margin-bottom: 5px;
            color: white;
            font-size: clamp(20px, 3vw, 25px);
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .profile-heading p {
            color: #dceafb;
            font-size: 13px;
        }

        .profile-heading .student-id {
            margin-top: 7px;
            color: #e4efff;
            font-size: 12px;
        }

        .profile-body {
            padding: 28px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-heading h3 {
            color: var(--navy);
            font-size: 17px;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .section-icon {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            flex: 0 0 39px;
            border-radius: 11px;
            background: #e5eeff;
            color: var(--blue);
        }

        .section-icon svg {
            width: 21px;
            height: 21px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .detail {
            min-width: 0;
            padding: 17px;
            border: 1px solid #e5ecf5;
            border-radius: 12px;
            background: #f8faff;
            transition: border-color .2s, background .2s;
        }

        .detail:hover {
            border-color: #cbdcf4;
            background: #f3f7ff;
        }

        .detail-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .detail-label svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: var(--blue);
        }

        .detail-value {
            color: var(--navy);
            font-size: 14px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-active {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-inactive {
            background: var(--red-bg);
            color: var(--red);
        }

        .status-other {
            background: var(--grey-bg);
            color: var(--grey);
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 23px;
            padding: 17px;
            border: 1px solid #cdddf5;
            border-radius: 12px;
            background: #e6efff;
            color: #345274;
            font-size: 12px;
            line-height: 1.8;
        }

        .note-icon {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            flex: 0 0 26px;
            border-radius: 50%;
            background: #d1e2ff;
            color: #2457b5;
            font-weight: 900;
        }

        .security-note strong {
            display: block;
            margin-bottom: 3px;
            color: var(--navy);
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 11px;
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 11px 17px;
            border: none;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .footer {
            margin-top: 35px;
            padding: 22px 15px 28px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 13px 14px;
                gap: 9px;
            }

            .brand {
                gap: 8px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle {
                font-size: 10px;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
            }

            .header-actions {
                gap: 6px;
            }

            .header-link {
                padding: 8px 9px;
                font-size: 11px;
            }

            .container {
                width: calc(100% - 28px);
                margin-top: 22px;
            }

            .page-heading {
                align-items: flex-start;
            }

            .profile-top {
                align-items: flex-start;
                gap: 15px;
                padding: 22px;
            }

            .avatar {
                width: 57px;
                height: 57px;
                flex-basis: 57px;
                border-radius: 16px;
                font-size: 24px;
            }

            .profile-body {
                padding: 21px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
                gap: 11px;
            }

            .detail {
                padding: 15px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12.5 10 17l9-10"/>
            </svg>
        </div>

        <div>
            <div class="brand-title">Secure Voting System</div>
            <div class="brand-subtitle">Student Election Portal</div>
        </div>
    </div>

    <nav class="header-actions" aria-label="Navigation">
        <a href="dashboard.php" class="header-link">Dashboard</a>
        <a href="../logout.php" class="header-link">Log out</a>
    </nav>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        <strong>My Profile</strong>
    </div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Student account</div>
            <h1>My Profile</h1>
            <p>View your registered student details and account information.</p>
        </div>

        <div class="heading-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
            </svg>
        </div>
    </section>

    <section class="profile-card">
        <div class="profile-top">
            <div class="avatar" aria-label="Name initial">
                <?= e($initial) ?>
            </div>

            <div class="profile-heading">
                <h2><?= e($name) ?></h2>
                <p>Registered Voter Account</p>
                <div class="student-id">
                    Student ID: <?= displayValue($voter["student_id"]) ?>
                </div>
            </div>
        </div>

        <div class="profile-body">
            <div class="section-heading">
                <div>
                    <h3>Student Information</h3>
                    <p>Your registered academic details.</p>
                </div>

                <div class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <path d="M7 9h10M7 13h6"/>
                    </svg>
                </div>
            </div>

            <div class="detail-grid">
                <article class="detail">
                    <div class="detail-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                        </svg>
                        Full Name
                    </div>
                    <div class="detail-value">
                        <?= displayValue($voter["full_name"]) ?>
                    </div>
                </article>

                <article class="detail">
                    <div class="detail-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="3" width="16" height="18" rx="2"/>
                            <path d="M8 8h8M8 12h8M8 16h4"/>
                        </svg>
                        Student ID
                    </div>
                    <div class="detail-value">
                        <?= displayValue($voter["student_id"]) ?>
                    </div>
                </article>

                <article class="detail">
                    <div class="detail-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21h18M5 21V7l7-4 7 4v14"/>
                            <path d="M9 10h1M14 10h1M9 14h1M14 14h1M10 21v-3h4v3"/>
                        </svg>
                        Department
                    </div>
                    <div class="detail-value">
                        <?= displayValue($voter["department"]) ?>
                    </div>
                </article>

                <article class="detail">
                    <div class="detail-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m2 9 10-5 10 5-10 5L2 9Z"/>
                            <path d="M6 11v5c3 3 9 3 12 0v-5M22 9v6"/>
                        </svg>
                        Academic Level
                    </div>
                    <div class="detail-value">
                        <?= $level ?>
                    </div>
                </article>

                <article class="detail">
                    <div class="detail-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="m8 12 2.5 2.5L16 9"/>
                        </svg>
                        Account Status
                    </div>
                    <div class="detail-value">
                        <span class="status <?= e($statusClass) ?>">
                            <?= e($statusLabel) ?>
                        </span>
                    </div>
                </article>
            </div>

            <div class="security-note">
                <div class="note-icon" aria-hidden="true">i</div>
                <div>
                    <strong>Account Security</strong>
                    Your password is never displayed on this page. If your
                    registered name, department or academic level is incorrect,
                    contact the election administrator for assistance.
                </div>
            </div>

            <div class="actions">
                <a href="dashboard.php" class="btn btn-primary">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

</body>
</html>

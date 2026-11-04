
<?php

require_once __DIR__ . "/session_guard.php";

/*
 * Only show this page immediately after a successful vote submission.
 * submit_votes.php sets this flag only after its database transaction commits.
 */
if (empty($_SESSION["vote_completed"])) {
    header("Location: dashboard.php");
    exit;
}

/* Consume the one-time success flag. */
unset($_SESSION["vote_completed"]);

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$fullName = trim((string)($_SESSION["full_name"] ?? "Student"));

if ($fullName === "") {
    $fullName = "Student";
}

$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vote Submitted Successfully | Secure Voting</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-light: #1d4775;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --bg: #edf3fb;
            --white: #fff;
            --text: #182b45;
            --muted: #64748b;
            --border: #dce5f0;
            --green: #087a55;
            --green-bg: #ddf7ec;
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
            gap: 18px;
            padding: 15px 5%;
            color: white;
            background: var(--navy);
            border-bottom: 4px solid var(--teal);
            box-shadow: 0 5px 18px rgba(20,43,74,.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border-radius: 12px;
            color: white;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 800;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background: #d9f4f0;
            color: #087e75;
            font-size: 14px;
            font-weight: 800;
        }

        .dashboard-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        .dashboard-link:hover {
            background: #365b85;
        }

        .main {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 45px 20px;
        }

        .success-card {
            width: min(100%, 620px);
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--white);
            box-shadow: 0 14px 42px rgba(20,43,74,.08);
            text-align: center;
        }

        .success-banner {
            padding: 35px 25px 28px;
            background: linear-gradient(120deg,#142b4a 0%,#1d4775 65%,#0d9488 140%);
            color: white;
        }

        .success-icon {
            display: grid;
            place-items: center;
            width: 78px;
            height: 78px;
            margin: 0 auto 19px;
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 50%;
            background: rgba(255,255,255,.13);
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            stroke: #b6f5dd;
        }

        .success-label {
            display: inline-block;
            margin-bottom: 12px;
            padding: 5px 11px;
            border: 1px solid rgba(204,251,241,.25);
            border-radius: 30px;
            color: #d7f8f4;
            background: rgba(13,148,136,.25);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .success-banner h1 {
            margin-bottom: 9px;
            color: white;
            font-size: clamp(24px,4vw,31px);
            line-height: 1.35;
        }

        .success-banner p {
            max-width: 430px;
            margin: 0 auto;
            color: #e0ecff;
            font-size: 13px;
        }

        .card-content {
            padding: 30px;
        }

        .personal-greeting {
            margin-bottom: 20px;
            color: var(--navy);
            font-size: 17px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .personal-greeting span {
            color: var(--blue);
        }

        .confirmation-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border: 1px solid #b8e9d2;
            border-radius: 11px;
            background: var(--green-bg);
            color: #116348;
            text-align: left;
        }

        .confirmation-icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #c2f0d9;
            color: var(--green);
        }

        .confirmation-icon svg {
            width: 17px;
            height: 17px;
        }

        .confirmation-box strong {
            display: block;
            margin-bottom: 3px;
            font-size: 13px;
        }

        .confirmation-box p {
            color: #28664f;
            font-size: 12px;
            line-height: 1.7;
        }

        .next-heading {
            margin: 24px 0 7px;
            color: var(--navy);
            font-size: 14px;
        }

        .next-description {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .actions {
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 46px;
            padding: 12px 18px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
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

        .security-reminder {
            margin-top: 21px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .footer {
            padding: 22px 15px 25px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 520px) {
            .topbar {
                padding: 13px 14px;
                gap: 9px;
            }

            .brand {
                gap: 8px;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle {
                font-size: 10px;
            }

            .avatar {
                display: none;
            }

            .dashboard-link {
                padding: 8px 9px;
                font-size: 11px;
            }

            .main {
                padding: 28px 14px;
            }

            .success-card {
                border-radius: 15px;
            }

            .success-banner {
                padding: 30px 20px 25px;
            }

            .success-icon {
                width: 70px;
                height: 70px;
            }

            .success-icon svg {
                width: 36px;
                height: 36px;
            }

            .card-content {
                padding: 22px 18px;
            }

            .btn {
                width: 100%;
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

    <div class="header-right">
        <div class="avatar" title="<?= e($fullName) ?>">
            <?= e($initial) ?>
        </div>

        <a href="dashboard.php" class="dashboard-link">
            Dashboard <span aria-hidden="true">→</span>
        </a>
    </div>
</header>

<main class="main">
    <section class="success-card" aria-labelledby="success-title">

        <div class="success-banner">
            <div class="success-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12.5 10 17l9-10"/>
                </svg>
            </div>

            <div class="success-label">Submission complete</div>

            <h1 id="success-title">Vote Submitted Successfully</h1>

            <p>
                Your voting submission has been completed. Thank you for
                participating in the election.
            </p>
        </div>

        <div class="card-content">
            <div class="personal-greeting">
                Thank you, <span><?= e($fullName) ?></span>.
            </div>

            <div class="confirmation-box">
                <div class="confirmation-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.5 10 17l9-10"/>
                    </svg>
                </div>

                <div>
                    <strong>Your submission was recorded</strong>
                    <p>
                        The system completed your submission successfully.
                        You cannot start another voting session for the same
                        election through this voting flow.
                    </p>
                </div>
            </div>

            <h2 class="next-heading">Return to your dashboard</h2>

            <p class="next-description">
                You can check your account and view available portal options.
            </p>

            <div class="actions">
                <a href="dashboard.php" class="btn btn-primary">
                    Return to Dashboard
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <p class="security-reminder">
                Log out when you finish using a shared computer.
            </p>
        </div>
    </section>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

</body>
</html>

<?php

require_once __DIR__ . "/session_guard.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/*
 * Load ALL elections that are currently within their voting period.
 * Do not block the elections page because the voter has voted in one election.
 */
$stmt = $pdo->query("
    SELECT id, election_name, start_date, end_date
    FROM elections
    WHERE status = 'active'
      AND NOW() BETWEEN start_date AND end_date
    ORDER BY start_date ASC, id ASC
");

$activeElections = $stmt->fetchAll(PDO::FETCH_ASSOC);
$activeElectionCount = count($activeElections);

/* Keep the first active election for the dashboard status card. */
$activeElection = $activeElections[0] ?? null;

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

    <title>Student Dashboard | Secure Voting</title>

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
            --grey: #53647a;
            --grey-bg: #e9eef5;
            --shadow: 0 8px 25px rgba(20, 43, 74, .06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 16px 5%;
            background: var(--navy);
            color: white;
            border-bottom: 4px solid var(--teal);
            box-shadow: 0 5px 18px rgba(20, 43, 74, .13);
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
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #3b82f6, #0d9488);
        }

        .brand-mark svg {
            width: 23px;
            height: 23px;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .brand-subtitle {
            display: block;
            margin-top: 1px;
            color: #c7d7eb;
            font-size: 12px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .portal-label {
            color: #d7e4f5;
            font-size: 13px;
        }

        .header-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #d9f4f0;
            color: #087e75;
            font-size: 15px;
            font-weight: 800;
        }

        .logout {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 9px 13px;
            color: white;
            background: #274567;
            border: 1px solid #59718f;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
        }

        .logout:hover {
            background: #365b85;
        }

        .container {
            width: min(1180px, calc(100% - 40px));
            margin: 32px auto 0;
        }

        .breadcrumb {
            margin-bottom: 21px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb strong {
            color: var(--navy);
        }

        .welcome {
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            padding: 32px;
            color: white;
            background: linear-gradient(
                115deg,
                #142b4a 0%,
                #1d4775 65%,
                #0d9488 140%
            );
            border-radius: 19px;
            box-shadow: 0 12px 30px rgba(20, 43, 74, .13);
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            right: -95px;
            top: -135px;
            border: 35px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            padding: 5px 10px;
            border: 1px solid rgba(204, 251, 241, .25);
            border-radius: 30px;
            color: #d7f8f4;
            background: rgba(13, 148, 136, .25);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .welcome h1 {
            max-width: 750px;
            margin-bottom: 10px;
            font-size: clamp(24px, 3vw, 32px);
            line-height: 1.3;
            letter-spacing: -.5px;
            overflow-wrap: anywhere;
        }

        .welcome p {
            max-width: 650px;
            color: #e0ecff;
            font-size: 14px;
        }

        .welcome-avatar {
            position: relative;
            z-index: 1;
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 22px;
            background: rgba(255, 255, 255, .14);
            font-size: 32px;
            font-weight: 800;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin: 31px 0 17px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 20px;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .portal-pill {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 9px;
            background: #e0eafe;
            color: #2457b5;
            font-size: 11px;
            font-weight: 800;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--white);
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 27px rgba(20, 43, 74, .09);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 17px;
        }

        .card-icon {
            width: 49px;
            height: 49px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 14px;
            background: #e5eeff;
            color: var(--blue);
        }

        .card-icon svg {
            width: 25px;
            height: 25px;
            stroke: currentColor;
        }

        .icon-teal {
            color: #0f766e;
            background: #ccfbf1;
        }

        .icon-purple {
            color: #6d42ba;
            background: #efe7ff;
        }

        .icon-grey {
            color: #53647a;
            background: #e9eef5;
        }

        .card h3 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 17px;
        }

        .card-description {
            flex: 1;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .card-footer {
            margin-top: 20px;
            padding-top: 17px;
            border-top: 1px solid #e8eef6;
        }

        .card-link {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            min-height: 41px;
            padding: 10px 15px;
            border-radius: 9px;
            background: var(--blue);
            color: white;
            font-size: 12px;
            font-weight: 750;
            transition: background .2s;
        }

        .card-link:hover {
            background: var(--blue-dark);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            flex-shrink: 0;
            border-radius: 50%;
            background: currentColor;
        }

        .status-active {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-inactive {
            background: var(--grey-bg);
            color: var(--grey);
        }

        .status-row {
            margin-top: 18px;
        }

        .election-name {
            display: block;
            margin-top: 9px;
            color: var(--navy);
            font-weight: 750;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .info-strip {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 24px;
            padding: 17px 19px;
            border: 1px solid #cdddf5;
            border-radius: 12px;
            background: #e6efff;
            color: #345274;
            font-size: 12px;
            line-height: 1.8;
        }

        .info-icon {
            display: grid;
            place-items: center;
            width: 23px;
            height: 23px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #d1e2ff;
            color: #2457b5;
            font-weight: 800;
        }

        .footer {
            margin-top: 35px;
            padding: 22px 15px 28px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 750px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .welcome {
                padding: 25px;
            }

            .card {
                padding: 22px;
            }
        }

        @media (max-width: 520px) {
            .topbar {
                padding: 13px 15px;
            }

            .brand-title {
                font-size: 14px;
            }

            .brand-subtitle,
            .portal-label {
                display: none;
            }

            .header-right {
                gap: 8px;
            }

            .header-avatar {
                width: 34px;
                height: 34px;
            }

            .logout {
                padding: 8px 10px;
                font-size: 12px;
            }

            .container {
                width: calc(100% - 28px);
                margin-top: 22px;
            }

            .welcome {
                align-items: flex-start;
                padding: 22px;
                border-radius: 15px;
            }

            .welcome h1 {
                font-size: 24px;
            }

            .welcome-avatar {
                width: 47px;
                height: 47px;
                flex-basis: 47px;
                border-radius: 14px;
                font-size: 21px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
                margin-top: 25px;
            }

            .card-link {
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
                 stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12.5 10 17l9-10"/>
            </svg>
        </div>

        <div>
            <div class="brand-title">Secure Voting System</div>
            <div class="brand-subtitle">Student Election Portal</div>
        </div>
    </div>

    <div class="header-right">
        <span class="portal-label">Voter Portal</span>

        <div class="header-avatar" title="<?= e($fullName) ?>">
            <?= e($initial) ?>
        </div>

        <a href="../logout.php" class="logout">Log out</a>
    </div>
</header>

<main class="container">

    <div class="breadcrumb">
        Student Portal &nbsp;›&nbsp; <strong>Dashboard</strong>
    </div>

    <section class="welcome">
        <div class="welcome-content">
            <div class="eyebrow">Your voter dashboard</div>

            <h1>Welcome back, <?= e($fullName) ?>!</h1>

            <p>
                View available elections, check your voting status,
                manage your account and participate in elections for
                which you are eligible.
            </p>
        </div>

        <div class="welcome-avatar" aria-label="Your initial">
            <?= e($initial) ?>
        </div>
    </section>

    <section class="section-heading">
        <div>
            <h2>Your Portal</h2>
            <p>Choose an option to manage your voting activities.</p>
        </div>

        <span class="portal-pill">STUDENT ACCOUNT</span>
    </section>

    <section class="dashboard-grid">

        <!-- Voting card: always allow the voter to view all elections. -->
        <article class="card">

            <div class="card-top">
                <div class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                        <path d="M8 8h8M8 12h8M8 16h4"/>
                        <path d="m15 16 1.5 1.5L20 14"/>
                    </svg>
                </div>
            </div>

            <h3>Cast Your Vote</h3>

            <p class="card-description">
                <?php if ($activeElectionCount > 0): ?>
                    There <?= $activeElectionCount === 1 ? "is" : "are" ?>
                    <?= $activeElectionCount ?>
                    currently open
                    <?= $activeElectionCount === 1 ? "election" : "elections" ?>.
                    Open the election list to check your eligibility and
                    voting status for each one.
                <?php else: ?>
                    No election is currently open within its scheduled
                    voting period. You can still view the elections page
                    to check available election information.
                <?php endif; ?>
            </p>

            <div class="card-footer">
                <a href="elections.php" class="card-link">
                    View Elections <span aria-hidden="true">→</span>
                </a>
            </div>

        </article>

        <!-- Profile card -->
        <article class="card">

            <div class="card-top">
                <div class="card-icon icon-teal" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                    </svg>
                </div>
            </div>

            <h3>My Profile</h3>

            <p class="card-description">
                Review your registered student details, including your name,
                student ID, department and academic level.
            </p>

            <div class="card-footer">
                <a href="profile.php" class="card-link">
                    View My Profile <span aria-hidden="true">→</span>
                </a>
            </div>

        </article>

        <!-- Election status card -->
        <article class="card">

            <div class="card-top">
                <div class="card-icon icon-purple" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </div>
            </div>

            <h3>Election Status</h3>

            <p class="card-description">
                <?php if ($activeElectionCount > 0): ?>
                    <?= $activeElectionCount ?>
                    <?= $activeElectionCount === 1
                        ? "election is"
                        : "elections are" ?>
                    currently open. View the election list to see which
                    ones you can participate in and which ones already
                    have your recorded votes.
                <?php else: ?>
                    There are currently no elections open within
                    their scheduled voting periods.
                <?php endif; ?>
            </p>

            <div class="card-footer">

                <?php if ($activeElectionCount > 0): ?>

                    <span class="status status-active">
                        <?= $activeElectionCount ?>
                        <?= $activeElectionCount === 1
                            ? "Active Election"
                            : "Active Elections" ?>
                    </span>

                    <?php foreach (array_slice($activeElections, 0, 3) as $election): ?>
                        <span class="election-name">
                            <?= e($election["election_name"]) ?>
                        </span>
                    <?php endforeach; ?>

                    <?php if ($activeElectionCount > 3): ?>
                        <span class="election-name">
                            And <?= $activeElectionCount - 3 ?> more...
                        </span>
                    <?php endif; ?>

                    <div class="status-row">
                        <a href="elections.php" class="card-link">
                            Check Election Status
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>

                <?php else: ?>

                    <span class="status status-inactive">
                        No Active Elections
                    </span>

                    <div class="status-row">
                        <a href="elections.php" class="card-link">
                            View Elections <span aria-hidden="true">→</span>
                        </a>
                    </div>

                <?php endif; ?>

            </div>

        </article>

        <!-- Password card -->
        <article class="card">

            <div class="card-top">
                <div class="card-icon icon-grey" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="10" width="14" height="11" rx="2"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        <circle cx="12" cy="15" r="1"/>
                        <path d="M12 16v2"/>
                    </svg>
                </div>
            </div>

            <h3>Change Password</h3>

            <p class="card-description">
                Update your account password to help protect your student
                login credentials.
            </p>

            <div class="card-footer">
                <a href="change_password.php" class="card-link">
                    Update Password <span aria-hidden="true">→</span>
                </a>
            </div>

        </article>

    </section>

    <div class="info-strip">
        <span class="info-icon" aria-hidden="true">i</span>

        <p>
            <strong>Security reminder:</strong>
            Keep your login details private. Always log out after using a
            shared computer, and never share your temporary password.
            Your voting status is checked separately for each election.
        </p>
    </div>

</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

</body>
</html>